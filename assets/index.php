<?php
include 'includes/db.php';
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle login or registration logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action == "register") {
        // Registration logic
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        if ($stmt->execute()) {
            $success = "Registration successful! You can now log in.";
        } else {
            $error = "Registration failed. Username might already exist.";
        }

        $stmt->close();
    } elseif ($action == "login") {
        // Login logic
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
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
    <title>Project Management</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Welcome to Project Management</h1>

        <?php if (isset($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>

        <div class="tabs">
            <button onclick="showForm('login')">Login</button>
            <button onclick="showForm('register')">Register</button>
        </div>

        <form id="loginForm" action="index.php" method="POST">
            <h2>Login</h2>
            <input type="hidden" name="action" value="login">
            <label for="loginUsername">Username:</label>
            <input type="text" id="loginUsername" name="username" required>
            <label for="loginPassword">Password:</label>
            <input type="password" id="loginPassword" name="password" required>
            <button type="submit">Login</button>
        </form>

        <form id="registerForm" action="index.php" method="POST" style="display:none;">
            <h2>Register</h2>
            <input type="hidden" name="action" value="register">
            <label for="registerUsername">Username:</label>
            <input type="text" id="registerUsername" name="username" required>
            <label for="registerPassword">Password:</label>
            <input type="password" id="registerPassword" name="password" required>
            <label for="registerRole">Role:</label>
            <select id="registerRole" name="role">
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
