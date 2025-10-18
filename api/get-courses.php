<?php
// api/get-courses.php - Get available courses for timetable
session_start();
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();

try {
    // Get filter parameters
    $yearLevel = isset($_GET['year']) ? intval($_GET['year']) : null;
    $semester = isset($_GET['semester']) ? intval($_GET['semester']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query
    $sql = "SELECT 
                course_id,
                course_code,
                course_name,
                department,
                credits,
                year_level,
                semester,
                theory_lecturer,
                practical_lecturer,
                color_code
            FROM courses 
            WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if ($yearLevel !== null) {
        $sql .= " AND year_level = ?";
        $params[] = $yearLevel;
    }
    
    if ($semester !== null) {
        $sql .= " AND semester = ?";
        $params[] = $semester;
    }
    
    if (!empty($search)) {
        $sql .= " AND (course_code LIKE ? OR course_name LIKE ? OR theory_lecturer LIKE ? OR practical_lecturer LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY year_level, course_code";
    
    // Execute query
    $stmt = $db->query($sql, $params);
    $courses = $stmt->fetchAll();
    
    // Format response
    $formattedCourses = [];
    foreach ($courses as $course) {
        $formattedCourses[] = [
            'id' => $course['course_id'],
            'code' => $course['course_code'],
            'name' => $course['course_name'],
            'department' => $course['department'],
            'credits' => $course['credits'],
            'year' => $course['year_level'],
            'semester' => $course['semester'],
            'theory_lecturer' => $course['theory_lecturer'],
            'practical_lecturer' => $course['practical_lecturer'],
            'color' => $course['color_code'],
            'has_theory' => true,
            'has_practical' => true
        ];
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $formattedCourses,
        'total' => count($formattedCourses)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching courses'
    ]);
}
?>