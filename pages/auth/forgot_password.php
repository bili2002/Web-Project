<?php
session_start();
include '../../includes/db.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../../css/auth/forgot_password.css">
</head>
<body>

    <main>
        <?php if (!empty($error)): ?>
            <section class="error-message">
                <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
            </section>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <section class="success-message">
                <p style="color:green;"><?php echo htmlspecialchars($message); ?></p>
            </section>
        <?php endif; ?>

        <section class="form-container">
            <h1>Forgot Password</h1> <!-- Преместено заглавие -->
            <form method="post">
                <input type="text" id="username_or_faculty" name="username_or_faculty"  placeholder = "Username or faculty number" required><br><br>
                <button type="submit">Send Reset Instructions</button>
            </form>
        </section>

        <nav>
            <a href="../../index.php"> < Back to Home</a>
        </nav>
    </main>

</body>
</html>
