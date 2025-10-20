<?php
// Update Schedule API

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
    
    // Validate required fields
    validateRequired($data, ['id']);
    
    $schedule_id = (int)$data['id'];
    $name = sanitizeInput($data['name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $semester = isset($data['semester']) ? (int)$data['semester'] : null;
    $year = isset($data['year']) ? (int)$data['year'] : null;
    $status = sanitizeInput($data['status'] ?? '');
    $courses = $data['courses'] ?? null;
    
    $db = getDB();
    
    // Check if schedule exists and belongs to user
    $check_sql = "SELECT id, name, status FROM schedules WHERE id = ? AND user_id = ?";
    $check_stmt = $db->query($check_sql, [$schedule_id, $user_id]);
    $existing = $check_stmt->fetch();
    
    if (!$existing) {
        sendError('Schedule not found', 404);
    }
    
    // Build update query dynamically
    $update_fields = [];
    $update_params = [];
    
    if (!empty($name) && $name !== $existing['name']) {
        // Check if new name conflicts with existing schedules
        $name_check = $db->query("SELECT id FROM schedules WHERE user_id = ? AND name = ? AND id != ?", 
                                 [$user_id, $name, $schedule_id]);
        if ($name_check->fetch()) {
            sendError('A schedule with this name already exists');
        }
        $update_fields[] = "name = ?";
        $update_params[] = $name;
    }
    
    if ($description !== null) {
        $update_fields[] = "description = ?";
        $update_params[] = $description;
    }
    
    if ($semester !== null && in_array($semester, [1, 2])) {
        $update_fields[] = "semester = ?";
        $update_params[] = $semester;
    }
    
    if ($year !== null && $year >= 2024 && $year <= 2030) {
        $update_fields[] = "year = ?";
        $update_params[] = $year;
    }
    
    if (!empty($status) && in_array($status, ['draft', 'active', 'archived'])) {
        // If setting as active, deactivate other schedules
        if ($status === 'active' && $existing['status'] !== 'active') {
            $db->query("UPDATE schedules SET status = 'draft' WHERE user_id = ? AND status = 'active'", [$user_id]);
        }
        $update_fields[] = "status = ?";
        $update_params[] = $status;
    }
    
    if ($courses !== null) {
        $update_fields[] = "courses_data = ?";
        $update_params[] = json_encode($courses);
    }
    
    if (empty($update_fields)) {
        sendError('No valid fields to update');
    }
    
    // Add updated_at and schedule_id to params
    $update_fields[] = "updated_at = NOW()";
    $update_params[] = $schedule_id;
    
    $db->beginTransaction();
    
    try {
        // Update schedule
        $update_sql = "UPDATE schedules SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $db->query($update_sql, $update_params);
        
        // If courses were updated, update the schedule_courses table
        if ($courses !== null) {
            // Delete existing course entries
            $db->query("DELETE FROM schedule_courses WHERE schedule_id = ?", [$schedule_id]);
            
            // Insert new course entries
            if (!empty($courses)) {
                $course_sql = "INSERT INTO schedule_courses (schedule_id, course_code, day_of_week, start_time, end_time, venue, lecturer, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                foreach ($courses as $course) {
                    if (isset($course['code'], $course['day'], $course['time'])) {
                        // Parse time slot
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
        }
        
        $db->commit();
        
        // Get updated schedule
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
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError('Failed to update schedule: ' . $e->getMessage(), 500);
}