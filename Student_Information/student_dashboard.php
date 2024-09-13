<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM Students_Profile WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($user_data['name']); ?>!</h2>
    <p>Admission Number: <?php echo htmlspecialchars($user_data['admission_number']); ?></p>
    <p>Department: <?php echo htmlspecialchars($user_data['department']); ?></p>
    <p>Level: <?php echo htmlspecialchars($user_data['level']); ?></p>

    <h3>Actions:</h3>
    <button onclick="window.location.href='view_profile.php'">View Profile</button>
    <button onclick="window.location.href='view_results.php'">View Results</button>

    <p><a href="logout.php">Logout</a></p>
</body>
</html>
Last edited 11 minutes ago
