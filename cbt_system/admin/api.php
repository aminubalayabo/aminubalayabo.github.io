<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$action = $_REQUEST['action'] ?? '';

// ── DEBUG: resets password and shows DB state ──────────────
// Visit: http://localhost/cbt_system/admin/api.php?action=debug_login
if ($action === 'debug_login') {
    $db = getDB();
    $db->prepare("UPDATE admins SET password='admin' WHERE email='bala.aminu@udusok.edu.ng'")->execute();
    $rows = $db->query('SELECT id, email, name, password FROM admins')->fetchAll();
    jsonOut([
        'php_version'   => phpversion(),
        'admins_in_db'  => count($rows),
        'admin_records' => $rows,
        'action_taken'  => 'Password reset to plain: admin',
        'next'          => 'Now log in with password: admin'
    ]);
}

// ── LOGIN ──────────────────────────────────────────────────
if ($action === 'admin_login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $db    = getDB();
    $stmt  = $db->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin) {
        $stored   = $admin['password'];
        $hashInfo = password_get_info($stored);
        $isHash   = ($hashInfo['algo'] !== 0 && $hashInfo['algo'] !== null);
        $valid    = $isHash ? password_verify($pass, $stored) : ($pass === $stored);

        if (!$isHash && $valid) {
            $newHash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare('UPDATE admins SET password=? WHERE id=?')->execute([$newHash, $admin['id']]);
        }

        if ($valid) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            auditLog($db, $admin['name'], 'Login', '', 'Admin logged in');
            jsonOut(['ok' => true, 'name' => $admin['name']]);
        }
    }
    jsonOut(['ok' => false, 'msg' => 'Invalid email or password.']);
}

if ($action === 'admin_logout') {
    if (!empty($_SESSION['admin_name'])) {
        auditLog(getDB(), $_SESSION['admin_name'], 'Logout', '', 'Admin logged out');
    }
    session_destroy();
    jsonOut(['ok' => true]);
}

if (empty($_SESSION['admin_id'])) { jsonOut(['ok' => false, 'msg' => 'Unauthorized']); }

$db        = getDB();
$adminName = $_SESSION['admin_name'] ?? 'Admin';

// ── SUBJECTS ───────────────────────────────────────────────
if ($action === 'add_subject') {
    $name = trim($_POST['name'] ?? ''); $code = strtoupper(trim($_POST['code'] ?? '')); $duration = (int)($_POST['duration'] ?? 30);
    $numq = max(0, (int)($_POST['num_questions'] ?? 0));
    if (!$name || !$code) jsonOut(['ok' => false, 'msg' => 'Name and code are required.']);
    try {
        $db->prepare('INSERT INTO subjects (name,code,duration_minutes,num_questions) VALUES (?,?,?,?)')->execute([$name,$code,$duration,$numq]);
        auditLog($db, $adminName, 'Add Subject', "$name ($code)", "Duration: {$duration}min");
        jsonOut(['ok'=>true,'msg'=>"Subject '$name' added."]);
    }
    catch (PDOException $e) { jsonOut(['ok'=>false,'msg'=>'Code already exists: '.$e->getMessage()]); }
}

if ($action === 'get_subjects') { jsonOut(['ok'=>true,'data'=>$db->query('SELECT * FROM subjects ORDER BY name')->fetchAll()]); }

if ($action === 'delete_subject') {
    $id = (int)($_POST['id'] ?? 0);
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$id]); $s = $sub->fetch();
    $db->prepare('DELETE FROM subjects WHERE id=?')->execute([$id]);
    if ($s) auditLog($db, $adminName, 'Delete Subject', "{$s['name']} ({$s['code']})");
    jsonOut(['ok'=>true]);
}

if ($action === 'update_subject_duration') {
    $id = (int)($_POST['id']??0); $dur = (int)($_POST['duration']??30);
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$id]); $s = $sub->fetch();
    $db->prepare('UPDATE subjects SET duration_minutes=? WHERE id=?')->execute([$dur,$id]);
    if ($s) auditLog($db, $adminName, 'Update Subject Duration', "{$s['name']} ({$s['code']})", "New duration: {$dur}min");
    jsonOut(['ok'=>true]);
}

if ($action === 'update_num_questions') {
    $id   = (int)($_POST['id'] ?? 0);
    $numq = max(0, (int)($_POST['num_questions'] ?? 0));
    $count = $db->prepare('SELECT COUNT(*) FROM questions WHERE subject_id=?');
    $count->execute([$id]);
    $total = (int)$count->fetchColumn();
    if ($numq > $total && $total > 0) {
        jsonOut(['ok'=>false,'msg'=>"Only $total questions exist for this subject. Enter $total or less (0 = all)."]);
    }
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$id]); $s = $sub->fetch();
    $db->prepare('UPDATE subjects SET num_questions=? WHERE id=?')->execute([$numq, $id]);
    if ($s) auditLog($db, $adminName, 'Update Questions Per Quiz', "{$s['name']} ({$s['code']})", "Set to: $numq");
    jsonOut(['ok'=>true,'msg'=>'Updated successfully.']);
}

