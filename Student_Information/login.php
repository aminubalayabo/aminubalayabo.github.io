<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $file_path = "Student_Information/Students_Results.txt";
    $username = $_POST['username'];
    $password = $_POST['password'];  // This is the admission number
    
    $data = file_get_contents($file_path);
    $lines = explode("\n", $data);
    
    foreach ($lines as $line) {
        $fields = explode(",", $line);
        if ($fields[0] == $username && $fields[1] == $password) {
            $_SESSION['username'] = $username;
            echo "Login successful";
            exit;
        }
    }
    
    echo "Invalid username or password";
} else {
    echo "Invalid request method";
}
?>
