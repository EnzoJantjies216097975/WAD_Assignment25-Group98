<?php
// User Registration API

require_once '../config/database.php';

setJsonHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

try {
    $data = getRequestBody();
    
    // Validate required fields
    validateRequired($data, ['fullname', 'student_number', 'email', 'password', 'program']);
    
    // Sanitize input
    $fullname = sanitizeInput($data['fullname']);
    $student_number = sanitizeInput($data['student_number']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $program = sanitizeInput($data['program']);
    $year_level = isset($data['year_level']) ? (int)$data['year_level'] : 1;
    
    // Validate input formats
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Invalid email format');
    }
    
    if (!preg_match('/^[0-9]{9}$/', $student_number)) {
        sendError('Student number must be 9 digits');
    }
    
    if (!str_ends_with($email, '@nust.na')) {
        sendError('Please use your NUST email address (@nust.na)');
    }
    
    if (strlen($password) < 8) {
        sendError('Password must be at least 8 characters long');
    }
    
    $db = getDB();
    
    // Check if email already exists
    $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
    if ($stmt->fetch()) {
        sendError('Email address already registered');
    }
    
    // Check if student number already exists
    $stmt = $db->query("SELECT id FROM users WHERE student_number = ?", [$student_number]);
    if ($stmt->fetch()) {
        sendError('Student number already registered');
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $sql = "INSERT INTO users (student_number, full_name, email, password_hash, program, year_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $db->query($sql, [
        $student_number,
        $fullname,
        $email,
        $password_hash,
        $program,
        $year_level
    ]);
    
    $user_id = $db->lastInsertId();
    
    // Start session for automatic login
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['full_name'] = $fullname;
    $_SESSION['student_number'] = $student_number;
    $_SESSION['program'] = $program;
    $_SESSION['logged_in'] = true;
    
    sendResponse([
        'success' => true,
        'message' => 'Account created successfully',
        'user' => [
            'id' => $user_id,
            'full_name' => $fullname,
            'email' => $email,
            'student_number' => $student_number,
            'program' => $program,
            'year_level' => $year_level
        ],
        'redirect' => 'dashboard.html'
    ], 201);
    
} catch (Exception $e) {
    sendError('Registration failed: ' . $e->getMessage(), 500);
}