<?php
// api/load-schedule.php - Load timetable from database
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
$scheduleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($scheduleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit();
}

$db = Database::getInstance();

try {
    // Fetch schedule details
    $scheduleStmt = $db->query(
        "SELECT s.*, u.first_name, u.last_name 
         FROM schedules s 
         JOIN users u ON s.user_id = u.user_id 
         WHERE s.schedule_id = ? AND (s.user_id = ? OR s.share_token IS NOT NULL)",
        [$scheduleId, $_SESSION['user_id']]
    );
    
    if ($scheduleStmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule not found or access denied']);
        exit();
    }
    
    $schedule = $scheduleStmt->fetch();
    
    // Fetch schedule items with course and venue details
    $itemsStmt = $db->query(
        "SELECT 
            si.*,
            c.course_code,
            c.course_name,
            c.color_code,
            c.theory_lecturer,
            c.practical_lecturer,
            v.venue_code,
            v.venue_name,
            ts.day_of_week,
            ts.start_time,
            ts.end_time
         FROM schedule_items si
         JOIN courses c ON si.course_id = c.course_id
         JOIN time_slots ts ON si.slot_id = ts.slot_id
         LEFT JOIN venues v ON si.venue_id = v.venue_id
         WHERE si.schedule_id = ?
         ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
            ts.start_time",
        [$scheduleId]
    );
    
    $items = [];
    $dayMap = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5
    ];
    
    while ($row = $itemsStmt->fetch()) {
        // Skip continuation slots for practical classes
        if ($row['class_type'] == 'practical_cont') {
            continue;
        }
        
        $items[] = [
            'item_id' => $row['item_id'],
            'course_id' => $row['course_id'],
            'course_code' => $row['course_code'],
            'course_name' => $row['course_name'],
            'color_code' => $row['color_code'],
            'day' => $dayMap[$row['day_of_week']],
            'day_name' => $row['day_of_week'],
            'time' => substr($row['start_time'], 0, 5), // Remove seconds
            'end_time' => substr($row['end_time'], 0, 5),
            'class_type' => $row['class_type'],
            'duration' => $row['duration'],
            'venue_id' => $row['venue_id'],
            'venue_code' => $row['venue_code'],
            'venue_name' => $row['venue_name'],
            'lecturer' => $row['class_type'] == 'theory' ? $row['theory_lecturer'] : $row['practical_lecturer'],
            'notes' => $row['notes']
        ];
    }
    
    // Return schedule data
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $schedule['schedule_id'],
            'name' => $schedule['schedule_name'],
            'semester' => $schedule['semester'],
            'year' => $schedule['year'],
            'created_by' => $schedule['first_name'] . ' ' . $schedule['last_name'],
            'created_at' => $schedule['created_at'],
            'updated_at' => $schedule['updated_at'],
            'is_owner' => $schedule['user_id'] == $_SESSION['user_id'],
            'share_token' => $schedule['share_token'],
            'items' => $items
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error loading schedule: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading schedule'
    ]);
}
?>