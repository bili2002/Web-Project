<?php
session_start();

// Remove all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Optional: Clear other application-specific cookies
setcookie("your_cookie_name", "", time() - 3600, "/");

// Redirect to login or homepage
header("Location: login.php");
exit;
?>
