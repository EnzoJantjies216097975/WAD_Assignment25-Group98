<?php
// api/save-schedule.php - Save timetable to database
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

// Get JSON data from request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!$data || !isset($data['name']) || !isset($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit();
}

$db = Database::getInstance();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Check if schedule with same name exists for this user
    $checkStmt = $db->query(
        "SELECT schedule_id FROM schedules WHERE user_id = ? AND schedule_name = ? AND is_active = 1",
        [$_SESSION['user_id'], $data['name']]
    );
    
    if ($checkStmt->rowCount() > 0) {
        // Update existing schedule
        $existing = $checkStmt->fetch();
        $scheduleId = $existing['schedule_id'];
        
        // Delete existing schedule items
        $db->query(
            "DELETE FROM schedule_items WHERE schedule_id = ?",
            [$scheduleId]
        );
        
        // Update schedule timestamp
        $db->query(
            "UPDATE schedules SET updated_at = NOW() WHERE schedule_id = ?",
            [$scheduleId]
        );
    } else {
        // Create new schedule
        $shareToken = bin2hex(random_bytes(16));
        
        $insertStmt = $db->query(
            "INSERT INTO schedules (user_id, schedule_name, semester, year, share_token) 
             VALUES (?, ?, ?, ?, ?)",
            [
                $_SESSION['user_id'],
                $data['name'],
                $data['semester'] ?? 2,
                $data['year'] ?? 2025,
                $shareToken
            ]
        );
        
        $scheduleId = $db->lastInsertId();
    }
    
    // Insert schedule items
    foreach ($data['items'] as $item) {
        // Map day number to day name
        $days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $dayName = $days[$item['day']] ?? 'Monday';
        
        // Get slot_id from time_slots table
        $slotStmt = $db->query(
            "SELECT slot_id FROM time_slots WHERE day_of_week = ? AND start_time = ?",
            [$dayName, $item['time'] . ':00']
        );
        
        if ($slotStmt->rowCount() == 0) {
            throw new Exception("Invalid time slot: {$dayName} {$item['time']}");
        }
        
        $slot = $slotStmt->fetch();
        
        // Insert schedule item
        $db->query(
            "INSERT INTO schedule_items 
             (schedule_id, course_id, slot_id, venue_id, class_type, duration, lecturer_name, notes) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $scheduleId,
                $item['course_id'],
                $slot['slot_id'],
                $item['venue_id'] ?? null,
                $item['class_type'],
                $item['duration'],
                $item['lecturer'] ?? '',
                $item['notes'] ?? ''
            ]
        );
        
        // For practical classes (2 hours), also block the next slot
        if ($item['duration'] == 2) {
            // Get next time slot
            $nextTimeMap = [
                '07:30' => '08:30',
                '08:30' => '09:30',
                '09:30' => '10:30',
                '10:30' => '11:30',
                '11:30' => '12:30',
                '12:30' => '13:30',
                '14:00' => '15:00',
                '17:15' => '18:40',
                '18:40' => '20:00'
            ];
            
            if (isset($nextTimeMap[$item['time']])) {
                $nextTime = $nextTimeMap[$item['time']];
                
                $nextSlotStmt = $db->query(
                    "SELECT slot_id FROM time_slots WHERE day_of_week = ? AND start_time = ?",
                    [$dayName, $nextTime . ':00']
                );
                
                if ($nextSlotStmt->rowCount() > 0) {
                    $nextSlot = $nextSlotStmt->fetch();
                    
                    // Insert continuation slot
                    $db->query(
                        "INSERT INTO schedule_items 
                         (schedule_id, course_id, slot_id, venue_id, class_type, duration, lecturer_name, notes) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $scheduleId,
                            $item['course_id'],
                            $nextSlot['slot_id'],
                            $item['venue_id'] ?? null,
                            'practical_cont',
                            0, // Continuation marker
                            $item['lecturer'] ?? '',
                            'Continuation of practical class'
                        ]
                    );
                }
            }
        }
    }
    
    // Save a version for history
    $versionData = json_encode($data);
    $db->query(
        "INSERT INTO schedule_versions (schedule_id, version_number, version_data) 
         SELECT ?, COALESCE(MAX(version_number), 0) + 1, ? 
         FROM schedule_versions WHERE schedule_id = ?",
        [$scheduleId, $versionData, $scheduleId]
    );
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Schedule saved successfully',
        'schedule_id' => $scheduleId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    error_log("Error saving schedule: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving schedule: ' . $e->getMessage()
    ]);
}
?>