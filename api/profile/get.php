<?php
// Get User Profile API

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
    
    // Get user profile information
    $sql = "SELECT 
                id,
                student_number,
                full_name,
                email,
                program,
                year_level,
                profile_picture,
                preferences,
                created_at,
                last_login,
                status
            FROM users 
            WHERE id = ?";
    
    $stmt = $db->query($sql, [$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User profile not found', 404);
    }
    
    // Parse preferences JSON
    $user['preferences'] = json_decode($user['preferences'] ?? '{}', true) ?: [];
    
    // Set default preferences if not set
    $default_preferences = [
        'default_semester' => 2,
        'default_year' => date('Y'),
        'theme' => 'light',
        'notifications' => [
            'email_notifications' => true,
            'conflict_alerts' => true,
            'schedule_reminders' => true
        ],
        'privacy' => [
            'allow_schedule_sharing' => true,
            'show_profile_to_others' => false
        ]
    ];
    
    $user['preferences'] = array_merge($default_preferences, $user['preferences']);
    
    // Convert numeric fields
    $user['year_level'] = (int)$user['year_level'];
    
    // Get user statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_schedules,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_schedules,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_schedules,
                    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_schedules
                  FROM schedules 
                  WHERE user_id = ?";
    
    $stats_stmt = $db->query($stats_sql, [$user_id]);
    $stats = $stats_stmt->fetch();
    
    $user['statistics'] = [
        'total_schedules' => (int)$stats['total_schedules'],
        'active_schedules' => (int)$stats['active_schedules'],
        'draft_schedules' => (int)$stats['draft_schedules'],
        'archived_schedules' => (int)$stats['archived_schedules']
    ];
    
    // Get recent activity (last 5 schedule updates)
    $activity_sql = "SELECT 
                        name,
                        status,
                        updated_at
                     FROM schedules 
                     WHERE user_id = ? 
                     ORDER BY updated_at DESC 
                     LIMIT 5";
    
    $activity_stmt = $db->query($activity_sql, [$user_id]);
    $user['recent_activity'] = $activity_stmt->fetchAll();
    
    // Calculate account age
    $created_time = strtotime($user['created_at']);
    $user['account_age_days'] = floor((time() - $created_time) / (24 * 60 * 60));
    
    // Generate initials for avatar
    $name_parts = explode(' ', $user['full_name']);
    $user['initials'] = strtoupper(
        (isset($name_parts[0]) ? $name_parts[0][0] : '') . 
        (isset($name_parts[1]) ? $name_parts[1][0] : '')
    );
    
    // Remove sensitive fields
    unset($user['password_hash']);
    
    sendResponse([
        'success' => true,
        'profile' => $user
    ]);
    
} catch (Exception $e) {
    sendError('Failed to fetch profile: ' . $e->getMessage(), 500);
}