<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Then your usual auth checks...
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
