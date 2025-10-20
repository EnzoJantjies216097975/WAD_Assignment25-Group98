<?php
// User Logout API

require_once '../config/database.php';

setJsonHeaders();

// Allow POST and GET requests for logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        sendResponse([
            'success' => true,
            'message' => 'Already logged out',
            'redirect' => 'login.html'
        ]);
    }
    
    // Get user ID before destroying session
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Optional: Log the logout event in database
    if ($user_id) {
        try {
            $db = getDB();
            $db->query("UPDATE users SET last_logout = NOW() WHERE id = ?", [$user_id]);
        } catch (Exception $e) {
            // Log error but don't fail logout
            error_log("Failed to update last_logout: " . $e->getMessage());
        }
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Logout successful',
        'redirect' => 'login.html'
    ]);
    
} catch (Exception $e) {
    sendError('Logout failed: ' . $e->getMessage(), 500);
}