<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $file_path = "Student_Information/Students_Profile.txt";
    
    // Check if the user already exists
    $existing_data = file_get_contents($file_path);
    $lines = explode("\n", $existing_data);
    foreach ($lines as $line) {
        $fields = explode(",", $line);
        if ($fields[0] == $_POST['username'] || $fields[1] == $_POST['admission_number']) {
            echo "User already exists";
            exit;
        }
    }
    
    // Prepare the data
    $data = implode(",", [
        $_POST['username'],
        $_POST['admission_number'],
        $_POST['name'],
        $_POST['department'],
        $_POST['level'],
        $_POST['session'],
        $_POST['phone_number'],
        $_POST['email']
    ]);
    
    // Add course information
    for ($i = 1; $i <= 14; $i++) {
        $data .= "," . $_POST["course{$i}code"] . "," . $_POST["course{$i}title"] . "," . $_POST["course{$i}units"];
    }
    
    $data .= "\n";
    
    // Append the data to the file
    if (file_put_contents($file_path, $data, FILE_APPEND | LOCK_EX) !== false) {
        echo "Registration successful";
    } else {
        echo "Error during registration";
    }
} else {
    echo "Invalid request method";
}
?>
