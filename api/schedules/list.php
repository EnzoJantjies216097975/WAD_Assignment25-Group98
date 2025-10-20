<?php
// List User Schedules API

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
    $db = getDB();
    
    // Get query parameters
    $status = $_GET['status'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $year = $_GET['year'] ?? '';
    $sort = $_GET['sort'] ?? 'updated_at';
    $order = $_GET['order'] ?? 'DESC';
    
    // Build WHERE clause
    $where_conditions = ['user_id = ?'];
    $params = [$user_id];
    
    if (!empty($status)) {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }
    
    if (!empty($semester)) {
        $where_conditions[] = "semester = ?";
        $params[] = (int)$semester;
    }
    
    if (!empty($year)) {
        $where_conditions[] = "year = ?";
        $params[] = (int)$year;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Validate sort parameters
    $allowed_sorts = ['name', 'created_at', 'updated_at', 'semester', 'year', 'status'];
    $allowed_orders = ['ASC', 'DESC'];
    
    if (!in_array($sort, $allowed_sorts)) {
        $sort = 'updated_at';
    }
    
    if (!in_array(strtoupper($order), $allowed_orders)) {
        $order = 'DESC';
    }
    
    // Get schedules
    $sql = "SELECT 
                s.id,
                s.name,
                s.description,
                s.semester,
                s.year,
                s.status,
                s.courses_data,
                s.created_at,
                s.updated_at,
                COUNT(sc.id) as course_count
            FROM schedules s
            LEFT JOIN schedule_courses sc ON s.id = sc.schedule_id
            $where_clause
            GROUP BY s.id
            ORDER BY s.$sort $order";
    
    $stmt = $db->query($sql, $params);
    $schedules = $stmt->fetchAll();
    
    // Process schedules data
    foreach ($schedules as &$schedule) {
        // Parse courses data
        $courses_data = json_decode($schedule['courses_data'] ?? '[]', true) ?: [];
        $schedule['courses_data'] = $courses_data;
        
        // Convert numeric fields
        $schedule['semester'] = (int)$schedule['semester'];
        $schedule['year'] = (int)$schedule['year'];
        $schedule['course_count'] = (int)$schedule['course_count'];
        
        // Calculate total credits
        $total_credits = 0;
        foreach ($courses_data as $course) {
            if (isset($course['credits'])) {
                $total_credits += (int)$course['credits'];
            }
        }
        $schedule['total_credits'] = $total_credits;
        
        // Add time ago for last updated
        $updated_time = strtotime($schedule['updated_at']);
        $schedule['updated_ago'] = timeAgo($updated_time);
        
        // Add status badge info
        $schedule['status_info'] = [
            'label' => ucfirst($schedule['status']),
            'color' => getStatusColor($schedule['status'])
        ];
    }
    
    // Get statistics
    $stats_sql = "SELECT 
                    status, 
                    COUNT(*) as count 
                  FROM schedules 
                  WHERE user_id = ? 
                  GROUP BY status";
    $stats_stmt = $db->query($stats_sql, [$user_id]);
    $stats = $stats_stmt->fetchAll();
    
    $statistics = [
        'total' => 0,
        'active' => 0,
        'draft' => 0,
        'archived' => 0
    ];
    
    foreach ($stats as $stat) {
        $statistics[$stat['status']] = (int)$stat['count'];
        $statistics['total'] += (int)$stat['count'];
    }
    
    sendResponse([
        'success' => true,
        'data' => $schedules,
        'statistics' => $statistics,
        'filters_applied' => [
            'status' => $status,
            'semester' => $semester,
            'year' => $year,
            'sort' => $sort,
            'order' => $order
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Failed to fetch schedules: ' . $e->getMessage(), 500);
}

// Helper function to calculate time ago
function timeAgo($time) {
    $time_difference = time() - $time;
    
    if ($time_difference < 1) {
        return 'just now';
    }
    
    $condition = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
    
    return 'just now';
}

// Helper function to get status color
function getStatusColor($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'draft':
            return 'warning';
        case 'archived':
            return 'secondary';
        default:
            return 'primary';
    }
}