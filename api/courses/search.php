<?php
// Course Search API

require_once '../config/database.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

try {
    $db = getDB();
    
    $query = $_GET['q'] ?? '';
    $department = $_GET['department'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $year_level = $_GET['year_level'] ?? '';
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
    
    if (empty($query) && empty($department)) {
        sendError('Search query or department filter is required');
    }
    
    // Build search conditions
    $where_conditions = [];
    $params = [];
    
    if (!empty($query)) {
        $search_term = "%{$query}%";
        $where_conditions[] = "(
            code LIKE ? OR 
            name LIKE ? OR 
            description LIKE ? OR 
            lecturers LIKE ?
        )";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
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
    
    // Only active courses
    $where_conditions[] = "status = 'active'";
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Search courses
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
                schedule_times
            FROM courses 
            $where_clause 
            ORDER BY 
                CASE 
                    WHEN code LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END,
                department, 
                year_level, 
                code 
            LIMIT ?";
    
    // Add parameters for ORDER BY LIKE conditions
    $order_params = [];
    if (!empty($query)) {
        $order_params[] = "%{$query}%";
        $order_params[] = "%{$query}%";
    } else {
        $order_params[] = '';
        $order_params[] = '';
    }
    
    $all_params = array_merge($params, $order_params, [$limit]);
    
    $stmt = $db->query($sql, $all_params);
    $courses = $stmt->fetchAll();
    
    // Format course data
    foreach ($courses as &$course) {
        // Parse JSON fields
        $course['lecturers'] = json_decode($course['lecturers'] ?? '[]', true) ?: [];
        
        if ($course['schedule_times']) {
            $course['schedule_times'] = json_decode($course['schedule_times'], true);
        }
        
        if ($course['prerequisites']) {
            $course['prerequisites'] = json_decode($course['prerequisites'], true);
        }
        
        // Convert numeric fields
        $course['credits'] = (int)$course['credits'];
        $course['nqf_level'] = (int)$course['nqf_level'];
        $course['semester'] = (int)$course['semester'];
        $course['year_level'] = (int)$course['year_level'];
        
        // Add relevance score if searching
        if (!empty($query)) {
            $relevance = 0;
            $lower_query = strtolower($query);
            $lower_code = strtolower($course['code']);
            $lower_name = strtolower($course['name']);
            
            // Exact matches get highest score
            if ($lower_code === $lower_query) {
                $relevance = 100;
            } elseif (strpos($lower_code, $lower_query) === 0) {
                $relevance = 90;
            } elseif (strpos($lower_name, $lower_query) === 0) {
                $relevance = 80;
            } elseif (strpos($lower_code, $lower_query) !== false) {
                $relevance = 70;
            } elseif (strpos($lower_name, $lower_query) !== false) {
                $relevance = 60;
            } else {
                $relevance = 50;
            }
            
            $course['relevance'] = $relevance;
        }
    }
    
    // Sort by relevance if searching
    if (!empty($query)) {
        usort($courses, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
    }
    
    // Get departments for filter suggestions
    $dept_sql = "SELECT DISTINCT department FROM courses WHERE status = 'active' ORDER BY department";
    $dept_stmt = $db->query($dept_sql);
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    sendResponse([
        'success' => true,
        'data' => $courses,
        'total_results' => count($courses),
        'search_query' => $query,
        'filters_applied' => [
            'department' => $department,
            'semester' => $semester,
            'year_level' => $year_level
        ],
        'suggestions' => [
            'departments' => $departments
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Search failed: ' . $e->getMessage(), 500);
}