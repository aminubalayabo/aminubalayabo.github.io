<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit;
}

$file_path = "https://raw.githubusercontent.com/aminubalayabo/aminubalayabo.github.io/main/Student_Information/Students_Profile.txt";
$username = $_SESSION['username'];

$data = file_get_contents($file_path);
$lines = explode("\n", $data);

$user_data = null;
foreach ($lines as $line) {
    $fields = explode(",", $line);
    if ($fields[0] == $username) {
        $user_data = $fields;
        break;
    }
}

if (!$user_data) {
    echo "User not found";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
</head>
<body>
    <h2>Student Profile</h2>
    <p>Username: <?php echo htmlspecialchars($user_data[0]); ?></p>
    <p>Admission Number: <?php echo htmlspecialchars($user_data[1]); ?></p>
    <p>Name: <?php echo htmlspecialchars($user_data[2]); ?></p>
    <p>Department: <?php echo htmlspecialchars($user_data[3]); ?></p>
    <p>Level: <?php echo htmlspecialchars($user_data[4]); ?></p>
    <p>Session: <?php echo htmlspecialchars($user_data[5]); ?></p>
    <p>Phone Number: <?php echo htmlspecialchars($user_data[6]); ?></p>
    <p>Email: <?php echo htmlspecialchars($user_data[7]); ?></p>

    <h3>Courses</h3>
    <?php
    for ($i = 8, $course = 1; $i < count($user_data); $i += 3, $course++) {
        echo "<p>Course $course: " . htmlspecialchars($user_data[$i]) . " - " . 
             htmlspecialchars($user_data[$i+1]) . " (" . htmlspecialchars($user_data[$i+2]) . " units)</p>";
    }
    ?>

    <a href="logout.php">Logout</a>
</body>
</html>
