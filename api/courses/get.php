<?php
// Get Courses API

require_once '../config/database.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $db = getDB();
    
    // Get query parameters
    $department = $_GET['department'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $year_level = $_GET['year_level'] ?? '';
    $credits = $_GET['credits'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($department)) {
        $where_conditions[] = "department = ?";
        $params[] = $department;
    }
    
    if (!empty($semester)) {
        $where_conditions[] = "semester = ?";
        $params[] = (int)$semester;
    }
    
    if (!empty($year_level)) {
        $where_conditions[] = "year_level = ?";
        $params[] = (int)$year_level;
    }
    
    if (!empty($credits)) {
        $where_conditions[] = "credits = ?";
        $params[] = (int)$credits;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(code LIKE ? OR name LIKE ? OR lecturers LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM courses $where_clause";
    $count_stmt = $db->query($count_sql, $params);
    $total = $count_stmt->fetch()['total'];
    
    // Get courses with pagination
    $sql = "SELECT 
                id,
                code,
                name,
                credits,
                nqf_level,
                department,
                semester,
                year_level,
                lecturers,
                description,
                prerequisites,
                venue,
                schedule_times,
                status,
                created_at,
                updated_at
            FROM courses 
            $where_clause 
            ORDER BY department, year_level, code 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->query($sql, $params);
    $courses = $stmt->fetchAll();
    
    // Parse JSON fields and format data
    foreach ($courses as &$course) {
        // Parse lecturers JSON
        $course['lecturers'] = json_decode($course['lecturers'] ?? '[]', true) ?: [];
        
        // Parse schedule times if exists
        if ($course['schedule_times']) {
            $course['schedule_times'] = json_decode($course['schedule_times'], true);
        }
        
        // Parse prerequisites if exists
        if ($course['prerequisites']) {
            $course['prerequisites'] = json_decode($course['prerequisites'], true);
        }
        
        // Convert numeric fields
        $course['credits'] = (int)$course['credits'];
        $course['nqf_level'] = (int)$course['nqf_level'];
        $course['semester'] = (int)$course['semester'];
        $course['year_level'] = (int)$course['year_level'];
    }
    
    // Calculate pagination info
    $total_pages = ceil($total / $limit);
    $has_next = $page < $total_pages;
    $has_prev = $page > 1;
    
    sendResponse([
        'success' => true,
        'data' => $courses,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => (int)$total,
            'items_per_page' => $limit,
            'has_next' => $has_next,
            'has_prev' => $has_prev
        ],
        'filters_applied' => [
            'department' => $department,
            'semester' => $semester,
            'year_level' => $year_level,
            'credits' => $credits,
            'search' => $search
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Failed to fetch courses: ' . $e->getMessage(), 500);
}