<?php
// ============================================================
//  student/api.php
// ============================================================
session_start();
require_once __DIR__ . '/../includes/db.php';

$action = $_REQUEST['action'] ?? '';
$db     = getDB();

// ── PUBLIC: REGISTER ───────────────────────────────────────
if ($action === 'register') {
    $adm   = trim($_POST['admission_no'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (!$adm||!$name||!$email||!$pass) jsonOut(['ok'=>false,'msg'=>'All fields are required.']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonOut(['ok'=>false,'msg'=>'Invalid email address.']);
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Handle passport photo upload
    $passportFile = null;
    if (!empty($_FILES['passport']['tmp_name'])) {
        $file     = $_FILES['passport'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/jpg','image/webp'];
        $maxSize  = 2 * 1024 * 1024; // 2MB
        if (!in_array($file['type'], $allowed))
            jsonOut(['ok'=>false,'msg'=>'Photo must be a JPG, PNG, GIF or WEBP image.']);
        if ($file['size'] > $maxSize)
            jsonOut(['ok'=>false,'msg'=>'Photo must be smaller than 2MB.']);
        $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
        $passportFile = preg_replace('/[^a-z0-9]/i','_', $adm) . '_' . time() . '.' . $ext;
        $uploadDir   = __DIR__ . '/../../passports/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $passportFile))
            jsonOut(['ok'=>false,'msg'=>'Failed to save photo. Check folder permissions.']);
    }

    try {
        $db->prepare('INSERT INTO students (admission_no,name,email,password,passport) VALUES (?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE email=VALUES(email),password=VALUES(password),
                      name=VALUES(name),passport=COALESCE(VALUES(passport),passport)')
           ->execute([$adm,$name,$email,$hash,$passportFile]);
        jsonOut(['ok'=>true,'msg'=>'Registration successful. You can now log in.']);
    } catch (PDOException $e) {
        jsonOut(['ok'=>false,'msg'=>'Email already in use or DB error.']);
    }
}

// ── PUBLIC: LOGIN ──────────────────────────────────────────
if ($action === 'login') {
    $adm  = trim($_POST['admission_no'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $db->prepare('SELECT * FROM students WHERE admission_no=?');
    $stmt->execute([$adm]);
    $student = $stmt->fetch();
    if ($student && $student['password'] && password_verify($pass, $student['password'])) {
        $_SESSION['student_id']       = $student['id'];
        $_SESSION['student_name']     = $student['name'];
        $_SESSION['student_adm']      = $student['admission_no'];
        $_SESSION['student_passport'] = $student['passport'] ?? null;
        jsonOut(['ok'=>true,'name'=>$student['name'],'adm'=>$student['admission_no'],'passport'=>$student['passport']??null]);
    }
    jsonOut(['ok'=>false,'msg'=>'Invalid admission number or password.']);
}

// ── PUBLIC: LOGOUT ─────────────────────────────────────────
if ($action === 'logout') { session_destroy(); jsonOut(['ok'=>true]); }

// ── GUARD ──────────────────────────────────────────────────
if (empty($_SESSION['student_id'])) jsonOut(['ok'=>false,'msg'=>'Not authenticated.']);
$studentId = (int)$_SESSION['student_id'];

// ── MY SUBJECTS ────────────────────────────────────────────
if ($action === 'my_subjects') {
    $stmt = $db->prepare(
        'SELECT s.id, s.name, s.code, s.duration_minutes,
                (SELECT id     FROM quiz_sessions qs WHERE qs.student_id=? AND qs.subject_id=s.id LIMIT 1) as session_id,
                (SELECT status FROM quiz_sessions qs WHERE qs.student_id=? AND qs.subject_id=s.id LIMIT 1) as attempt_status
         FROM subjects s
         JOIN enrollments e ON e.subject_id=s.id
         WHERE e.student_id=? ORDER BY s.name'
    );
    $stmt->execute([$studentId, $studentId, $studentId]);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

// ── DEBUG: reset all quiz sessions for current student ─────
// Visit: http://localhost/cbt_system/student/api.php?action=reset_my_sessions
// Only works while logged in
if ($action === 'reset_my_sessions') {
    $db->prepare('DELETE FROM quiz_sessions WHERE student_id=?')->execute([$studentId]);
    $db->prepare('DELETE FROM results WHERE student_id=?')->execute([$studentId]);
    jsonOut(['ok'=>true,'msg'=>'All your quiz sessions and results have been cleared. You can now retake quizzes.']);
}

// ── START QUIZ ─────────────────────────────────────────────
if ($action === 'start_quiz') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);

    // Must be enrolled
    $enr = $db->prepare('SELECT id FROM enrollments WHERE student_id=? AND subject_id=?');
    $enr->execute([$studentId, $subjectId]);
    if (!$enr->fetch()) jsonOut(['ok'=>false,'msg'=>'You are not enrolled in this subject.']);

    // Check for existing session
    $ses = $db->prepare('SELECT * FROM quiz_sessions WHERE student_id=? AND subject_id=?');
    $ses->execute([$studentId, $subjectId]);
    $existing = $ses->fetch();

    // Block only if properly submitted (has a result saved)
    if ($existing && $existing['status'] !== 'in_progress') {
        $hasResult = $db->prepare('SELECT id FROM results WHERE student_id=? AND subject_id=?');
        $hasResult->execute([$studentId, $subjectId]);
        if ($hasResult->fetch()) {
            jsonOut(['ok'=>false,'msg'=>'You have already completed this subject. Check your results tab.']);
        }
        // Orphaned/broken session with no result — delete it and start fresh
        $db->prepare('DELETE FROM student_answers WHERE session_id=?')->execute([$existing['id']]);
        $db->prepare('DELETE FROM quiz_sessions WHERE id=?')->execute([$existing['id']]);
        $existing = null;
    }

    // Get subject info
    $sub = $db->prepare('SELECT * FROM subjects WHERE id=?');
    $sub->execute([$subjectId]);
    $subject = $sub->fetch();
    if (!$subject) jsonOut(['ok'=>false,'msg'=>'Subject not found.']);

    // Get questions — respect num_questions limit set by admin
    $qStmt = $db->prepare('SELECT * FROM questions WHERE subject_id=?');
    $qStmt->execute([$subjectId]);
    $allQuestions = $qStmt->fetchAll();
    if (empty($allQuestions)) jsonOut(['ok'=>false,'msg'=>'No questions found for this subject. Please contact admin.']);

    // Randomly select the required number of questions
    $limit = (int)$subject['num_questions'];
    if ($limit > 0 && $limit < count($allQuestions)) {
        shuffle($allQuestions);
        $questions = array_slice($allQuestions, 0, $limit);
    } else {
        $questions = $allQuestions; // 0 means use all
    }

    $timeLimitSeconds = $subject['duration_minutes'] * 60;

    if (!$existing) {
        // Brand new session — record start time as NOW()
        $db->prepare('INSERT INTO quiz_sessions (student_id,subject_id,time_limit_seconds,status,started_at) VALUES (?,?,?,?,NOW())')
           ->execute([$studentId, $subjectId, $timeLimitSeconds, 'in_progress']);
        $sessionId = (int)$db->lastInsertId();
        $remaining = $timeLimitSeconds; // full time for new session
    } else {
        // Resume in_progress session — compute how much time is left
        $sessionId = (int)$existing['id'];
        $elapsed   = time() - strtotime($existing['started_at']);
        $remaining = max(30, $timeLimitSeconds - $elapsed); // minimum 30s so page can load
    }

    // Shuffle question order
    shuffle($questions);

    $clientData = [];
    foreach ($questions as $q) {
        $optMap    = [1=>$q['option1'], 2=>$q['option2'], 3=>$q['option3'], 4=>$q['option4']];
        $positions = [1,2,3,4];
        shuffle($positions);

        $opts = [];
        foreach ($positions as $newPos => $origKey) {
            $opts[] = [
                'pos'  => $newPos + 1,
                'text' => $optMap[$origKey],
                'key'  => $origKey,
            ];
        }

        $clientData[] = [
            'id'      => (int)$q['id'],
            'text'    => $q['question'],
            'mark'    => (int)$q['mark'],
            'options' => $opts,
        ];
    }

    jsonOut([
        'ok'                => true,
        'session_id'        => $sessionId,
        'subject'           => $subject,
        'questions'         => $clientData,
        'remaining_seconds' => $remaining,
    ]);
}

// ── SUBMIT QUIZ ────────────────────────────────────────────
if ($action === 'submit_quiz') {
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $answers   = $_POST['answers'] ?? '{}';
    $auto      = ($_POST['auto'] ?? '0') === '1';

    // Verify session belongs to this student
    $ses = $db->prepare('SELECT * FROM quiz_sessions WHERE id=? AND student_id=?');
    $ses->execute([$sessionId, $studentId]);
    $session = $ses->fetch();
    if (!$session) jsonOut(['ok'=>false,'msg'=>'Session not found.']);
    if ($session['status'] !== 'in_progress') {
        // Already submitted — check if result exists and return it
        $res = $db->prepare('SELECT * FROM results WHERE session_id=?');
        $res->execute([$sessionId]);
        $result = $res->fetch();
        if ($result) {
            jsonOut(['ok'=>true,'score'=>(int)$result['score'],'total'=>(int)$result['total_marks'],
                     'percentage'=>(float)$result['percentage'],'grade'=>$result['grade'],'already_done'=>true]);
        }
        jsonOut(['ok'=>false,'msg'=>'Session already closed.']);
    }

    // Grade only the questions the student was actually given (keys from their answers)
    // The client sends { question_id: chosen_original_key } only for served questions
    $answersMap = json_decode($answers, true) ?? [];

    // Fetch only the questions that were served (IDs present in answers map)
    // Also fetch any question the student skipped — those IDs were sent as null
    // We use the answered question IDs as the served set
    $servedIds = array_keys($answersMap);

    if (empty($servedIds)) {
        // Auto-submit with no answers — grade as zero but use question count from subject
        $subjectRow = $db->prepare('SELECT num_questions FROM subjects WHERE id=?');
        $subjectRow->execute([$session['subject_id']]);
        $subjectData = $subjectRow->fetch();
        $numAsked = (int)$subjectData['num_questions'];
        if ($numAsked === 0) {
            $countRow = $db->prepare('SELECT COUNT(*) FROM questions WHERE subject_id=?');
            $countRow->execute([$session['subject_id']]);
            $numAsked = (int)$countRow->fetchColumn();
        }
        $pct   = 0.00;
        $grade = computeGrade(0);
        $status = $auto ? 'auto_submitted' : 'submitted';
        $db->prepare('UPDATE quiz_sessions SET submitted_at=NOW(), status=? WHERE id=?')->execute([$status, $sessionId]);
        try {
            $db->prepare('INSERT INTO results (student_id,subject_id,session_id,score,total_marks,percentage,grade)
                          VALUES (?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE score=0,total_marks=VALUES(total_marks),percentage=0.00,grade=VALUES(grade),submitted_at=NOW()')
               ->execute([$studentId, $session['subject_id'], $sessionId, 0, $numAsked, 0.00, $grade]);
        } catch(Exception $e) {}
        jsonOut(['ok'=>true,'score'=>0,'correct'=>0,'total_questions'=>$numAsked,'percentage'=>0.00,'grade'=>$grade]);
    }

    // Fetch correct answers only for served question IDs
    $placeholders = implode(',', array_fill(0, count($servedIds), '?'));
    $qStmt = $db->prepare("SELECT id, correct_option, mark FROM questions WHERE id IN ($placeholders)");
    $qStmt->execute($servedIds);
    $dbQuestions = $qStmt->fetchAll();

    $correctCount = 0;   // number of correctly answered questions
    $totalMarks   = 0;   // sum of marks for all served questions
    $earnedMarks  = 0;   // sum of marks earned

    $stmtAns = $db->prepare(
        'INSERT INTO student_answers (session_id,question_id,chosen_option,is_correct)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE chosen_option=VALUES(chosen_option),is_correct=VALUES(is_correct)'
    );

    foreach ($dbQuestions as $q) {
        $qid     = (int)$q['id'];
        $correct = (int)$q['correct_option'];
        $mark    = (int)$q['mark'];
        $chosen  = isset($answersMap[$qid]) ? (int)$answersMap[$qid] : null;
        $isRight = ($chosen !== null && $chosen === $correct) ? 1 : 0;
        if ($isRight) {
            $correctCount++;
            $earnedMarks += $mark;
        }
        $totalMarks += $mark;
        try { $stmtAns->execute([$sessionId, $qid, $chosen, $isRight]); } catch(Exception $e) {}
    }

    $numServed = count($dbQuestions);  // actual number of questions the student answered
    // Percentage based on marks (supports variable marks per question)
    $pct    = $totalMarks > 0 ? round($earnedMarks / $totalMarks * 100, 2) : 0;
    $grade  = computeGrade($pct);
    $status = $auto ? 'auto_submitted' : 'submitted';

    $db->prepare('UPDATE quiz_sessions SET submitted_at=NOW(), status=? WHERE id=?')
       ->execute([$status, $sessionId]);

    try {
        $db->prepare(
            'INSERT INTO results (student_id,subject_id,session_id,score,total_marks,percentage,grade)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE score=VALUES(score),total_marks=VALUES(total_marks),
             percentage=VALUES(percentage),grade=VALUES(grade),submitted_at=NOW()'
        )->execute([$studentId, $session['subject_id'], $sessionId, $correctCount, $numServed, $pct, $grade]);
    } catch(Exception $e) {}

    // Return correct/total_questions for the score display (e.g. "14 / 20")
    jsonOut(['ok'=>true,'score'=>$correctCount,'correct'=>$correctCount,'total_questions'=>$numServed,'percentage'=>$pct,'grade'=>$grade]);
}

// ── MY RESULTS ─────────────────────────────────────────────
if ($action === 'my_results') {
    $subjectId = (int)($_GET['subject_id'] ?? 0);
    $sql = 'SELECT r.*, sub.name as subject_name, sub.code FROM results r
            JOIN subjects sub ON sub.id=r.subject_id WHERE r.student_id=?';
    $params = [$studentId];
    if ($subjectId) { $sql .= ' AND r.subject_id=?'; $params[] = $subjectId; }
    $sql .= ' ORDER BY sub.name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonOut(['ok'=>true,'data'=>$stmt->fetchAll()]);
}

jsonOut(['ok'=>false,'msg'=>'Unknown action']);
