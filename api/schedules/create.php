<?php
// Create Schedule API

require_once '../config/database.php';

setJsonHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    sendError('Authentication required', 401);
}

try {
    $data = getRequestBody();
    
    // Validate required fields
    validateRequired($data, ['name', 'semester', 'year']);
    
    $user_id = $_SESSION['user_id'];
    $name = sanitizeInput($data['name']);
    $semester = (int)$data['semester'];
    $year = (int)$data['year'];
    $description = sanitizeInput($data['description'] ?? '');
    $courses = $data['courses'] ?? [];
    $status = sanitizeInput($data['status'] ?? 'draft');
    
    // Validate inputs
    if (empty($name)) {
        sendError('Schedule name is required');
    }
    
    if (!in_array($semester, [1, 2])) {
        sendError('Semester must be 1 or 2');
    }
    
    if ($year < 2024 || $year > 2030) {
        sendError('Year must be between 2024 and 2030');
    }
    
    if (!in_array($status, ['draft', 'active', 'archived'])) {
        sendError('Invalid status');
    }
    
    $db = getDB();
    
    // Check if user already has a schedule with this name
    $check_sql = "SELECT id FROM schedules WHERE user_id = ? AND name = ?";
    $check_stmt = $db->query($check_sql, [$user_id, $name]);
    if ($check_stmt->fetch()) {
        sendError('You already have a schedule with this name');
    }
    
    // If setting as active, deactivate other schedules
    if ($status === 'active') {
        $db->query("UPDATE schedules SET status = 'draft' WHERE user_id = ? AND status = 'active'", [$user_id]);
    }
    
    $db->beginTransaction();
    
    try {
        // Create schedule
        $sql = "INSERT INTO schedules (user_id, name, description, semester, year, status, courses_data, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $courses_json = json_encode($courses);
        
        $db->query($sql, [
            $user_id,
            $name,
            $description,
            $semester,
            $year,
            $status,
            $courses_json
        ]);
        
        $schedule_id = $db->lastInsertId();
        
        // Validate and save individual course entries if courses provided
        if (!empty($courses)) {
            $course_sql = "INSERT INTO schedule_courses (schedule_id, course_code, day_of_week, start_time, end_time, venue, lecturer, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            foreach ($courses as $course) {
                if (isset($course['code'], $course['day'], $course['time'])) {
                    // Parse time slot (e.g., "08:00-09:00")
                    $time_parts = explode('-', $course['time']);
                    $start_time = $time_parts[0] ?? '08:00';
                    $end_time = $time_parts[1] ?? '09:00';
                    
                    $db->query($course_sql, [
                        $schedule_id,
                        $course['code'],
                        $course['day'],
                        $start_time,
                        $end_time,
                        $course['venue'] ?? '',
                        $course['lecturer'] ?? ''
                    ]);
                }
            }
        }
        
        $db->commit();
        
        // Get the created schedule with full details
        $result_sql = "SELECT 
                        id,
                        name,
                        description,
                        semester,
                        year,
                        status,
                        courses_data,
                        created_at,
                        updated_at
                       FROM schedules 
                       WHERE id = ?";
        
        $stmt = $db->query($result_sql, [$schedule_id]);
        $schedule = $stmt->fetch();
        
        if ($schedule) {
            $schedule['courses_data'] = json_decode($schedule['courses_data'] ?? '[]', true);
            $schedule['semester'] = (int)$schedule['semester'];
            $schedule['year'] = (int)$schedule['year'];
        }
        
        sendResponse([
            'success' => true,
            'message' => 'Schedule created successfully',
            'schedule' => $schedule
        ], 201);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError('Failed to create schedule: ' . $e->getMessage(), 500);
}