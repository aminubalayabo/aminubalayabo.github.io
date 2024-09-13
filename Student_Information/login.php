<?php
session_start();

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
    $username = $_POST['username'];
    $password = $_POST['password'];  // This is the admission number

    $sql = "SELECT * FROM Students_Profile WHERE username = ? AND admission_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['admission_number'] = $row['admission_number'];
        header("Location: student_dashboard.php");
    } else {
        echo "Invalid username or password";
    }

    $stmt->close();
}

$conn->close();
?>
