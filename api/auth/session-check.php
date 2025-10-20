<?php
// Session Check API - Verifies if user is authenticated

require_once '../config/database.php';
require_once '../middleware/auth.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Start session
session_start();

try {
    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        sendResponse([
            'success' => false,
            'authenticated' => false,
            'message' => 'Not authenticated'
        ]);
        exit;
    }

    // Check session timeout (4 hours default)
    $timeout = 14400; // 4 hours in seconds
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
        session_destroy();
        sendResponse([
            'success' => false,
            'authenticated' => false,
            'message' => 'Session expired'
        ]);
        exit;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Return user information
    $user = [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'student_number' => $_SESSION['student_number'],
        'program' => $_SESSION['program'] ?? 'Unknown Program',
        'year_level' => $_SESSION['year_level'] ?? 1
    ];

    // Generate initials for avatar
    $name_parts = explode(' ', $user['full_name']);
    $user['initials'] = strtoupper(
        (isset($name_parts[0]) ? $name_parts[0][0] : '') .
        (isset($name_parts[1]) ? $name_parts[1][0] : '')
    );

    sendResponse([
        'success' => true,
        'authenticated' => true,
        'user' => $user,
        'session_info' => [
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'time_remaining' => isset($_SESSION['login_time']) ?
                max(0, $timeout - (time() - $_SESSION['login_time'])) : 0
        ]
    ]);

} catch (Exception $e) {
    sendError('Session check failed: ' . $e->getMessage(), 500);
}