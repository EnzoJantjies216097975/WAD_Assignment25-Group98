<?php
// Update User Profile API

require_once '../config/database.php';

setJsonHeaders();

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendError('Method not allowed', 405);
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    sendError('Authentication required', 401);
}

try {
    $user_id = $_SESSION['user_id'];
    $data = getRequestBody();
    
    $db = getDB();
    
    // Get current user data
    $current_sql = "SELECT full_name, email, program, year_level, preferences FROM users WHERE id = ?";
    $current_stmt = $db->query($current_sql, [$user_id]);
    $current_user = $current_stmt->fetch();
    
    if (!$current_user) {
        sendError('User not found', 404);
    }
    
    // Build update query dynamically
    $update_fields = [];
    $update_params = [];
    $updated_session = false;
    
    // Update full name
    if (isset($data['full_name'])) {
        $full_name = sanitizeInput($data['full_name']);
        if (empty($full_name)) {
            sendError('Full name cannot be empty');
        }
        if ($full_name !== $current_user['full_name']) {
            $update_fields[] = "full_name = ?";
            $update_params[] = $full_name;
            $_SESSION['full_name'] = $full_name;
            $updated_session = true;
        }
    }
    
    // Update email
    if (isset($data['email'])) {
        $email = sanitizeInput($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Invalid email format');
        }
        if (!str_ends_with($email, '@nust.na')) {
            sendError('Please use your NUST email address (@nust.na)');
        }
        if ($email !== $current_user['email']) {
            // Check if email already exists
            $email_check = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
            if ($email_check->fetch()) {
                sendError('Email address already in use');
            }
            $update_fields[] = "email = ?";
            $update_params[] = $email;
            $_SESSION['email'] = $email;
            $updated_session = true;
        }
    }
    
    // Update program
    if (isset($data['program'])) {
        $program = sanitizeInput($data['program']);
        $valid_programs = [
            '07BCSS', '07BCSS-CN', '07BCSS-SD', '07BCCY', '07BAIT', '07BJOU'
        ];
        if (!in_array($program, $valid_programs)) {
            sendError('Invalid program selected');
        }
        if ($program !== $current_user['program']) {
            $update_fields[] = "program = ?";
            $update_params[] = $program;
            $_SESSION['program'] = $program;
            $updated_session = true;
        }
    }
    
    // Update year level
    if (isset($data['year_level'])) {
        $year_level = (int)$data['year_level'];
        if (!in_array($year_level, [1, 2, 3, 4])) {
            sendError('Year level must be between 1 and 4');
        }
        if ($year_level !== (int)$current_user['year_level']) {
            $update_fields[] = "year_level = ?";
            $update_params[] = $year_level;
            $_SESSION['year_level'] = $year_level;
            $updated_session = true;
        }
    }
    
    // Update preferences
    if (isset($data['preferences'])) {
        $preferences = $data['preferences'];
        if (!is_array($preferences)) {
            sendError('Preferences must be an object');
        }
        
        // Merge with existing preferences
        $current_preferences = json_decode($current_user['preferences'] ?? '{}', true) ?: [];
        $new_preferences = array_merge($current_preferences, $preferences);
        
        $update_fields[] = "preferences = ?";
        $update_params[] = json_encode($new_preferences);
    }
    
    // Update password if provided
    if (isset($data['current_password']) && isset($data['new_password'])) {
        $current_password = $data['current_password'];
        $new_password = $data['new_password'];
        
        // Verify current password
        $password_sql = "SELECT password_hash FROM users WHERE id = ?";
        $password_stmt = $db->query($password_sql, [$user_id]);
        $password_data = $password_stmt->fetch();
        
        if (!password_verify($current_password, $password_data['password_hash'])) {
            sendError('Current password is incorrect');
        }
        
        if (strlen($new_password) < 8) {
            sendError('New password must be at least 8 characters long');
        }
        
        $update_fields[] = "password_hash = ?";
        $update_params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    
    if (empty($update_fields)) {
        sendError('No valid fields to update');
    }
    
    // Add updated_at and user_id to params
    $update_fields[] = "updated_at = NOW()";
    $update_params[] = $user_id;
    
    // Execute update
    $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $db->query($update_sql, $update_params);
    
    if ($stmt->rowCount() === 0) {
        sendError('No changes were made');
    }
    
    // Regenerate session if sensitive data changed
    if ($updated_session) {
        session_regenerate_id(true);
    }
    
    // Get updated user data
    $result_sql = "SELECT 
                    id,
                    student_number,
                    full_name,
                    email,
                    program,
                    year_level,
                    preferences,
                    updated_at
                   FROM users 
                   WHERE id = ?";
    
    $result_stmt = $db->query($result_sql, [$user_id]);
    $updated_user = $result_stmt->fetch();
    
    if ($updated_user) {
        $updated_user['preferences'] = json_decode($updated_user['preferences'] ?? '{}', true);
        $updated_user['year_level'] = (int)$updated_user['year_level'];
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $updated_user,
        'session_regenerated' => $updated_session
    ]);
    
} catch (Exception $e) {
    sendError('Failed to update profile: ' . $e->getMessage(), 500);
}