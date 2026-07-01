<?php
// ============================================================
//  lecturer/api.php  — Lecturer Portal Back-end
// ============================================================
session_start();
require_once __DIR__ . '/../includes/db.php';

$action = $_REQUEST['action'] ?? '';
$db     = getDB();

// ── PUBLIC: LOGIN ──────────────────────────────────────────
if ($action === 'lecturer_login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt  = $db->prepare('SELECT * FROM lecturers WHERE email=? AND is_active=1');
    $stmt->execute([$email]);
    $lec = $stmt->fetch();
    if ($lec && password_verify($pass, $lec['password'])) {
        $_SESSION['lecturer_id']   = $lec['id'];
        $_SESSION['lecturer_name'] = $lec['name'];
        auditLog($db, $lec['name'], 'Login', '', 'Lecturer logged in', 'lecturer');
        jsonOut(['ok'=>true,'name'=>$lec['name']]);
    }
    jsonOut(['ok'=>false,'msg'=>'Invalid email or password, or account disabled.']);
}

// ── PUBLIC: LOGOUT ─────────────────────────────────────────
if ($action === 'lecturer_logout') {
    if (!empty($_SESSION['lecturer_name'])) {
        auditLog($db, $_SESSION['lecturer_name'], 'Logout', '', 'Lecturer logged out', 'lecturer');
    }
    session_destroy();
    jsonOut(['ok'=>true]);
}

// ── GUARD ──────────────────────────────────────────────────
if (empty($_SESSION['lecturer_id'])) jsonOut(['ok'=>false,'msg'=>'Not authenticated.']);
$lecturerId  = (int)$_SESSION['lecturer_id'];
$lectureName = $_SESSION['lecturer_name'] ?? 'Lecturer';

// Helper: verify this lecturer is assigned to a subject
function isAssigned(PDO $db, int $lecturerId, int $subjectId): bool {
    $s = $db->prepare('SELECT id FROM lecturer_subjects WHERE lecturer_id=? AND subject_id=?');
    $s->execute([$lecturerId, $subjectId]);
    return (bool)$s->fetch();
}

// ── MY ASSIGNED SUBJECTS ───────────────────────────────────
if ($action === 'my_assigned_subjects') {
    $stmt = $db->prepare(
        'SELECT s.id, s.name, s.code, s.duration_minutes, s.num_questions
         FROM subjects s
         JOIN lecturer_subjects ls ON ls.subject_id=s.id
         WHERE ls.lecturer_id=? ORDER BY s.name'
    );
    $stmt->execute([$lecturerId]);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

// ── UPLOAD QUESTIONS ───────────────────────────────────────
if ($action === 'lecturer_upload_questions') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    if (!$subjectId || !isAssigned($db, $lecturerId, $subjectId))
        jsonOut(['ok'=>false,'msg'=>'You are not assigned to this subject.']);
    if (empty($_FILES['csv_file']['tmp_name']))
        jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);

    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$subjectId]); $s = $sub->fetch();
    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $header = fgetcsv($file);
    $headerMap = [];
    foreach ($header as $i => $h) { $headerMap[strtolower(trim($h))] = $i; }
    $required = ['question','opt1','opt2','opt3','opt4','correct','mark'];
    foreach ($required as $r) {
        if (!array_key_exists($r, $headerMap)) {
            fclose($file);
            jsonOut(['ok'=>false,'msg'=>"Missing column: '$r'. Required: ".implode(', ',$required)]);
        }
    }
    $inserted=0; $errors=[];
    $stmt=$db->prepare('INSERT INTO questions (subject_id,question,option1,option2,option3,option4,correct_option,mark) VALUES (?,?,?,?,?,?,?,?)');
    $row=2;
    while (($data=fgetcsv($file))!==false) {
        $q=trim($data[$headerMap['question']]??''); $o1=trim($data[$headerMap['opt1']]??'');
        $o2=trim($data[$headerMap['opt2']]??''); $o3=trim($data[$headerMap['opt3']]??'');
        $o4=trim($data[$headerMap['opt4']]??''); $cor=(int)trim($data[$headerMap['correct']]??0);
        $mk=(int)trim($data[$headerMap['mark']]??1);
        $e=[];
        if(!$q)$e[]='Question empty'; if(!$o1)$e[]='Opt1 empty'; if(!$o2)$e[]='Opt2 empty';
        if(!$o3)$e[]='Opt3 empty'; if(!$o4)$e[]='Opt4 empty';
        if($cor<1||$cor>4)$e[]='Correct must be 1-4'; if($mk<1)$e[]='Mark>=1';
        if($e){$errors[]="Row $row: ".implode('; ',$e);}
        else{try{$stmt->execute([$subjectId,$q,$o1,$o2,$o3,$o4,$cor,$mk]);$inserted++;}
             catch(PDOException $ex){$errors[]="Row $row: ".$ex->getMessage();}}
        $row++;
    }
    fclose($file);
    if ($inserted > 0 && $s) auditLog($db, $lectureName, 'Upload Questions', "{$s['name']} ({$s['code']})", "$inserted question(s) added", 'lecturer');
    jsonOut(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
}

