<?php
// Dashboard Statistics API

require_once '../config/database.php';
require_once '../middleware/auth.php';

setJsonHeaders();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Check authentication
$user_id = requireAuth();

try {
    $db = getDB();
    
    // Get user's schedule statistics
    $schedule_stats = getUserScheduleStats($db, $user_id);
    
    // Get course enrollment statistics
    $course_stats = getUserCourseStats($db, $user_id);
    
    // Get recent activity
    $recent_activity = getUserRecentActivity($db, $user_id);
    
    // Get system-wide statistics (for comparison)
    $system_stats = getSystemStats($db);
    
    // Get upcoming deadlines or important dates
    $upcoming_events = getUpcomingEvents($db, $user_id);
    
    // Calculate trends
    $trends = calculateTrends($db, $user_id);
    
    sendResponse([
        'success' => true,
        'dashboard_data' => [
            'user_statistics' => [
                'schedules' => $schedule_stats,
                'courses' => $course_stats,
                'account_info' => [
                    'member_since' => getUserMemberSince($db, $user_id),
                    'last_login' => $_SESSION['last_activity'] ?? time(),
                    'total_logins' => getUserLoginCount($db, $user_id)
                ]
            ],
            'recent_activity' => $recent_activity,
            'upcoming_events' => $upcoming_events,
            'trends' => $trends,
            'system_overview' => $system_stats,
            'quick_actions' => getQuickActions($schedule_stats),
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Failed to load dashboard statistics: ' . $e->getMessage(), 500);
}

/**
 * Get user's schedule statistics
 */
function getUserScheduleStats($db, $user_id) {
    $sql = "SELECT 
                COUNT(*) as total_schedules,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_schedules,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_schedules,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_schedules,
                MAX(updated_at) as last_updated
            FROM schedules 
            WHERE user_id = ?";
    
    $stmt = $db->query($sql, [$user_id]);
    $stats = $stmt->fetch();
    
    // Get current semester schedule count
    $current_year = date('Y');
    $current_semester = date('n') <= 6 ? 1 : 2;
    
    $current_sql = "SELECT COUNT(*) as current_semester_schedules 
                   FROM schedules 
                   WHERE user_id = ? AND year = ? AND semester = ?";
    
    $current_stmt = $db->query($current_sql, [$user_id, $current_year, $current_semester]);
    $current_stats = $current_stmt->fetch();
    
    return [
        'total' => (int)$stats['total_schedules'],
        'active' => (int)$stats['active_schedules'],
        'draft' => (int)$stats['draft_schedules'],
        'archived' => (int)$stats['archived_schedules'],
        'current_semester' => (int)$current_stats['current_semester_schedules'],
        'last_updated' => $stats['last_updated']
    ];
}

/**
 * Get user's course statistics
 */
function getUserCourseStats($db, $user_id) {
    // Get unique courses from all schedules
    $sql = "SELECT 
                s.courses_data
            FROM schedules s
            WHERE s.user_id = ? AND s.status != 'archived'";
    
    $stmt = $db->query($sql, [$user_id]);
    $schedules = $stmt->fetchAll();
    
    $all_courses = [];
    $total_credits = 0;
    $departments = [];
    
    foreach ($schedules as $schedule) {
        $courses_data = json_decode($schedule['courses_data'] ?? '[]', true) ?: [];
        
        foreach ($courses_data as $course) {
            if (isset($course['code'])) {
                $all_courses[$course['code']] = true;
                
                if (isset($course['credits'])) {
                    $total_credits += (int)$course['credits'];
                }
                
                if (isset($course['department'])) {
                    $departments[$course['department']] = true;
                }
            }
        }
    }
    
    return [
        'unique_courses' => count($all_courses),
        'total_credits' => $total_credits,
        'departments' => array_keys($departments),
        'department_count' => count($departments)
    ];
}

/**
 * Get user's recent activity
 */
function getUserRecentActivity($db, $user_id) {
    // Get recent schedule updates
    $sql = "SELECT 
                name,
                status,
                updated_at,
                'schedule_update' as activity_type
            FROM schedules 
            WHERE user_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 5";
    
    $stmt = $db->query($sql, [$user_id]);
    $activities = $stmt->fetchAll();
    
    // Format activities
    foreach ($activities as &$activity) {
        $activity['time_ago'] = timeAgo(strtotime($activity['updated_at']));
        $activity['description'] = "Updated schedule '{$activity['name']}'";
    }
    
    return $activities;
}

/**
 * Get system-wide statistics for comparison
 */
function getSystemStats($db) {
    $stats_sql = "SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
                    (SELECT COUNT(*) FROM schedules) as total_schedules,
                    (SELECT COUNT(*) FROM courses WHERE status = 'active') as total_courses,
                    (SELECT COUNT(*) FROM schedule_shares WHERE expires_at > NOW()) as active_shares";
    
    $stmt = $db->query($stats_sql);
    $stats = $stmt->fetch();
    
    return [
        'total_users' => (int)$stats['total_users'],
        'total_schedules' => (int)$stats['total_schedules'],
        'total_courses' => (int)$stats['total_courses'],
        'active_shares' => (int)$stats['active_shares']
    ];
}

/**
 * Get upcoming events or deadlines
 */
function getUpcomingEvents($db, $user_id) {
    $events = [];
    
    // Add semester start/end dates (example)
    $current_year = date('Y');
    $events[] = [
        'title' => 'Semester 2 Registration',
        'date' => '2025-07-15',
        'type' => 'registration',
        'days_until' => daysUntil('2025-07-15')
    ];
    
    $events[] = [
        'title' => 'Semester 2 Classes Begin',
        'date' => '2025-08-01',
        'type' => 'academic',
        'days_until' => daysUntil('2025-08-01')
    ];
    
    // Filter out past events
    $events = array_filter($events, function($event) {
        return $event['days_until'] >= 0;
    });
    
    // Sort by days until
    usort($events, function($a, $b) {
        return $a['days_until'] - $b['days_until'];
    });
    
    return array_slice($events, 0, 3);
}

/**
 * Calculate trends
 */
function calculateTrends($db, $user_id) {
    // Get schedule creation trend (last 30 days vs previous 30 days)
    $recent_sql = "SELECT COUNT(*) as recent_count 
                  FROM schedules 
                  WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $previous_sql = "SELECT COUNT(*) as previous_count 
                    FROM schedules 
                    WHERE user_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $recent_stmt = $db->query($recent_sql, [$user_id]);
    $previous_stmt = $db->query($previous_sql, [$user_id]);
    
    $recent_count = $recent_stmt->fetch()['recent_count'];
    $previous_count = $previous_stmt->fetch()['previous_count'];
    
    $schedule_trend = $previous_count > 0 ? 
        (($recent_count - $previous_count) / $previous_count) * 100 : 
        ($recent_count > 0 ? 100 : 0);
    
    return [
        'schedule_creation' => [
            'percentage_change' => round($schedule_trend, 1),
            'direction' => $schedule_trend > 0 ? 'up' : ($schedule_trend < 0 ? 'down' : 'stable'),
            'recent_count' => (int)$recent_count,
            'previous_count' => (int)$previous_count
        ]
    ];
}

/**
 * Get quick actions based on user's current state
 */
function getQuickActions($schedule_stats) {
    $actions = [];
    
    if ($schedule_stats['total'] === 0) {
        $actions[] = [
            'title' => 'Create Your First Schedule',
            'description' => 'Get started by creating your first timetable',
            'action' => 'create_schedule',
            'priority' => 'high'
        ];
    } else {
        if ($schedule_stats['active'] === 0) {
            $actions[] = [
                'title' => 'Set Active Schedule',
                'description' => 'Mark one of your schedules as active',
                'action' => 'set_active',
                'priority' => 'medium'
            ];
        }
        
        if ($schedule_stats['draft'] > 0) {
            $actions[] = [
                'title' => 'Complete Draft Schedules',
                'description' => "You have {$schedule_stats['draft']} draft schedules to complete",
                'action' => 'complete_drafts',
                'priority' => 'low'
            ];
        }
    }
    
    $actions[] = [
        'title' => 'Browse Courses',
        'description' => 'Explore available courses for this semester',
        'action' => 'browse_courses',
        'priority' => 'low'
    ];
    
    return $actions;
}

/**
 * Helper functions
 */
function getUserMemberSince($db, $user_id) {
    $sql = "SELECT created_at FROM users WHERE id = ?";
    $stmt = $db->query($sql, [$user_id]);
    $user = $stmt->fetch();
    return $user['created_at'];
}

function getUserLoginCount($db, $user_id) {
    // This would require login tracking
    return 1; // Placeholder
}

function timeAgo($time) {
    $time_difference = time() - $time;
    
    if ($time_difference < 1) return 'just now';
    
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

function daysUntil($date) {
    $target = strtotime($date);
    $now = time();
    return floor(($target - $now) / (24 * 60 * 60));
}