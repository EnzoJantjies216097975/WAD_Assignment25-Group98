<?php
// Get Single Schedule API

require_once '../config/database.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    sendError('Authentication required', 401);
}

try {
    $user_id = $_SESSION['user_id'];
    $schedule_id = $_GET['id'] ?? '';
    
    if (empty($schedule_id)) {
        sendError('Schedule ID is required');
    }
    
    $db = getDB();
    
    // Get schedule details
    $sql = "SELECT 
                s.id,
                s.user_id,
                s.name,
                s.description,
                s.semester,
                s.year,
                s.status,
                s.courses_data,
                s.share_token,
                s.created_at,
                s.updated_at,
                u.full_name as owner_name
            FROM schedules s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.user_id = ?";
    
    $stmt = $db->query($sql, [$schedule_id, $user_id]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        sendError('Schedule not found', 404);
    }
    
    // Parse courses data
    $schedule['courses_data'] = json_decode($schedule['courses_data'] ?? '[]', true) ?: [];
    
    // Convert numeric fields
    $schedule['semester'] = (int)$schedule['semester'];
    $schedule['year'] = (int)$schedule['year'];
    
    // Get detailed course information
    $detailed_courses = [];
    $course_codes = [];
    
    foreach ($schedule['courses_data'] as $course) {
        if (isset($course['code'])) {
            $course_codes[] = $course['code'];
        }
    }
    
    if (!empty($course_codes)) {
        $placeholders = str_repeat('?,', count($course_codes) - 1) . '?';
        $course_sql = "SELECT 
                        code,
                        name,
                        credits,
                        nqf_level,
                        department,
                        lecturers,
                        description,
                        venue,
                        prerequisites
                       FROM courses 
                       WHERE code IN ($placeholders)";
        
        $course_stmt = $db->query($course_sql, $course_codes);
        $course_details = $course_stmt->fetchAll();
        
        // Index by course code for easy lookup
        $course_index = [];
        foreach ($course_details as $course) {
            $course['lecturers'] = json_decode($course['lecturers'] ?? '[]', true);
            $course['prerequisites'] = json_decode($course['prerequisites'] ?? '[]', true);
            $course['credits'] = (int)$course['credits'];
            $course['nqf_level'] = (int)$course['nqf_level'];
            $course_index[$course['code']] = $course;
        }
        
        // Merge schedule course data with detailed course info
        foreach ($schedule['courses_data'] as &$schedule_course) {
            if (isset($course_index[$schedule_course['code']])) {
                $schedule_course = array_merge($course_index[$schedule_course['code']], $schedule_course);
            }
        }
    }
    
    // Calculate statistics
    $total_credits = 0;
    $course_count = count($schedule['courses_data']);
    $departments = [];
    
    foreach ($schedule['courses_data'] as $course) {
        if (isset($course['credits'])) {
            $total_credits += (int)$course['credits'];
        }
        if (isset($course['department'])) {
            $departments[$course['department']] = true;
        }
    }
    
    $schedule['statistics'] = [
        'total_courses' => $course_count,
        'total_credits' => $total_credits,
        'departments' => array_keys($departments),
        'department_count' => count($departments)
    ];
    
    // Check for conflicts (simplified version)
    $conflicts = [];
    $time_slots = [];
    
    foreach ($schedule['courses_data'] as $index => $course) {
        if (isset($course['day'], $course['time'])) {
            $slot_key = $course['day'] . '_' . $course['time'];
            if (isset($time_slots[$slot_key])) {
                $conflicts[] = [
                    'type' => 'time_overlap',
                    'courses' => [$time_slots[$slot_key], $index],
                    'message' => "Time conflict between {$time_slots[$slot_key]['name']} and {$course['name']}"
                ];
            }
            $time_slots[$slot_key] = array_merge($course, ['index' => $index]);
        }
    }
    
    $schedule['conflicts'] = $conflicts;
    $schedule['has_conflicts'] = !empty($conflicts);
    
    // Add sharing info
    $schedule['sharing'] = [
        'is_shareable' => !empty($schedule['share_token']),
        'share_url' => !empty($schedule['share_token']) ? 
            getShareUrl($schedule['share_token']) : null
    ];
    
    sendResponse([
        'success' => true,
        'schedule' => $schedule
    ]);
    
} catch (Exception $e) {
    sendError('Failed to fetch schedule: ' . $e->getMessage(), 500);
}

// Generate share URL
function getShareUrl($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "{$protocol}://{$host}/shared/{$token}";
}