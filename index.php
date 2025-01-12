<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body>
    <h1>Project Management System</h1>
    <?php if (isset($_SESSION['user_id'])): ?>
        <p>You are logged in. Go to your <a href="pages/dashboard.php">dashboard</a>.</p>
    <?php else: ?>
        <p>Please choose an option:</p>
        <ul>
            <li><a href="pages/auth/login.php">Login</a></li>
            <li><a href="pages/auth/register.php">Register</a></li>
            <li><a href="pages/auth/forgot_password.php">Forgot Password</a></li>
        </ul>
    <?php endif; ?>
</body>
</html>
