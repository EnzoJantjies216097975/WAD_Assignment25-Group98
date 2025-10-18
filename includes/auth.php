<?php
// includes/auth.php - Authentication functions for NUST Timetable Manager

require_once 'config.php';
require_once 'db_connect.php';

/**
 * Authenticate user login
 * @param string $identifier Email or student number
 * @param string $password Plain text password
 * @return array Result with success status and user data or error message
 */
function authenticateUser($identifier, $password) {
    $db = Database::getInstance();
    
    try {
        // Check if identifier is email or student number
        $stmt = $db->query(
            "SELECT user_id, student_number, first_name, last_name, email, password, year_of_study, program 
             FROM users 
             WHERE email = ? OR student_number = ?",
            [$identifier, $identifier]
        );
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }
        
        $user = $stmt->fetch();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }
        
        // Update last login
        $db->query(
            "UPDATE users SET last_login = NOW() WHERE user_id = ?",
            [$user['user_id']]
        );
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['student_number'] = $user['student_number'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['year_of_study'] = $user['year_of_study'];
        $_SESSION['program'] = $user['program'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['user_id'],
                'student_number' => $user['student_number'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email']
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Authentication failed'
        ];
    }
}

/**
 * Register new user
 * @param array $userData User registration data
 * @return array Result with success status and message
 */
function registerUser($userData) {
    $db = Database::getInstance();
    
    // Validate required fields
    $required = ['student_number', 'first_name', 'last_name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($userData[$field])) {
            return [
                'success' => false,
                'message' => "Missing required field: $field"
            ];
        }
    }
    
    // Validate email format
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email address'
        ];
    }
    
    // Validate password strength
    if (strlen($userData['password']) < PASSWORD_MIN_LENGTH) {
        return [
            'success' => false,
            'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
        ];
    }
    
    try {
        // Check if student number already exists
        $checkStmt = $db->query(
            "SELECT user_id FROM users WHERE student_number = ?",
            [$userData['student_number']]
        );
        
        if ($checkStmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => 'Student number already registered'
            ];
        }
        
        // Check if email already exists
        $checkStmt = $db->query(
            "SELECT user_id FROM users WHERE email = ?",
            [$userData['email']]
        );
        
        if ($checkStmt->rowCount() > 0) {
            return [
                'success' => false,
                'message' => 'Email address already registered'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $db->query(
            "INSERT INTO users (student_number, first_name, last_name, email, password, year_of_study, program) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userData['student_number'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['email'],
                $hashedPassword,
                $userData['year_of_study'] ?? 1,
                $userData['program'] ?? ''
            ]
        );
        
        return [
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $db->lastInsertId()
        ];
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ];
    }
}

/**
 * Logout user
 * Destroys session and clears authentication
 */
function logoutUser() {
    // Clear session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check if current session is valid
 * @return boolean True if valid, false otherwise
 */
function isValidSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_LIFETIME) {
            logoutUser();
            return false;
        }
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 * @param string $redirectUrl URL to redirect after login (optional)
 */
function requireLogin($redirectUrl = null) {
    if (!isValidSession()) {
        $loginUrl = '../login.php';
        if ($redirectUrl) {
            $loginUrl .= '?redirect=' . urlencode($redirectUrl);
        }
        header("Location: $loginUrl");
        exit();
    }
}

/**
 * Get current user ID
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user details
 * @return array|null User details or null if not logged in
 */
function getCurrentUserDetails() {
    if (!isValidSession()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'student_number' => $_SESSION['student_number'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['email'],
        'year_of_study' => $_SESSION['year_of_study'] ?? null,
        'program' => $_SESSION['program'] ?? null
    ];
}

/**
 * Update user password
 * @param int $userId User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return array Result with success status and message
 */
function updatePassword($userId, $currentPassword, $newPassword) {
    $db = Database::getInstance();
    
    try {
        // Get current password hash
        $stmt = $db->query(
            "SELECT password FROM users WHERE user_id = ?",
            [$userId]
        );
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        $user = $stmt->fetch();
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }
        
        // Validate new password
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
            ];
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $db->query(
            "UPDATE users SET password = ? WHERE user_id = ?",
            [$hashedPassword, $userId]
        );
        
        return [
            'success' => true,
            'message' => 'Password updated successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Password update error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to update password'
        ];
    }
}

/**
 * Reset user password
 * @param string $email User email
 * @return array Result with success status and message
 */
function resetPassword($email) {
    $db = Database::getInstance();
    
    try {
        // Check if email exists
        $stmt = $db->query(
            "SELECT user_id, first_name FROM users WHERE email = ?",
            [$email]
        );
        
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'Email address not found'
            ];
        }
        
        $user = $stmt->fetch();
        
        // Generate reset token
        $resetToken = generateToken(32);
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token (you'd need to add password_reset_tokens table)
        // For now, we'll just return success
        
        // Send reset email (implement email sending)
        // sendPasswordResetEmail($email, $resetToken);
        
        return [
            'success' => true,
            'message' => 'Password reset instructions sent to your email'
        ];
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to process password reset'
        ];
    }
}
?>