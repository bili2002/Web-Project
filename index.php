<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start a session
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Welcome to Project Management</h1>
        
        <div class="tabs">
            <button onclick="showForm('login')">Login</button>
            <button onclick="showForm('register')">Register</button>
        </div>

        <!-- Login Form -->
        <form id="loginForm" action="login.php" method="POST">
            <h2>Login</h2>
            <label for="loginUsername">Username:</label>
            <input type="text" id="loginUsername" name="username" required>
            <label for="loginPassword">Password:</label>
            <input type="password" id="loginPassword" name="password" required>
            <button type="submit">Login</button>
        </form>

        <!-- Registration Form -->
        <form id="registerForm" action="register.php" method="POST" style="display: none;">
            <h2>Register</h2>
            <label for="registerUsername">Username:</label>
            <input type="text" id="registerUsername" name="username" required>
            <label for="registerPassword">Password:</label>
            <input type="password" id="registerPassword" name="password" required>
            <label for="registerRole">Role:</label>
            <select id="registerRole" name="role" required>
                <option value="team_leader">Team Leader</option>
                <option value="developer">Developer</option>
                <option value="observer">Observer</option>
            </select>
            <button type="submit">Register</button>
        </form>
    </div>

    <script>
        function showForm(form) {
            document.getElementById('loginForm').style.display = form === 'login' ? 'block' : 'none';
            document.getElementById('registerForm').style.display = form === 'register' ? 'block' : 'none';
        }
    </script>
</body>
</html>