<?php
session_start();
include '../../includes/db.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" type="text/css" href="../../css/register.css">
</head>
<body>
    <main>
        <section>

            <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

            <form action="" method="post">
                <fieldset>
                    <legend>Registration Form</legend>
                    
                    <input type="text" name="faculty_number" placeholder="Faculty Number" required>
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email">
                    <input type="password" name="password" placeholder="Password" required>
                    
                    <button type="submit">Register</button>
                </fieldset>
            </form>
        </section>

        <footer>
            <p>Already have an account?</p>
            <p> <a href="login.php">Click here to login</a></p>
        </footer>
    </main>
</body>
</html>
