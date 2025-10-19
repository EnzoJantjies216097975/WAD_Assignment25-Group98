<?php
/**
 * Courses API
 * University Timetable Manager
 */

require_once _DIR_ . '/../includes/db.php';
require_once _DIR_ . '/../includes/functions.php';

header('Content-Type: application/json');

// Get request data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

// Database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    sendError('Database connection failed', 500);
}

switch ($action) {
    case 'list':
        handleList($db);
        break;
        
    case 'get':
        handleGet($db, $_GET['id'] ?? $data['id'] ?? null);
        break;
        
    case 'search':
        handleSearch($db, $_GET['q'] ?? '');
        break;
        
    case 'filter':
        handleFilter($db, $_GET);
        break;
        
    case 'create':
        handleCreate($db, $data);
        break;
        
    case 'update':
        handleUpdate($db, $data);
        break;
        
    case 'delete':
        handleDelete($db, $data['id'] ?? null);
        break;
        
    default:
        sendError('Invalid action', 400);
}

/**
 * Get all courses
 */
function handleList($db) {
    $query = "SELECT course_id, course_code, course_name, lecturer, 
              department, credits, description 
              FROM courses 
              ORDER BY course_code";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    
    sendResponse(true, ['courses' => $courses]);
}

/**
 * Get single course
 */
function handleGet($db, $courseId) {
    if (!$courseId) {
        sendError('Course ID required', 400);
    }
    
    $query = "SELECT course_id, course_code, course_name, lecturer, 
              department, credits, description 
              FROM courses 
              WHERE course_id = :course_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        sendError('Course not found', 404);
    }
    
    $course = $stmt->fetch();
    
    sendResponse(true, ['course' => $course]);
}

/**
 * Search courses
 */
function handleSearch($db, $query) {
    if (empty($query)) {
        handleList($db);
        return;
    }
    
    $searchTerm = "%{$query}%";
    
    $sql = "SELECT course_id, course_code, course_name, lecturer, 
            department, credits, description 
            FROM courses 
            WHERE course_code LIKE :search 
            OR course_name LIKE :search 
            OR lecturer LIKE :search
            ORDER BY course_code";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':search', $searchTerm);
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    
    sendResponse(true, ['courses' => $courses]);
}

/**
 * Filter courses
 */
function handleFilter($db, $filters) {
    $conditions = [];
    $params = [];
    
    if (isset($filters['department']) && !empty($filters['department'])) {
        $conditions[] = "department = :department";
        $params[':department'] = $filters['department'];
    }
    
    if (isset($filters['credits']) && !empty($filters['credits'])) {
        $conditions[] = "credits = :credits";
        $params[':credits'] = $filters['credits'];
    }
    
    if (isset($filters['lecturer']) && !empty($filters['lecturer'])) {
        $conditions[] = "lecturer LIKE :lecturer";
        $params[':lecturer'] = "%{$filters['lecturer']}%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";
    
    $query = "SELECT course_id, course_code, course_name, lecturer, 
              department, credits, description 
              FROM courses 
              {$whereClause}
              ORDER BY course_code";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    
    sendResponse(true, ['courses' => $courses]);
}

/**
 * Create new course
 */
function handleCreate($db, $data) {
    // Check authentication for admin actions
    if (!isLoggedIn()) {
        sendError('Not authenticated', 401);
    }
    
    if (!isset($data['course'])) {
        sendError('Course data required', 400);
    }
    
    $course = $data['course'];
    
    $requiredFields = ['code', 'name'];
    $missingFields = validateRequiredFields($course, $requiredFields);
    
    if (!empty($missingFields)) {
        sendError('Missing required fields: ' . implode(', ', $missingFields), 400);
    }
    
    $courseCode = sanitizeInput($course['code']);
    $courseName = sanitizeInput($course['name']);
    $lecturer = sanitizeInput($course['lecturer'] ?? '');
    $department = sanitizeInput($course['department'] ?? '');
    $credits = intval($course['credits'] ?? 3);
    $description = sanitizeInput($course['description'] ?? '');
    
    // Check if course code already exists
    $query = "SELECT course_id FROM courses WHERE course_code = :course_code";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_code', $courseCode);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        sendError('Course code already exists', 409);
    }
    
    // Insert course
    $query = "INSERT INTO courses 
              (course_code, course_name, lecturer, department, credits, description) 
              VALUES (:course_code, :course_name, :lecturer, :department, :credits, :description)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_code', $courseCode);
    $stmt->bindParam(':course_name', $courseName);
    $stmt->bindParam(':lecturer', $lecturer);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':credits', $credits);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $courseId = $db->lastInsertId();
        sendResponse(true, ['course_id' => $courseId], 'Course created successfully', 201);
    } else {
        sendError('Failed to create course', 500);
    }
}

/**
 * Update existing course
 */
function handleUpdate($db, $data) {
    // Check authentication for admin actions
    if (!isLoggedIn()) {
        sendError('Not authenticated', 401);
    }
    
    if (!isset($data['id']) || !isset($data['course'])) {
        sendError('Course ID and data required', 400);
    }
    
    $courseId = $data['id'];
    $course = $data['course'];
    
    // Check if course exists
    $query = "SELECT course_id FROM courses WHERE course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        sendError('Course not found', 404);
    }
    
    // Build update query
    $updates = [];
    $params = [':course_id' => $courseId];
    
    if (isset($course['code'])) {
        $updates[] = "course_code = :course_code";
        $params[':course_code'] = sanitizeInput($course['code']);
    }
    
    if (isset($course['name'])) {
        $updates[] = "course_name = :course_name";
        $params[':course_name'] = sanitizeInput($course['name']);
    }
    
    if (isset($course['lecturer'])) {
        $updates[] = "lecturer = :lecturer";
        $params[':lecturer'] = sanitizeInput($course['lecturer']);
    }
    
    if (isset($course['department'])) {
        $updates[] = "department = :department";
        $params[':department'] = sanitizeInput($course['department']);
    }
    
    if (isset($course['credits'])) {
        $updates[] = "credits = :credits";
        $params[':credits'] = intval($course['credits']);
    }
    
    if (isset($course['description'])) {
        $updates[] = "description = :description";
        $params[':description'] = sanitizeInput($course['description']);
    }
    
    if (empty($updates)) {
        sendError('No fields to update', 400);
    }
    
    $query = "UPDATE courses SET " . implode(', ', $updates) . " WHERE course_id = :course_id";
    
    $stmt = $db->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if ($stmt->execute()) {
        sendResponse(true, null, 'Course updated successfully');
    } else {
        sendError('Failed to update course', 500);
    }
}

/**
 * Delete course
 */
function handleDelete($db, $courseId) {
    // Check authentication for admin actions
    if (!isLoggedIn()) {
        sendError('Not authenticated', 401);
    }
    
    if (!$courseId) {
        sendError('Course ID required', 400);
    }
    
    // Check if course exists
    $query = "SELECT course_id FROM courses WHERE course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        sendError('Course not found', 404);
    }
    
    // Delete course
    $query = "DELETE FROM courses WHERE course_id = :course_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    
    if ($stmt->execute()) {
        sendResponse(true, null, 'Course deleted successfully');
    } else {
        sendError('Failed to delete course', 500);
    }
}
?>
