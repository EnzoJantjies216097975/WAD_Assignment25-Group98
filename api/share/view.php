<?php
// View Shared Schedule API

require_once '../config/database.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $share_token = $_GET['token'] ?? '';
    
    if (empty($share_token)) {
        sendError('Share token is required');
    }
    
    $db = getDB();
    
    // Get share information and schedule
    $sql = "SELECT 
                ss.id as share_id,
                ss.schedule_id,
                ss.share_token,
                ss.expires_at,
                ss.allow_public,
                ss.access_count,
                ss.created_by,
                s.name as schedule_name,
                s.description as schedule_description,
                s.semester,
                s.year,
                s.status,
                s.courses_data,
                s.created_at as schedule_created_at,
                u.full_name as owner_name,
                u.program as owner_program
            FROM schedule_shares ss
            JOIN schedules s ON ss.schedule_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE ss.share_token = ?";
    
    $stmt = $db->query($sql, [$share_token]);
    $share_data = $stmt->fetch();
    
    if (!$share_data) {
        sendError('Invalid or expired share link', 404);
    }
    
    // Check if share link has expired
    if (strtotime($share_data['expires_at']) < time()) {
        sendError('This share link has expired', 410);
    }
    
    // Check if public access is allowed
    if (!$share_data['allow_public']) {
        // For now, we'll allow access. In a more complex system,
        // you might require authentication or specific permissions
    }
    
    // Increment access count
    $db->query("UPDATE schedule_shares SET access_count = access_count + 1 WHERE id = ?", 
               [$share_data['share_id']]);
    
    // Parse courses data
    $courses_data = json_decode($share_data['courses_data'] ?? '[]', true) ?: [];
    
    // Get detailed course information if course codes are available
    $detailed_courses = [];
    $course_codes = [];
    
    foreach ($courses_data as $course) {
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
                        venue
                       FROM courses 
                       WHERE code IN ($placeholders)";
        
        $course_stmt = $db->query($course_sql, $course_codes);
        $course_details = $course_stmt->fetchAll();
        
        // Index by course code for easy lookup
        $course_index = [];
        foreach ($course_details as $course) {
            $course['lecturers'] = json_decode($course['lecturers'] ?? '[]', true);
            $course['credits'] = (int)$course['credits'];
            $course['nqf_level'] = (int)$course['nqf_level'];
            $course_index[$course['code']] = $course;
        }
        
        // Merge schedule course data with detailed course info
        foreach ($courses_data as &$schedule_course) {
            if (isset($course_index[$schedule_course['code']])) {
                $schedule_course = array_merge($course_index[$schedule_course['code']], $schedule_course);
            }
        }
    }
    
    // Calculate statistics
    $total_credits = 0;
    $course_count = count($courses_data);
    $departments = [];
    
    foreach ($courses_data as $course) {
        if (isset($course['credits'])) {
            $total_credits += (int)$course['credits'];
        }
        if (isset($course['department'])) {
            $departments[$course['department']] = true;
        }
    }
    
    $statistics = [
        'total_courses' => $course_count,
        'total_credits' => $total_credits,
        'departments' => array_keys($departments),
        'department_count' => count($departments)
    ];
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'shared_schedule' => [
            'share_token' => $share_data['share_token'],
            'schedule_name' => $share_data['schedule_name'],
            'schedule_description' => $share_data['schedule_description'],
            'semester' => (int)$share_data['semester'],
            'year' => (int)$share_data['year'],
            'courses' => $courses_data,
            'statistics' => $statistics,
            'owner_info' => [
                'name' => $share_data['owner_name'],
                'program' => $share_data['owner_program']
            ],
            'share_info' => [
                'expires_at' => $share_data['expires_at'],
                'access_count' => (int)$share_data['access_count'] + 1,
                'is_public' => (bool)$share_data['allow_public']
            ]
        ],
        'view_only' => true
    ];
    
    sendResponse($response_data);
    
} catch (Exception $e) {
    sendError('Failed to load shared schedule: ' . $e->getMessage(), 500);
}