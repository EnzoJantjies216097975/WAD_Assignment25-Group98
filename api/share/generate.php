<?php
// Generate Share Link API

require_once '../config/database.php';

setJsonHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    validateRequired($data, ['schedule_id']);
    
    $schedule_id = (int)$data['schedule_id'];
    $expires_in_days = isset($data['expires_in_days']) ? (int)$data['expires_in_days'] : 30;
    $allow_public = isset($data['allow_public']) ? (bool)$data['allow_public'] : true;
    
    // Validate expiry days (max 365 days)
    if ($expires_in_days < 1 || $expires_in_days > 365) {
        sendError('Expiry days must be between 1 and 365');
    }
    
    $db = getDB();
    
    // Check if schedule exists and belongs to user
    $check_sql = "SELECT id, name, status FROM schedules WHERE id = ? AND user_id = ?";
    $check_stmt = $db->query($check_sql, [$schedule_id, $user_id]);
    $schedule = $check_stmt->fetch();
    
    if (!$schedule) {
        sendError('Schedule not found', 404);
    }
    
    // Check if schedule is not archived
    if ($schedule['status'] === 'archived') {
        sendError('Cannot share archived schedules');
    }
    
    // Generate unique share token
    $share_token = 'sh_' . bin2hex(random_bytes(16));
    
    // Calculate expiry date
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in_days} days"));
    
    // Check if share link already exists for this schedule
    $existing_sql = "SELECT id, share_token, expires_at FROM schedule_shares WHERE schedule_id = ? AND created_by = ?";
    $existing_stmt = $db->query($existing_sql, [$schedule_id, $user_id]);
    $existing_share = $existing_stmt->fetch();
    
    $db->beginTransaction();
    
    try {
        if ($existing_share) {
            // Update existing share link
            $update_sql = "UPDATE schedule_shares 
                          SET share_token = ?, 
                              expires_at = ?, 
                              allow_public = ?, 
                              access_count = 0,
                              updated_at = NOW()
                          WHERE id = ?";
            
            $db->query($update_sql, [
                $share_token,
                $expires_at,
                $allow_public ? 1 : 0,
                $existing_share['id']
            ]);
            
            $share_id = $existing_share['id'];
        } else {
            // Create new share link
            $insert_sql = "INSERT INTO schedule_shares 
                          (schedule_id, created_by, share_token, expires_at, allow_public, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $db->query($insert_sql, [
                $schedule_id,
                $user_id,
                $share_token,
                $expires_at,
                $allow_public ? 1 : 0
            ]);
            
            $share_id = $db->lastInsertId();
        }
        
        // Update schedule with share token
        $db->query("UPDATE schedules SET share_token = ? WHERE id = ?", [$share_token, $schedule_id]);
        
        $db->commit();
        
        // Generate share URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $share_url = "{$protocol}://{$host}/shared/{$share_token}";
        
        sendResponse([
            'success' => true,
            'message' => 'Share link generated successfully',
            'share_data' => [
                'id' => $share_id,
                'schedule_id' => $schedule_id,
                'schedule_name' => $schedule['name'],
                'share_token' => $share_token,
                'share_url' => $share_url,
                'expires_at' => $expires_at,
                'expires_in_days' => $expires_in_days,
                'allow_public' => $allow_public,
                'access_count' => 0,
                'qr_code_url' => "{$protocol}://{$host}/api/share/qr.php?token={$share_token}"
            ]
        ], 201);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError('Failed to generate share link: ' . $e->getMessage(), 500);
}