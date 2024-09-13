<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "student_information";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department = $_POST['department'];
    $level = $_POST['level'];
    $session = $_POST['session'];

    if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] == 0) {
        $file = $_FILES['result_file']['tmp_name'];
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $admission_number = $data[0];
                $course_code = $data[1];
                $ca_score = $data[2];
                $exam_score = $data[3];
                $total_score = $data[4];

                // Calculate grade
                $grade = calculateGrade($total_score);

                // Insert or update result in database
                $sql = "INSERT INTO Students_Results (admission_number, department, level, session, course_code, ca_score, exam_score, total_score, grade) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        ca_score = VALUES(ca_score), 
                        exam_score = VALUES(exam_score), 
                        total_score = VALUES(total_score), 
                        grade = VALUES(grade)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssddds", $admission_number, $department, $level, $session, $course_code, $ca_score, $exam_score, $total_score, $grade);
                $stmt->execute();
                $stmt->close();
            }
            fclose($handle);
            echo "Results uploaded successfully.";
        } else {
            echo "Error opening file.";
        }
    } else {
        echo "Error uploading file.";
    }
}

$conn->close();

function calculateGrade($score) {
    if ($score >= 70) return 'A';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    if ($score >= 40) return 'E';
    return 'F';
}
?>
