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
    $department = $_POST['summary_department'];
    $level = $_POST['summary_level'];
    $session = $_POST['summary_session'];

    // Fetch results for the specified department, level, and session
    $sql = "SELECT sr.*, sp.name 
            FROM Students_Results sr
            JOIN Students_Profile sp ON sr.admission_number = sp.admission_number
            WHERE sr.department = ? AND sr.level = ? AND sr.session = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $department, $level, $session);
    $stmt->execute();
    $result = $stmt->get_result();

    $summaries = [];

    while ($row = $result->fetch_assoc()) {
        $admission_number = $row['admission_number'];
        
        if (!isset($summaries[$admission_number])) {
            $summaries[$admission_number] = [
                'name' => $row['name'],
                'department' => $row['department'],
                'level' => $row['level'],
                'courses' => [],
                'total_units' => 0,
                'total_grade_points' => 0
            ];
        }

        $grade_point = calculateGradePoint($row['grade']);
        $course_units = fetchCourseUnits($conn, $row['course_code']);

        $summaries[$admission_number]['courses'][] = [
            'code' => $row['course_code'],
            'units' => $course_units,
            'grade' => $row['grade'],
            'grade_point' => $grade_point
        ];

        $summaries[$admission_number]['total_units'] += $course_units;
        $summaries[$admission_number]['total_grade_points'] += ($grade_point * $course_units);
    }

    $stmt->close();

    // Generate and display summary
    foreach ($summaries as $admission_number => $summary) {
        $gpa = $summary['total_grade_points'] / $summary['total_units'];
        
        echo "<h2>Result Summary for {$summary['name']} ({$admission_number})</h2>";
        echo "<p>Department: {$summary['department']}</p>";
        echo "<p>Level: {$summary['level']}</p>";
        echo "<p>Session: {$session}</p>";
        echo "<table border='1'>";
        echo "<tr><th>Course Code</th><th>Units</th><th>Grade</th><th>Grade Point</th></tr>";
        
        foreach ($summary['courses'] as $course) {
            echo "<tr>";
            echo "<td>{$course['code']}</td>";
            echo "<td>{$course['units']}</td>";
            echo "<td>{$course['grade']}</td>";
            echo "<td>{$course['grade_point']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<p>Total Units: {$summary['total_units']}</p>";
        echo "<p>GPA: " . number_format($gpa, 2) . "</p>";
        echo "<hr>";
    }
}

$conn->close();

function calculateGradePoint($grade) {
    switch ($grade) {
        case 'A': return 5;
        case 'B': return 4;
        case 'C': return 3;
        case 'D': return 2;
        case 'E': return 1;
        default: return 0;
    }
}

function fetchCourseUnits($conn, $course_code) {
    $sql = "SELECT units FROM Courses WHERE code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['units'] : 0;
}
?>
