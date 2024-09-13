<?php
// Database connection
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "student_information";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $admission_number = $_POST['admission_number'];
    $name = $_POST['name'];
    $department = $_POST['department'];
    $level = $_POST['level'];
    $session = $_POST['session'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];

    // Handle file upload
    $target_dir = "Passport/";
    $target_file = $target_dir . $admission_number . ".jpg";
    move_uploaded_file($_FILES["passport"]["tmp_name"], $target_file);

    // Prepare SQL statement
    $sql = "INSERT INTO Students_Profile (username, admission_number, name, department, level, session, phone_number, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $username, $admission_number, $name, $department, $level, $session, $phone_number, $email);
    
    if ($stmt->execute()) {
        echo "Registration successful";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();

    // Handle course information
    for ($i = 1; $i <= 14; $i++) {
        $code = $_POST["course{$i}code"];
        $title = $_POST["course{$i}title"];
        $units = $_POST["course{$i}units"];

        if (!empty($code) && !empty($title) && !empty($units)) {
            $sql = "UPDATE Students_Profile SET cose{$i}code = ?, cose{$i}title = ?, cose{$i}units = ? WHERE admission_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $code, $title, $units, $admission_number);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();
?>
