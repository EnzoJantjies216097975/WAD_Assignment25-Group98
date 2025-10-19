<?php
// api/export-schedule.php - Export schedule in various formats
session_start();

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$scheduleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';

if ($scheduleId <= 0) {
    die('Invalid schedule ID');
}

$db = Database::getInstance();

// Verify user has access to this schedule
$scheduleStmt = $db->query(
    "SELECT s.*, u.first_name, u.last_name 
     FROM schedules s 
     JOIN users u ON s.user_id = u.user_id 
     WHERE s.schedule_id = ? AND s.is_active = 1",
    [$scheduleId]
);

if ($scheduleStmt->rowCount() == 0) {
    die('Schedule not found');
}

$schedule = $scheduleStmt->fetch();

// Check if user has access
if ($schedule['user_id'] != $_SESSION['user_id']) {
    die('Access denied');
}

// Fetch schedule items
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

$scheduleItems = [];
while ($row = $itemsStmt->fetch()) {
    $day = getDayNumber($row['day_of_week']);
    $time = formatTime($row['start_time']);
    $key = $day . '-' . $time;
    $scheduleItems[$key] = $row;
}

// Export based on format
switch ($format) {
    case 'json':
        exportAsJSON($schedule, $scheduleItems);
        break;
    
    case 'csv':
        exportAsCSV($schedule, $scheduleItems);
        break;
    
    case 'ics':
        exportAsICS($schedule, $scheduleItems);
        break;
    
    case 'html':
        exportAsHTML($schedule, $scheduleItems);
        break;
    
    case 'pdf':
    default:
        // For PDF, we'll generate HTML and instruct user to print
        exportAsPrintableHTML($schedule, $scheduleItems);
        break;
}

/**
 * Export as JSON
 */
function exportAsJSON($schedule, $items) {
    $exportData = [
        'schedule' => [
            'name' => $schedule['schedule_name'],
            'semester' => $schedule['semester'],
            'year' => $schedule['year'],
            'created_by' => $schedule['first_name'] . ' ' . $schedule['last_name'],
            'created_at' => $schedule['created_at'],
            'updated_at' => $schedule['updated_at']
        ],
        'items' => array_values($items),
        'exported_at' => date('Y-m-d H:i:s')
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="schedule_' . $schedule['schedule_id'] . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT);
}

/**
 * Export as CSV
 */
function exportAsCSV($schedule, $items) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="schedule_' . $schedule['schedule_id'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, ['Day', 'Time', 'Course Code', 'Course Name', 'Type', 'Venue', 'Lecturer']);
    
    // Data rows
    foreach ($items as $item) {
        if ($item['class_type'] !== 'practical_cont') {
            fputcsv($output, [
                $item['day_of_week'],
                formatTime($item['start_time']) . ' - ' . formatTime($item['end_time']),
                $item['course_code'],
                $item['course_name'],
                ucfirst($item['class_type']),
                $item['venue_code'] ?: 'TBA',
                $item['class_type'] == 'Theory' ? $item['theory_lecturer'] : $item['practical_lecturer']
            ]);
        }
    }
    
    fclose($output);
}

/**
 * Export as ICS (Calendar format)
 */