// ── GET QUESTIONS ──────────────────────────────────────────
if ($action === 'lecturer_get_questions') {
    $subjectIds = array_filter(array_map('intval', explode(',', $_GET['subject_ids'] ?? '')));
    if (empty($subjectIds)) jsonOut(['ok'=>true,'data'=>[]]);
    $assigned = $db->prepare('SELECT subject_id FROM lecturer_subjects WHERE lecturer_id=?');
    $assigned->execute([$lecturerId]);
    $myIds = array_column($assigned->fetchAll(), 'subject_id');
    $allowed = array_intersect($subjectIds, $myIds);
    if (empty($allowed)) jsonOut(['ok'=>true,'data'=>[]]);
    $ph = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $db->prepare("SELECT q.*, s.name as subject_name FROM questions q JOIN subjects s ON s.id=q.subject_id WHERE q.subject_id IN ($ph) ORDER BY q.subject_id, q.id");
    $stmt->execute(array_values($allowed));
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

// ── UPDATE QUESTION ────────────────────────────────────────
if ($action === 'lecturer_update_question') {
    $id  = (int)($_POST['id'] ?? 0);
    $check = $db->prepare('SELECT q.subject_id, s.name as sname, s.code FROM questions q JOIN lecturer_subjects ls ON ls.subject_id=q.subject_id JOIN subjects s ON s.id=q.subject_id WHERE q.id=? AND ls.lecturer_id=?');
    $check->execute([$id, $lecturerId]);
    $row = $check->fetch();
    if (!$row) jsonOut(['ok'=>false,'msg'=>'Question not found or not your subject.']);
    $q=trim($_POST['question']??''); $o1=trim($_POST['option1']??''); $o2=trim($_POST['option2']??'');
    $o3=trim($_POST['option3']??''); $o4=trim($_POST['option4']??'');
    $cor=(int)($_POST['correct_option']??0); $mk=(int)($_POST['mark']??1);
    if(!$q||!$o1||!$o2||!$o3||!$o4||$cor<1||$cor>4||$mk<1) jsonOut(['ok'=>false,'msg'=>'All fields required.']);
    $db->prepare('UPDATE questions SET question=?,option1=?,option2=?,option3=?,option4=?,correct_option=?,mark=? WHERE id=?')
       ->execute([$q,$o1,$o2,$o3,$o4,$cor,$mk,$id]);
    auditLog($db, $lectureName, 'Edit Question', "{$row['sname']} ({$row['code']})", mb_strimwidth($q,0,80,'…'), 'lecturer');
    jsonOut(['ok'=>true]);
}

// ── DELETE QUESTION ────────────────────────────────────────
if ($action === 'lecturer_delete_question') {
    $id = (int)($_POST['id'] ?? 0);
    $check = $db->prepare('SELECT q.question, s.name as sname, s.code FROM questions q JOIN lecturer_subjects ls ON ls.subject_id=q.subject_id JOIN subjects s ON s.id=q.subject_id WHERE q.id=? AND ls.lecturer_id=?');
    $check->execute([$id, $lecturerId]);
    $row = $check->fetch();
    if (!$row) jsonOut(['ok'=>false,'msg'=>'Question not found or not your subject.']);
    $db->prepare('DELETE FROM questions WHERE id=?')->execute([$id]);
    auditLog($db, $lectureName, 'Delete Question', "{$row['sname']} ({$row['code']})", mb_strimwidth($row['question'],0,80,'…'), 'lecturer');
    jsonOut(['ok'=>true]);
}

// ── BATCH REGISTER STUDENTS ────────────────────────────────
if ($action === 'lecturer_batch_register') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    if (!$subjectId || !isAssigned($db, $lecturerId, $subjectId))
        jsonOut(['ok'=>false,'msg'=>'You are not assigned to this subject.']);
    if (empty($_FILES['csv_file']['tmp_name']))
        jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);
    $sub = $db->prepare('SELECT name,code FROM subjects WHERE id=?'); $sub->execute([$subjectId]); $s = $sub->fetch();
    $file=fopen($_FILES['csv_file']['tmp_name'],'r'); fgetcsv($file);
    $inserted=0; $errors=[]; $row=2;
    $s1=$db->prepare('INSERT INTO students (admission_no,name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
    $s2=$db->prepare('INSERT IGNORE INTO enrollments (student_id,subject_id) VALUES (?,?)');
    $s3=$db->prepare('SELECT id FROM students WHERE admission_no=?');
    while(($data=fgetcsv($file))!==false){
        $adm=trim($data[0]??''); $name=trim($data[1]??'');
        if(!$adm||!$name){$errors[]="Row $row: empty";$row++;continue;}
        try{$s1->execute([$adm,$name]);$s3->execute([$adm]);$sid=$s3->fetchColumn();$s2->execute([$sid,$subjectId]);$inserted++;}
        catch(PDOException $e){$errors[]="Row $row: ".$e->getMessage();}
        $row++;
    }
    fclose($file);
    if ($inserted > 0 && $s) auditLog($db, $lectureName, 'Batch Register Students', "{$s['name']} ({$s['code']})", "$inserted student(s) enrolled", 'lecturer');
    jsonOut(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
}

// ── ENROLLED STUDENTS LIST ─────────────────────────────────
if ($action === 'get_enrolled_students') {
    $subjectId = (int)($_GET['subject_id'] ?? 0);
    if ($subjectId && !isAssigned($db, $lecturerId, $subjectId))
        jsonOut(['ok'=>false,'msg'=>'Not your subject.']);
    if ($subjectId) {
        $stmt = $db->prepare(
            'SELECT s.id, s.name, s.admission_no, sub.name AS subject_name
             FROM students s
             JOIN enrollments e ON e.student_id=s.id
             JOIN subjects sub ON sub.id=e.subject_id
             WHERE e.subject_id=?
             ORDER BY s.name'
        );
        $stmt->execute([$subjectId]);
    } else {
        $stmt = $db->prepare(
            'SELECT s.id, s.name, s.admission_no, sub.name AS subject_name
             FROM students s
             JOIN enrollments e ON e.student_id=s.id
             JOIN subjects sub ON sub.id=e.subject_id
             JOIN lecturer_subjects ls ON ls.subject_id=e.subject_id
             WHERE ls.lecturer_id=?
             ORDER BY s.name'
        );
        $stmt->execute([$lecturerId]);
    }
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

// ── RESET STUDENT PASSWORD ─────────────────────────────────
if ($action === 'reset_student_password') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $newPass   = trim($_POST['new_password'] ?? '');
    if (!$studentId) jsonOut(['ok'=>false,'msg'=>'Invalid student.']);
    if (strlen($newPass) < 4) jsonOut(['ok'=>false,'msg'=>'Password must be at least 4 characters.']);
    $check = $db->prepare(
        'SELECT e.student_id, s.name, s.admission_no FROM enrollments e
         JOIN lecturer_subjects ls ON ls.subject_id = e.subject_id
         JOIN students s ON s.id = e.student_id
         WHERE e.student_id=? AND ls.lecturer_id=? LIMIT 1'
    );
    $check->execute([$studentId, $lecturerId]);
    $stu = $check->fetch();
    if (!$stu) jsonOut(['ok'=>false,'msg'=>'Student not in your subjects.']);
    $db->prepare('UPDATE students SET password=?, must_reset_password=1 WHERE id=?')
       ->execute([password_hash($newPass, PASSWORD_DEFAULT), $studentId]);
    auditLog($db, $lectureName, 'Reset Student Password', "{$stu['name']} ({$stu['admission_no']})", '', 'lecturer');
    jsonOut(['ok'=>true,'msg'=>'Password reset. Student will be prompted to change it on next login.']);
}

jsonOut(['ok'=>false,'msg'=>'Unknown action']);