// ── QUESTIONS ──────────────────────────────────────────────
if ($action === 'upload_questions') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    if (!$subject_id) jsonOut(['ok'=>false,'msg'=>'Select a subject.']);
    if (empty($_FILES['csv_file']['tmp_name'])) jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$subject_id]); $s = $sub->fetch();
    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $header = fgetcsv($file);
    $headerMap = [];
    foreach ($header as $i => $h) { $headerMap[strtolower(trim($h))] = $i; }
    $required = ['question','opt1','opt2','opt3','opt4','correct','mark'];
    foreach ($required as $r) { if (!array_key_exists($r,$headerMap)) { fclose($file); jsonOut(['ok'=>false,'msg'=>"Missing column: '$r'"]); } }
    $inserted=0; $errors=[];
    $stmt=$db->prepare('INSERT INTO questions (subject_id,question,option1,option2,option3,option4,correct_option,mark) VALUES (?,?,?,?,?,?,?,?)');
    $row=2;
    while (($data=fgetcsv($file))!==false) {
        $q=trim($data[$headerMap['question']]??''); $o1=trim($data[$headerMap['opt1']]??''); $o2=trim($data[$headerMap['opt2']]??'');
        $o3=trim($data[$headerMap['opt3']]??''); $o4=trim($data[$headerMap['opt4']]??'');
        $cor=(int)trim($data[$headerMap['correct']]??0); $mk=(int)trim($data[$headerMap['mark']]??1);
        $e=[];
        if(!$q)$e[]='Question empty'; if(!$o1)$e[]='Opt1 empty'; if(!$o2)$e[]='Opt2 empty';
        if(!$o3)$e[]='Opt3 empty'; if(!$o4)$e[]='Opt4 empty';
        if($cor<1||$cor>4)$e[]='Correct must be 1-4'; if($mk<1)$e[]='Mark>=1';
        if($e){$errors[]="Row $row: ".implode('; ',$e);}
        else{try{$stmt->execute([$subject_id,$q,$o1,$o2,$o3,$o4,$cor,$mk]);$inserted++;}catch(PDOException $ex){$errors[]="Row $row: ".$ex->getMessage();}}
        $row++;
    }
    fclose($file);
    if ($inserted > 0 && $s) auditLog($db, $adminName, 'Upload Questions', "{$s['name']} ({$s['code']})", "$inserted question(s) added");
    jsonOut(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
}

