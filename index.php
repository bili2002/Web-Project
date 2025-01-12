<?php
session_start();
?>
<!-- <!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <title>Welcome</title>
</head>
<body>
    <h1>Project Management System</h1>
  //  <?php if (isset($_SESSION['user_id'])): ?>
        <p>You are logged in. Go to your <a href="dashboard.php">dashboard</a>.</p>
   // <?php else: ?>
        <p>Please choose an option:</p>
        <ul>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
            <li><a href="forgot_password.php">Forgot Password</a></li>
        </ul>
  //  <?php endif; ?>
</body>
</html> -->


<!-- <!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <link rel="stylesheet" type="text/css" href="css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Project Management System</h1>
        //<?php if (isset($_SESSION['user_id'])): ?>
            <p>You are logged in. Go to your <a href="dashboard.php">dashboard</a>.</p>
        //<?php else: ?>
            <p>Please choose an option:</p>
            <div class="links">
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
                <a href="forgot_password.php">Forgot Password</a>
            </div>
            <p class="register-message">Don't have an account? <a href="register.php" style="color: white; text-decoration: underline;">Register here</a>.</p>
        //<?php endif; ?>
    </div>
</body>
</html> -->


<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <link rel="stylesheet" type="text/css" href="css/styles.css">
</head>
<body>
    <div>
        <h1>Project Management System</h1>
        <div class="center-buttons">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>
    </div>
</body>
</html>

