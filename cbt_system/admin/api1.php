<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$action = $_REQUEST['action'] ?? '';

// ── DEBUG: resets password and shows DB state ──────────────
// Visit: http://localhost/cbt_system/admin/api.php?action=debug_login
if ($action === 'debug_login') {
    $db = getDB();
    $db->prepare("UPDATE admins SET password='admin' WHERE email='baba.aminu@udusok.edu.ng'")->execute();
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
            jsonOut(['ok' => true, 'name' => $admin['name']]);
        }
    }
    jsonOut(['ok' => false, 'msg' => 'Invalid email or password.']);
}

if ($action === 'admin_logout') { session_destroy(); jsonOut(['ok' => true]); }

if (empty($_SESSION['admin_id'])) { jsonOut(['ok' => false, 'msg' => 'Unauthorized']); }

$db = getDB();

if ($action === 'add_subject') {
    $name = trim($_POST['name'] ?? ''); $code = strtoupper(trim($_POST['code'] ?? '')); $duration = (int)($_POST['duration'] ?? 30);
    $numq = max(0, (int)($_POST['num_questions'] ?? 0));
    if (!$name || !$code) jsonOut(['ok' => false, 'msg' => 'Name and code are required.']);
    try { $db->prepare('INSERT INTO subjects (name,code,duration_minutes,num_questions) VALUES (?,?,?,?)')->execute([$name,$code,$duration,$numq]); jsonOut(['ok'=>true,'msg'=>"Subject '$name' added."]); }
    catch (PDOException $e) { jsonOut(['ok'=>false,'msg'=>'Code already exists: '.$e->getMessage()]); }
}

if ($action === 'get_subjects') { jsonOut(['ok'=>true,'data'=>$db->query('SELECT * FROM subjects ORDER BY name')->fetchAll()]); }

if ($action === 'delete_subject') { $db->prepare('DELETE FROM subjects WHERE id=?')->execute([(int)($_POST['id']??0)]); jsonOut(['ok'=>true]); }

if ($action === 'update_subject_duration') { $db->prepare('UPDATE subjects SET duration_minutes=? WHERE id=?')->execute([(int)($_POST['duration']??30),(int)($_POST['id']??0)]); jsonOut(['ok'=>true]); }

if ($action === 'update_num_questions') {
    $id   = (int)($_POST['id'] ?? 0);
    $numq = max(0, (int)($_POST['num_questions'] ?? 0));
    // Validate against actual question count for this subject
    $count = $db->prepare('SELECT COUNT(*) FROM questions WHERE subject_id=?');
    $count->execute([$id]);
    $total = (int)$count->fetchColumn();
    if ($numq > $total && $total > 0) {
        jsonOut(['ok'=>false,'msg'=>"Only $total questions exist for this subject. Enter $total or less (0 = all)."]);
    }
    $db->prepare('UPDATE subjects SET num_questions=? WHERE id=?')->execute([$numq, $id]);
    jsonOut(['ok'=>true,'msg'=>'Updated successfully.']);
}

if ($action === 'upload_questions') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    if (!$subject_id) jsonOut(['ok'=>false,'msg'=>'Select a subject.']);
    if (empty($_FILES['csv_file']['tmp_name'])) jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);
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
    jsonOut(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
}

if ($action === 'get_questions') {
    $sid=(int)($_GET['subject_id']??0);
    $stmt=$db->prepare('SELECT q.*, s.name as subject_name FROM questions q JOIN subjects s ON s.id=q.subject_id WHERE q.subject_id=? ORDER BY q.id');
    $stmt->execute([$sid]); jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

if ($action === 'delete_question') { $db->prepare('DELETE FROM questions WHERE id=?')->execute([(int)($_POST['id']??0)]); jsonOut(['ok'=>true]); }

if ($action === 'update_question') {
    $id=(int)($_POST['id']??0); $q=trim($_POST['question']??''); $o1=trim($_POST['option1']??''); $o2=trim($_POST['option2']??'');
    $o3=trim($_POST['option3']??''); $o4=trim($_POST['option4']??''); $cor=(int)($_POST['correct_option']??0); $mk=(int)($_POST['mark']??1);
    if(!$q||!$o1||!$o2||!$o3||!$o4||$cor<1||$cor>4||$mk<1) jsonOut(['ok'=>false,'msg'=>'All fields required.']);
    $db->prepare('UPDATE questions SET question=?,option1=?,option2=?,option3=?,option4=?,correct_option=?,mark=? WHERE id=?')->execute([$q,$o1,$o2,$o3,$o4,$cor,$mk,$id]);
    jsonOut(['ok'=>true]);
}

if ($action === 'batch_register_students') {
    $subject_id=(int)($_POST['subject_id']??0);
    if(!$subject_id) jsonOut(['ok'=>false,'msg'=>'Select a subject.']);
    if(empty($_FILES['csv_file']['tmp_name'])) jsonOut(['ok'=>false,'msg'=>'No file uploaded.']);
    $file=fopen($_FILES['csv_file']['tmp_name'],'r'); fgetcsv($file);
    $inserted=0; $errors=[]; $row=2;
    // $s1=$db->prepare('INSERT INTO students (admission_no,name) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
    $s1=$db->prepare('
    INSERT INTO students (admission_no, name, passport) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        passport = VALUES(passport)
');
    // $passport = 'passports/' . $adm . '.jpg';
    $s2=$db->prepare('INSERT IGNORE INTO enrollments (student_id,subject_id) VALUES (?,?)');
    $s3=$db->prepare('SELECT id FROM students WHERE admission_no=?');
    // while(($data=fgetcsv($file))!==false){
    //     $adm=trim($data[0]??''); $name=trim($data[1]??'');
    //     if(!$adm||!$name){$errors[]="Row $row: empty";$row++;continue;}
    //     try{$s1->execute([$adm,$name]);$s3->execute([$adm]);$sid=$s3->fetchColumn();$s2->execute([$sid,$subject_id]);$inserted++;}
    //     catch(PDOException $e){$errors[]="Row $row: ".$e->getMessage();}
    //     $row++;
    // }
while(($data=fgetcsv($file))!==false){
    $adm = trim($data[0] ?? '');
    $name = trim($data[1] ?? '');

    if(!$adm || !$name){
        $errors[] = "Row $row: empty";
        $row++;
        continue;
    }

    // 👉 Generate passport automatically
    $passport = $adm . '.jpg';

    try {
        $s1->execute([$adm, $name, $passport]);

        $s3->execute([$adm]);
        $sid = $s3->fetchColumn();

        $s2->execute([$sid, $subject_id]);
        $inserted++;
    } catch(PDOException $e){
        $errors[] = "Row $row: " . $e->getMessage();
    }

    $row++;
}

    fclose($file);
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

if ($action === 'get_students') { jsonOut(['ok'=>true,'data'=>$db->query('SELECT id,admission_no,name FROM students ORDER BY name')->fetchAll()]); }

jsonOut(['ok'=>false,'msg'=>'Unknown action']);