if ($action === 'get_questions') {
    $sid=(int)($_GET['subject_id']??0);
    $stmt=$db->prepare('SELECT q.*, s.name as subject_name FROM questions q JOIN subjects s ON s.id=q.subject_id WHERE q.subject_id=? ORDER BY q.id');
    $stmt->execute([$sid]); jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

if ($action === 'delete_question') {
    $id = (int)($_POST['id']??0);
    $q = $db->prepare('SELECT q.question, s.name as sname, s.code FROM questions q JOIN subjects s ON s.id=q.subject_id WHERE q.id=?');
    $q->execute([$id]); $row = $q->fetch();
    $db->prepare('DELETE FROM questions WHERE id=?')->execute([$id]);
    if ($row) auditLog($db, $adminName, 'Delete Question', "{$row['sname']} ({$row['code']})", mb_strimwidth($row['question'],0,80,'…'));
    jsonOut(['ok'=>true]);
}

if ($action === 'update_question') {
    $id=(int)($_POST['id']??0); $q=trim($_POST['question']??''); $o1=trim($_POST['option1']??''); $o2=trim($_POST['option2']??'');
    $o3=trim($_POST['option3']??''); $o4=trim($_POST['option4']??''); $cor=(int)($_POST['correct_option']??0); $mk=(int)($_POST['mark']??1);
    if(!$q||!$o1||!$o2||!$o3||!$o4||$cor<1||$cor>4||$mk<1) jsonOut(['ok'=>false,'msg'=>'All fields required.']);
    $db->prepare('UPDATE questions SET question=?,option1=?,option2=?,option3=?,option4=?,correct_option=?,mark=? WHERE id=?')->execute([$q,$o1,$o2,$o3,$o4,$cor,$mk,$id]);
    auditLog($db, $adminName, 'Edit Question', "Question #$id", mb_strimwidth($q,0,80,'…'));
    jsonOut(['ok'=>true]);
}

// ── STUDENTS ───────────────────────────────────────────────
if ($action === 'batch_register_students') {
    $subject_id=(int)($_POST['subject_id']??0);
    if(!$subject_id) jsonOut(['ok'=>false,'msg'=>'Select a subject.']);
    if(empty($_FILES['csv_file']['tmp_name'])) jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$subject_id]); $s = $sub->fetch();
    $file=fopen($_FILES['csv_file']['tmp_name'],'r'); fgetcsv($file);
    $inserted=0; $errors=[]; $row=2;
    $s1=$db->prepare('INSERT INTO students (admission_no,name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
    $s2=$db->prepare('INSERT IGNORE INTO enrollments (student_id,subject_id) VALUES (?,?)');
    $s3=$db->prepare('SELECT id FROM students WHERE admission_no=?');
    while(($data=fgetcsv($file))!==false){
        $adm=trim($data[0]??''); $name=trim($data[1]??'');
        if(!$adm||!$name){$errors[]="Row $row: empty";$row++;continue;}
        try{$s1->execute([$adm,$name]);$s3->execute([$adm]);$sid=$s3->fetchColumn();$s2->execute([$sid,$subject_id]);$inserted++;}
        catch(PDOException $e){$errors[]="Row $row: ".$e->getMessage();}
        $row++;
    }
    fclose($file);
    if ($inserted > 0 && $s) auditLog($db, $adminName, 'Batch Register Students', "{$s['name']} ({$s['code']})", "$inserted student(s) enrolled");
    jsonOut(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
}

if ($action === 'get_results') {
    $sid=(int)($_GET['subject_id']??0); $stid=(int)($_GET['student_id']??0);
    $sql='SELECT r.*, s.admission_no, s.name as student_name, sub.name as subject_name, sub.code FROM results r JOIN students s ON s.id=r.student_id JOIN subjects sub ON sub.id=r.subject_id WHERE 1=1';
    $params=[];
    if($sid){$sql.=' AND r.subject_id=?';$params[]=$sid;}
    if($stid){$sql.=' AND r.student_id=?';$params[]=$stid;}
    $sql.=' ORDER BY sub.name, s.name';
    $stmt=$db->prepare($sql); $stmt->execute($params);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

if ($action === 'get_students') { jsonOut(['ok'=>true,'data'=>$db->query('SELECT id,admission_no,name,email,created_at FROM students ORDER BY name')->fetchAll()]); }

if ($action === 'reset_student_password') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $newPass   = trim($_POST['new_password'] ?? '');
    if (!$studentId) jsonOut(['ok'=>false,'msg'=>'Invalid student.']);
    if (strlen($newPass) < 4) jsonOut(['ok'=>false,'msg'=>'Password must be at least 4 characters.']);
    $stu = $db->prepare('SELECT name,admission_no FROM students WHERE id=?'); $stu->execute([$studentId]); $st = $stu->fetch();
    $stmt = $db->prepare('UPDATE students SET password=?, must_reset_password=1 WHERE id=?');
    $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $studentId]);
    if ($stmt->rowCount() === 0) jsonOut(['ok'=>false,'msg'=>'Student not found.']);
    if ($st) auditLog($db, $adminName, 'Reset Student Password', "{$st['name']} ({$st['admission_no']})");
    jsonOut(['ok'=>true,'msg'=>'Password reset. Student will be prompted to change it on next login.']);
}

// ── LECTURERS ──────────────────────────────────────────────
if ($action === 'add_lecturer') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if (!$name||!$email||!$pass) jsonOut(['ok'=>false,'msg'=>'Name, email and password are required.']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonOut(['ok'=>false,'msg'=>'Invalid email address.']);
    if (strlen($pass) < 6) jsonOut(['ok'=>false,'msg'=>'Password must be at least 6 characters.']);
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    try {
        $db->prepare('INSERT INTO lecturers (name,email,department,password) VALUES (?,?,?,?)')->execute([$name,$email,$dept,$hash]);
        auditLog($db, $adminName, 'Add Lecturer', $name, "Email: $email, Dept: $dept");
        jsonOut(['ok'=>true,'msg'=>"Lecturer '$name' added successfully."]);
    } catch (PDOException $e) {
        jsonOut(['ok'=>false,'msg'=>'Email already exists or DB error.']);
    }
}

if ($action === 'get_lecturers') {
    $rows = $db->query(
        'SELECT l.*, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") as assigned_subjects
         FROM lecturers l
         LEFT JOIN lecturer_subjects ls ON ls.lecturer_id=l.id
         LEFT JOIN subjects s ON s.id=ls.subject_id
         GROUP BY l.id ORDER BY l.name'
    )->fetchAll();
    jsonOut(['ok'=>true,'data'=>$rows]);
}

