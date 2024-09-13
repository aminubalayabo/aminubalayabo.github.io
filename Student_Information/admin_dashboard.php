<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
</head>
<body>
    <h2>Admin Dashboard</h2>

    <h3>Upload Results</h3>
    <form action="upload_results.php" method="POST" enctype="multipart/form-data">
        <label for="department">Department:</label>
        <select id="department" name="department" required>
            <!-- Add department options here -->
        </select><br><br>

        <label for="level">Level:</label>
        <select id="level" name="level" required>
            <!-- Add level options here -->
        </select><br><br>

        <label for="session">Session:</label>
        <select id="session" name="session" required>
            <!-- Add session options here -->
        </select><br><br>

        <label for="result_file">Result File:</label>
        <input type="file" id="result_file" name="result_file" required><br><br>

        <input type="submit" value="Upload Results">
    </form>

    <h3>Generate Result Summary</h3>
    <form action="generate_summary.php" method="POST">
        <label for="summary_department">Department:</label>
        <select id="summary_department" name="summary_department" required>
            <!-- Add department options here -->
        </select><br><br>

        <label for="summary_level">Level:</label>
        <select id="summary_level" name="summary_level" required>
            <!-- Add level options here -->
        </select><br><br>

        <label for="summary_session">Session:</label>
        <select id="summary_session" name="summary_session" required>
            <!-- Add session options here -->
        </select><br><br>

        <input type="submit" value="Generate Summary">
    </form>

    <p><a href="admin_logout.php">Logout</a></p>
</body>
</html>
