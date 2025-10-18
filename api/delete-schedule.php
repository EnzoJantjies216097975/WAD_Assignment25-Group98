<?php
// api/delete-schedule.php - Delete schedule from database
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

// Get schedule ID from request
$scheduleId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;

if ($scheduleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit();
}

$db = Database::getInstance();

try {
    // Verify that the schedule belongs to the logged-in user
    $checkStmt = $db->query(
        "SELECT user_id FROM schedules WHERE schedule_id = ?",
        [$scheduleId]
    );
    
    if ($checkStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
        exit();
    }
    
    $schedule = $checkStmt->fetch();
    
    if ($schedule['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this schedule']);
        exit();
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Option 1: Soft delete (set is_active to 0)
    $deleteStmt = $db->query(
        "UPDATE schedules SET is_active = 0, updated_at = NOW() WHERE schedule_id = ?",
        [$scheduleId]
    );
    
    // Option 2: Hard delete (actually remove from database)
    // Uncomment below if you want permanent deletion instead of soft delete
    /*
    // Delete schedule items first (due to foreign key constraint)
    $db->query("DELETE FROM schedule_items WHERE schedule_id = ?", [$scheduleId]);
    
    // Delete schedule versions
    $db->query("DELETE FROM schedule_versions WHERE schedule_id = ?", [$scheduleId]);
    
    // Delete the schedule
    $db->query("DELETE FROM schedules WHERE schedule_id = ?", [$scheduleId]);
    */
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    error_log("Error deleting schedule: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting schedule'
    ]);
}
?>
