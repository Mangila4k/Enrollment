<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear remember me cookies if they exist
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, '/');
}
if (isset($_COOKIE['user_remember'])) {
    setcookie('user_remember', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: login.php?logged_out=1");
exit();
?>