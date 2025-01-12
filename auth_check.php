<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Not logged in -> redirect to login
    header("Location: login.php");
    exit;
}
?>