if ($action === 'toggle_lecturer') {
    $id = (int)($_POST['id'] ?? 0);
    $lec = $db->prepare('SELECT name, is_active FROM lecturers WHERE id=?'); $lec->execute([$id]); $l = $lec->fetch();
    $db->prepare('UPDATE lecturers SET is_active = NOT is_active WHERE id=?')->execute([$id]);
    if ($l) {
        $newState = $l['is_active'] ? 'Disabled' : 'Enabled';
        auditLog($db, $adminName, "$newState Lecturer", $l['name']);
    }
    jsonOut(['ok'=>true]);
}

if ($action === 'delete_lecturer') {
    $id = (int)($_POST['id'] ?? 0);
    $lec = $db->prepare('SELECT name,email FROM lecturers WHERE id=?'); $lec->execute([$id]); $l = $lec->fetch();
    $db->prepare('DELETE FROM lecturers WHERE id=?')->execute([$id]);
    if ($l) auditLog($db, $adminName, 'Delete Lecturer', $l['name'], "Email: {$l['email']}");
    jsonOut(['ok'=>true]);
}

if ($action === 'reset_lecturer_password') {
    $id      = (int)($_POST['id'] ?? 0);
    $newPass = trim($_POST['new_password'] ?? '');
    if (!$id) jsonOut(['ok'=>false,'msg'=>'Invalid lecturer.']);
    if (strlen($newPass) < 4) jsonOut(['ok'=>false,'msg'=>'Password must be at least 4 characters.']);
    $lec = $db->prepare('SELECT name FROM lecturers WHERE id=?'); $lec->execute([$id]); $l = $lec->fetch();
    $db->prepare('UPDATE lecturers SET password=? WHERE id=?')->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
    if ($l) auditLog($db, $adminName, 'Reset Lecturer Password', $l['name']);
    jsonOut(['ok'=>true,'msg'=>'Lecturer password reset successfully.']);
}

if ($action === 'assign_subjects') {
    $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
    $subjectIds = json_decode($_POST['subject_ids'] ?? '[]', true);
    if (!$lecturerId) jsonOut(['ok'=>false,'msg'=>'Invalid lecturer.']);
    $lec = $db->prepare('SELECT name FROM lecturers WHERE id=?'); $lec->execute([$lecturerId]); $l = $lec->fetch();
    $db->prepare('DELETE FROM lecturer_subjects WHERE lecturer_id=?')->execute([$lecturerId]);
    $stmt = $db->prepare('INSERT IGNORE INTO lecturer_subjects (lecturer_id,subject_id) VALUES (?,?)');
    foreach ($subjectIds as $sid) { $stmt->execute([$lecturerId, (int)$sid]); }
    if ($l) auditLog($db, $adminName, 'Assign Subjects', $l['name'], count($subjectIds).' subject(s) assigned');
    jsonOut(['ok'=>true,'msg'=>'Subject assignments updated.']);
}

if ($action === 'get_lecturer_subjects') {
    $lecturerId = (int)($_GET['lecturer_id'] ?? 0);
    $stmt = $db->prepare('SELECT subject_id FROM lecturer_subjects WHERE lecturer_id=?');
    $stmt->execute([$lecturerId]);
    jsonOut(['ok'=>true,'data'=>array_column($stmt->fetchAll(),'subject_id')]);
}

// ── AUDIT LOG ──────────────────────────────────────────────
if ($action === 'get_audit_logs') {
    $limit    = min((int)($_GET['limit'] ?? 200), 500);
    $offset   = max((int)($_GET['offset'] ?? 0), 0);
    $search   = trim($_GET['search'] ?? '');
    $filterAction = trim($_GET['filter_action'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo   = trim($_GET['date_to'] ?? '');

    $sql = 'SELECT * FROM audit_logs WHERE 1=1';
    $params = [];
    if ($search) { $sql .= ' AND (actor_name LIKE ? OR target LIKE ? OR detail LIKE ?)'; $like="%$search%"; $params=array_merge($params,[$like,$like,$like]); }
    if ($filterAction) { $sql .= ' AND action=?'; $params[] = $filterAction; }
    if ($dateFrom) { $sql .= ' AND DATE(created_at)>=?'; $params[] = $dateFrom; }
    if ($dateTo)   { $sql .= ' AND DATE(created_at)<=?'; $params[] = $dateTo; }
    $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit; $params[] = $offset;

    $stmt = $db->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $countSql = 'SELECT COUNT(*) FROM audit_logs WHERE 1=1';
    $countParams = array_slice($params, 0, -2);
    $countStmt = $db->prepare(str_replace('SELECT *','SELECT COUNT(*)',explode('ORDER BY',$sql)[0]));
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    $actions = $db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    jsonOut(['ok'=>true,'data'=>$rows,'total'=>$total,'actions'=>$actions]);
}

jsonOut(['ok'=>false,'msg'=>'Unknown action']);