function exportAsICS($schedule, $items) {
    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="schedule_' . $schedule['schedule_id'] . '.ics"');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//NUST//Timetable Manager//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "X-WR-CALNAME:" . $schedule['schedule_name'] . "\r\n";
    
    // Calculate start date (next Monday)
    $nextMonday = strtotime('next monday');
    $dayMap = ['Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2, 'Thursday' => 3, 'Friday' => 4];
    
    foreach ($items as $item) {
        if ($item['class_type'] !== 'practical_cont') {
            $eventDate = $nextMonday + ($dayMap[$item['day_of_week']] * 86400);
            
            echo "BEGIN:VEVENT\r\n";
            echo "DTSTART:" . date('Ymd\T', $eventDate) . str_replace(':', '', $item['start_time']) . "\r\n";
            echo "DTEND:" . date('Ymd\T', $eventDate) . str_replace(':', '', $item['end_time']) . "\r\n";
            echo "SUMMARY:" . $item['course_code'] . " - " . $item['course_name'] . "\r\n";
            echo "LOCATION:" . ($item['venue_code'] ?: 'TBA') . "\r\n";
            echo "DESCRIPTION:Type: " . ucfirst($item['class_type']) . "\\nLecturer: " . 
                 ($item['class_type'] == 'Theory' ? $item['theory_lecturer'] : $item['practical_lecturer']) . "\r\n";
            echo "RRULE:FREQ=WEEKLY;COUNT=14\r\n"; // Repeat for 14 weeks (one semester)
            echo "END:VEVENT\r\n";
        }
    }
    
    echo "END:VCALENDAR\r\n";
}

/**
 * Export as HTML table
 */
function exportAsHTML($schedule, $items) {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="schedule_' . $schedule['schedule_id'] . '.html"');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo htmlspecialchars($schedule['schedule_name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
            th { background: #003D7A; color: white; }
            .time-cell { background: #f5f5f5; font-weight: bold; }
            .lunch { background: #f0f0f0; color: #999; font-style: italic; }
            .class-block { background: #FFE5DC; padding: 5px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($schedule['schedule_name']); ?></h1>
        <p>Semester <?php echo $schedule['semester']; ?>, <?php echo $schedule['year']; ?></p>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Monday</th>
                    <th>Tuesday</th>
                    <th>Wednesday</th>
                    <th>Thursday</th>
                    <th>Friday</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $timeSlots = ['07:30', '08:30', '09:30', '10:30', '11:30', '12:30', '13:30', '14:00', '15:00', '17:15', '18:40', '20:00'];
                foreach ($timeSlots as $time):
                ?>
                <tr>
                    <td class="time-cell"><?php echo $time; ?></td>
                    <?php for ($day = 1; $day <= 5; $day++):
                        $key = $day . '-' . $time;
                        if ($time == '13:30'):
                    ?>
                        <td class="lunch">Lunch Break</td>
                    <?php elseif (isset($items[$key]) && $items[$key]['class_type'] !== 'practical_cont'): ?>
                        <td>
                            <div class="class-block">
                                <strong><?php echo $items[$key]['course_code']; ?></strong><br>
                                <?php echo $items[$key]['venue_code'] ?: 'TBA'; ?>
                            </div>
                        </td>
                    <?php else: ?>
                        <td></td>
                    <?php endif; endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
}

/**
 * Export as printable HTML (for PDF printing)
 */
function exportAsPrintableHTML($schedule, $items) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo htmlspecialchars($schedule['schedule_name']); ?> - Print Version</title>
        <style>
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
            }
            
            h1 {
                color: #003D7A;
                text-align: center;
                margin-bottom: 10px;
            }
            
            .schedule-info {
                text-align: center;
                margin-bottom: 20px;
                color: #666;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 5px;
                text-align: center;
            }
            
            thead th {
                background: #003D7A;
                color: white;
                font-weight: bold;
                padding: 8px;
            }
            
            .time-header {
                background: #FF6B35 !important;
            }
            
            .time-cell {
                background: #f8f8f8;
                font-weight: bold;
                width: 80px;
            }
            
            .lunch-cell {
                background: repeating-linear-gradient(
                    45deg,
                    #f5f5f5,
                    #f5f5f5 10px,
                    #eeeeee 10px,
                    #eeeeee 20px
                );
                color: #999;
                font-style: italic;
            }
            
            .class-block {
                padding: 4px;
                border-radius: 4px;
                min-height: 40px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            
            .class-code {
                font-weight: bold;
                font-size: 12px;
            }
            
            .class-venue {
                font-size: 10px;
                color: #333;
            }
            
            .class-lecturer {
                font-size: 9px;
                color: #666;
            }
            
            .class-type {
                font-size: 9px;
                font-weight: bold;
            }
            
            @media print {
                body {
                    padding: 0;
                }
                
                .no-print {
                    display: none;
                }
            }
            
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 10px 20px;
                background: #FF6B35;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                z-index: 1000;
            }
        </style>
    </head>
    <body>
        <button class="print-button no-print" onclick="window.print()">Print / Save as PDF</button>
        
        <h1><?php echo htmlspecialchars($schedule['schedule_name']); ?></h1>
        <div class="schedule-info">
            <p>
                Semester <?php echo $schedule['semester']; ?>, <?php echo $schedule['year']; ?> | 
                Created by: <?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?> |
                Generated: <?php echo date('F j, Y'); ?>
            </p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th class="time-header">Time</th>
                    <th>Monday</th>
                    <th>Tuesday</th>
                    <th>Wednesday</th>
                    <th>Thursday</th>
                    <th>Friday</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $timeSlots = [
                    ['07:30', 'regular'],
                    ['08:30', 'regular'],
                    ['09:30', 'regular'],
                    ['10:30', 'regular'],
                    ['11:30', 'regular'],
                    ['12:30', 'regular'],
                    ['13:30', 'lunch'],
                    ['14:00', 'regular'],
                    ['15:00', 'regular'],
                    ['17:15', 'part-time'],
                    ['18:40', 'part-time'],
                    ['20:00', 'part-time']
                ];
                
                foreach ($timeSlots as $slot):
                    $time = $slot[0];
                    $type = $slot[1];
                ?>
                <tr>
                    <td class="time-cell"><?php echo $time; ?></td>
                    <?php for ($day = 1; $day <= 5; $day++):
                        $key = $day . '-' . $time;
                        $hasClass = isset($items[$key]);
                        $classData = $hasClass ? $items[$key] : null;
                        
                        if ($type == 'lunch'):
                    ?>
                        <td class="lunch-cell">Lunch Break</td>
                    <?php elseif ($hasClass && $classData['class_type'] !== 'practical_cont'): ?>
                        <td>
                            <div class="class-block" style="background-color: <?php echo $classData['color_code']; ?>22;">
                                <div class="class-code"><?php echo $classData['course_code']; ?></div>
                                <div class="class-type"><?php echo $classData['class_type'] == 'Theory' ? '(T)' : '(P)'; ?></div>
                                <div class="class-venue"><?php echo $classData['venue_code'] ?: 'TBA'; ?></div>
                                <div class="class-lecturer">
                                    <?php echo $classData['class_type'] == 'Theory' 
                                        ? $classData['theory_lecturer'] 
                                        : $classData['practical_lecturer']; ?>
                                </div>
                            </div>
                        </td>
                    <?php else: ?>
                        <td></td>
                    <?php endif; endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
}
?>