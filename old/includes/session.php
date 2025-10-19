<?php
// includes/session.php - Session management for NUST Timetable Manager

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']), // True if HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_name(SESSION_NAME);
    session_start();
    session_regenerate_id();
}

/**
 * Check if user is authenticated
 * @return bool True if authenticated, false otherwise
 */
function checkAuth() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check for session timeout
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_LIFETIME) {
            // Session has expired
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require authentication for a page
 * Redirects to login if not authenticated
 */
function requireAuth() {
    if (!checkAuth()) {
        header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * Set session data
 * @param string $key Session key
 * @param mixed $value Session value
 */
function setSession($key, $value) {
    $_SESSION[$key] = $value;
}

/**
 * Get session data
 * @param string $key Session key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Session value or default
 */
function getSession($key, $default = null) {
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

/**
 * Remove session data
 * @param string $key Session key
 */
function removeSession($key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

/**
 * Flash message functionality
 * @param string $message Message to flash
 * @param string $type Message type (success, error, warning, info)
 */
function setFlash($message, $type = 'info') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * @return array|null Flash message or null
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF token generation and validation
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get current user data
 * @return array|null User data or null
 */
function getCurrentUser() {
    if (!checkAuth()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'student_number' => $_SESSION['student_number'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['email'] ?? ''
    ];
}

/**
 * Check if user has specific permission
 * @param string $permission Permission to check
 * @return bool True if has permission, false otherwise
 */
function hasPermission($permission) {
    // For now, all logged-in users have all permissions
    // This can be expanded for role-based access control
    return checkAuth();
}

/**
 * Log user activity
 * @param string $action Action performed
 * @param array $data Additional data
 */
function logUserActivity($action, $data = []) {
    if (!checkAuth()) {
        return;
    }
    
    require_once 'db_connect.php';
    $db = Database::getInstance();
    
    try {
        $db->query(
            "INSERT INTO user_activity (user_id, action, data, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                $_SESSION['user_id'],
                $action,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}
?>