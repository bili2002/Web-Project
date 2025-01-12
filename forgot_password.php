<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrFaculty = $_POST['username_or_faculty'];

    // Basic check if user exists
    $sql  = "SELECT * FROM users WHERE username = ? OR faculty_number = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $usernameOrFaculty, $usernameOrFaculty);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        // User found—here you'd normally send an email or show a "reset instructions sent" message.
        // But since we're not using tokens or emails, we’ll keep it simple:
        $message = "If this were a real system, we'd email you a reset link. For now, please contact the admin.";
    } else {
        $error = "No user found with that username or faculty number.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
</head>
<body>
<h1>Forgot Password</h1>

<?php
if (!empty($error)) {
    echo "<p style='color:red;'>$error</p>";
}
if (!empty($message)) {
    echo "<p style='color:green;'>$message</p>";
}
?>

<form method="post">
    <label>Username or Faculty Number</label><br>
    <input type="text" name="username_or_faculty" required><br><br>
    <button type="submit">Send Reset Instructions</button>
</form>

<p><a href="index.php">Back to Home</a></p>
</body>
</html>
