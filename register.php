<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facultyNumber = $_POST['faculty_number'];
    $username      = $_POST['username'];
    $email         = $_POST['email'];
    $password      = $_POST['password'];
    $role          = 'user';

    if (empty($facultyNumber) || empty($username) || empty($password)) {
        $error = "Please fill in all required fields.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO users (faculty_number, username, password, email, role)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $facultyNumber, $username, $hashedPassword, $email, $role);

        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit;
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
<h1>Register</h1>

<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

<form action="" method="post">
    <label>Faculty Number*</label><br>
    <input type="text" name="faculty_number" required><br><br>
    
    <label>Username*</label><br>
    <input type="text" name="username" required><br><br>
    
    <label>Email</label><br>
    <input type="email" name="email"><br><br>

    <label>Password*</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Register</button>
</form>

<p>Already have an account? <a href="login.php">Click here to login</a>.</p>
<p><a href="index.php">Back to Home</a></p>
</body>
</html>
