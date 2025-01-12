<?php
session_start();
include '../../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrFaculty = $_POST['username_or_faculty'];
    $password          = $_POST['password'];
    
    if (!empty($usernameOrFaculty) && !empty($password)) {
        $sql  = "SELECT * FROM users WHERE username = ? OR faculty_number = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usernameOrFaculty, $usernameOrFaculty);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                header("Location: ../dashboard.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in both fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<h1>Login</h1>

<?php
if (isset($_GET['registered'])) {
    echo "<p style='color:green;'>Registration successful, please login.</p>";
}
if (!empty($error)) {
    echo "<p style='color:red;'>$error</p>";
}
?>

<form method="post">
    <label>Username or Faculty Number</label><br>
    <input type="text" name="username_or_faculty" required><br><br>

    <label>Password</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Login</button>
</form>

<p>Don't have an account yet? <a href="register.php">Click here to register</a>.</p>
<p><a href="../../index.php">Back to Home</a></p>
</body>
</html>
