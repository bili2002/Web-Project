<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

echo "Welcome, " . htmlspecialchars($_SESSION['username']) . "! You are logged in as " . htmlspecialchars($_SESSION['role']) . ".";
?>

<a href="logout.php">Logout</a>
