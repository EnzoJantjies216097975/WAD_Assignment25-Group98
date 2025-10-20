<?php
// Authentication Middleware

// Check if user is authenticated
function requireAuth() {
    session_start();
    
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        sendError('Authentication required. Please login.', 401);
    }
    
    // Check session timeout (4 hours default)
    $timeout = 14400; // 4 hours in seconds
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout) {
        session_destroy();
        sendError('Session expired. Please login again.', 401);
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return $_SESSION['user_id'];
}

// Check if user is admin (optional for future features)
function requireAdmin() {
    $user_id = requireAuth();
    
    // For now, check if user is in admin list
    $admin_emails = ['admin@nust.na'];
    
    if (!in_array($_SESSION['email'], $admin_emails)) {
        sendError('Administrator access required', 403);
    }
    
    return $user_id;
}

// Get current user information
function getCurrentUser() {
    requireAuth();
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'student_number' => $_SESSION['student_number'],
        'program' => $_SESSION['program'],
        'year_level' => $_SESSION['year_level'] ?? 1
    ];
}

// Validate user owns resource
function validateResourceOwner($resource_user_id) {
    $current_user_id = requireAuth();
    
    if ((int)$resource_user_id !== (int)$current_user_id) {
        sendError('Access denied. You can only access your own resources.', 403);
    }
    
    return true;
}

// Rate limiting (simple implementation)
function checkRateLimit($action = 'general', $limit = 60, $window = 3600) {
    session_start();
    
    $key = "rate_limit_{$action}";
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Clean old requests outside the window
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });
    
    // Check if limit exceeded
    if (count($_SESSION[$key]) >= $limit) {
        sendError('Rate limit exceeded. Please try again later.', 429);
    }
    
    // Add current request
    $_SESSION[$key][] = $now;
}

// Log user activity (optional)
function logActivity($action, $details = null) {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $db = getDB();
        
        // Create activity log table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details JSON DEFAULT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )");
        
        $sql = "INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            $_SESSION['user_id'],
            $action,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Validate CSRF token (if implementing CSRF protection)
function validateCSRF() {
    session_start();
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        sendError('CSRF token missing', 403);
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        sendError('Invalid CSRF token', 403);
    }
}

// Generate CSRF token
function generateCSRF() {
    session_start();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Sanitize file upload
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('No valid file uploaded');
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('File size exceeds maximum allowed size');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('File type not allowed');
    }
    
    return true;
}

// Input validation helpers
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    if (!str_ends_with($email, '@nust.na')) {
        throw new Exception('Please use your NUST email address');
    }
    
    return true;
}

function validateStudentNumber($student_number) {
    if (!preg_match('/^[0-9]{9}$/', $student_number)) {
        throw new Exception('Student number must be exactly 9 digits');
    }
    
    return true;
}

function validatePassword($password) {
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    // Add more password requirements if needed
    if (!preg_match('/[A-Za-z]/', $password)) {
        throw new Exception('Password must contain at least one letter');
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception('Password must contain at least one number');
    }
    
    return true;
}