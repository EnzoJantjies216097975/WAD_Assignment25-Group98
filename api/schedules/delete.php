<?php
// Delete Schedule API

require_once '../config/database.php';

setJsonHeaders();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    
    $schedule_id = (int)$schedule_id;
    
    $db = getDB();
    
    // Check if schedule exists and belongs to user
    $check_sql = "SELECT id, name, status FROM schedules WHERE id = ? AND user_id = ?";
    $check_stmt = $db->query($check_sql, [$schedule_id, $user_id]);
    $schedule = $check_stmt->fetch();
    
    if (!$schedule) {
        sendError('Schedule not found', 404);
    }
    
    $db->beginTransaction();
    
    try {
        // Delete related course entries first (foreign key constraint)
        $db->query("DELETE FROM schedule_courses WHERE schedule_id = ?", [$schedule_id]);
        
        // Delete the schedule
        $delete_sql = "DELETE FROM schedules WHERE id = ? AND user_id = ?";
        $stmt = $db->query($delete_sql, [$schedule_id, $user_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to delete schedule');
        }
        
        $db->commit();
        
        sendResponse([
            'success' => true,
            'message' => "Schedule '{$schedule['name']}' deleted successfully",
            'deleted_schedule' => [
                'id' => $schedule['id'],
                'name' => $schedule['name'],
                'status' => $schedule['status']
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError('Failed to delete schedule: ' . $e->getMessage(), 500);
}