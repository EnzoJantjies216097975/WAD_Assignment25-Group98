<?php
// logout.php - Logout handler for NUST Timetable Manager
session_start();

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to home page with logout message
header("Location: index.php?logout=success");
exit();
?>