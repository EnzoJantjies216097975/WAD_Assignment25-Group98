<?php
// User Login API

require_once '../config/database.php';

setJsonHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

try {
    $data = getRequestBody();
    
    // Validate required fields
    validateRequired($data, ['email', 'password']);
    
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $remember_me = isset($data['remember_me']) ? (bool)$data['remember_me'] : false;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format');
    }
    
    $db = getDB();
    
    // Get user by email
    $sql = "SELECT id, student_number, full_name, email, password_hash, program, year_level, status, last_login 
            FROM users WHERE email = ?";
    $stmt = $db->query($sql, [$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Invalid email or password');
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        sendError('Account is suspended. Please contact support.');
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        sendError('Invalid email or password');
    }
    
    // Update last login
    $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    
    // Start session
    session_start();
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['student_number'] = $user['student_number'];
    $_SESSION['program'] = $user['program'];
    $_SESSION['year_level'] = $user['year_level'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Set session timeout (24 hours for remember me, 4 hours default)
    if ($remember_me) {
        ini_set('session.gc_maxlifetime', 86400); // 24 hours
        session_set_cookie_params(86400);
    } else {
        ini_set('session.gc_maxlifetime', 14400); // 4 hours
        session_set_cookie_params(14400);
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Prepare user data for response (exclude sensitive info)
    $userData = [
        'id' => $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'student_number' => $user['student_number'],
        'program' => $user['program'],
        'year_level' => $user['year_level'],
        'last_login' => $user['last_login']
    ];
    
    sendResponse([
        'success' => true,
        'message' => 'Login successful',
        'user' => $userData,
        'session_id' => session_id(),
        'redirect' => 'dashboard.html'
    ]);
    
} catch (Exception $e) {
    sendError('Login failed: ' . $e->getMessage(), 500);
}