<?php
// dashboard/view-schedule.php - View timetable in read-only mode
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

if ($scheduleId <= 0) {
    header("Location: schedules.php");
    exit();
}

$db = Database::getInstance();

// Fetch schedule details
$scheduleStmt = $db->query(
    "SELECT s.*, u.first_name, u.last_name 
     FROM schedules s 
     JOIN users u ON s.user_id = u.user_id 
     WHERE s.schedule_id = ? AND s.is_active = 1",
    [$scheduleId]
);

if ($scheduleStmt->rowCount() == 0) {
    header("Location: schedules.php");
    exit();
}

$schedule = $scheduleStmt->fetch();

// Check if user has access to this schedule
if ($schedule['user_id'] != $_SESSION['user_id']) {
    // Check if schedule is shared (future feature)
    header("Location: schedules.php");
    exit();
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($schedule['schedule_name']); ?> - NUST Timetable Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/timetable.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <style>
        .view-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .schedule-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-orange));
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }
        
        .schedule-title-section {
            display: flex;
            justify-content: space-between;
            align-items: start;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .schedule-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .schedule-metadata {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 16px;
            opacity: 0.95;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-white {
            background: white;
            color: var(--primary-blue);
        }
        
        .btn-outline-white {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .timetable-view {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            overflow-x: auto;
        }
        
        .view-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2px;
            min-width: 900px;
        }
        
        .view-table thead th {
            background: var(--primary-blue);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .view-table .time-header {
            background: var(--primary-orange) !important;
            width: 100px;
        }
        
        .view-table tbody td {
            padding: 8px;
            text-align: center;
            border: 1px solid var(--gray-light);
            height: 70px;
            position: relative;
            background: white;
        }
        
        .view-table .time-cell {
            background: #f8f8f8;
            font-weight: 600;
            color: var(--text-dark);
            width: 100px;
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
        
        .part-time-cell {
            background: #FFFBF0;
        }
        
        .class-block {
            padding: 8px;
            border-radius: 6px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .class-code {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 2px;
        }
        
        .class-venue {
            font-size: 11px;
            opacity: 0.95;
        }
        
        .class-lecturer {
            font-size: 10px;
            opacity: 0.9;
            margin-top: 2px;
        }
        
        .class-type-badge {
            position: absolute;
            top: 4px;
            right: 6px;
            font-size: 9px;
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-orange);
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 5px;
        }
        
        @media print {
            .navbar,
            .action-buttons,
            .btn-action {
                display: none !important;
            }
            
            .view-container {
                padding: 0;
            }
            
            .schedule-header {
                background: none;
                color: black;
                padding: 20px 0;
            }
            
            .view-table {
                font-size: 10px;
            }
            
            .class-block {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="../assets/images/logo.png" alt="NUST Logo" class="nav-logo">
                <span class="nav-title">NUST Timetable Manager</span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Dashboard</a></li>
                <li><a href="schedules.php" class="nav-link active">My Schedules</a></li>
                <li><a href="create-schedule.php" class="nav-link">Create</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="view-container">
        <!-- Schedule Header -->
        <div class="schedule-header">
            <div class="schedule-title-section">
                <div class="schedule-info">
                    <h1><?php echo htmlspecialchars($schedule['schedule_name']); ?></h1>
                    <div class="schedule-metadata">
                        <span class="meta-item">
                            <span>📅</span>
                            Semester <?php echo $schedule['semester']; ?>, <?php echo $schedule['year']; ?>
                        </span>
                        <span class="meta-item">
                            <span>👤</span>
                            Created by <?php echo htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']); ?>
                        </span>
                        <span class="meta-item">
                            <span>🕒</span>
                            Last updated <?php echo formatDate($schedule['updated_at']); ?>
                        </span>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="edit-schedule.php?id=<?php echo $scheduleId; ?>" class="btn-action btn-white">
                        ✏️ Edit
                    </a>
                    <button onclick="printSchedule()" class="btn-action btn-outline-white">
                        🖨️ Print
                    </button>
                    <button onclick="exportPDF()" class="btn-action btn-outline-white">
                        📥 Export PDF
                    </button>
                    <button onclick="shareSchedule()" class="btn-action btn-outline-white">
                        📤 Share
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($scheduleItems); ?></div>
                <div class="stat-label">Total Classes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_unique(array_column($scheduleItems, 'course_id'))); ?></div>
                <div class="stat-label">Different Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($scheduleItems, function($item) { return $item['class_type'] == 'Theory'; })); ?></div>
                <div class="stat-label">Theory Classes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($scheduleItems, function($item) { return $item['class_type'] == 'Practical'; })); ?></div>
                <div class="stat-label">Practical Classes</div>
            </div>
        </div>

        <!-- Timetable View -->
        <div class="timetable-view">
            <table class="view-table">
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
                    
                    foreach($timeSlots as $slot):
                        $time = $slot[0];
                        $type = $slot[1];
                    ?>
                    <tr>
                        <td class="time-cell"><?php echo $time; ?></td>
                        <?php for($day = 1; $day <= 5; $day++): ?>
                            <?php 
                            $key = $day . '-' . $time;
                            $hasClass = isset($scheduleItems[$key]);
                            $classData = $hasClass ? $scheduleItems[$key] : null;
                            ?>
                            <?php if($type == 'lunch'): ?>
                                <td class="lunch-cell">Lunch Break</td>
                            <?php elseif($hasClass && $classData['class_type'] !== 'practical_cont'): ?>
                                <td class="<?php echo $type == 'part-time' ? 'part-time-cell' : ''; ?>">
                                    <div class="class-block" 
                                         style="background: linear-gradient(135deg, <?php echo $classData['color_code']; ?>, <?php echo $classData['color_code']; ?>dd);">
                                        <span class="class-type-badge">
                                            <?php echo $classData['class_type'] == 'Theory' ? 'T' : 'P'; ?>
                                        </span>
                                        <div class="class-code"><?php echo $classData['course_code']; ?></div>
                                        <div class="class-venue"><?php echo $classData['venue_code'] ?: 'TBA'; ?></div>
                                        <div class="class-lecturer">
                                            <?php echo $classData['class_type'] == 'Theory' 
                                                ? $classData['theory_lecturer'] 
                                                : $classData['practical_lecturer']; ?>
                                        </div>
                                    </div>
                                </td>
                            <?php else: ?>
                                <td class="<?php echo $type == 'part-time' ? 'part-time-cell' : ''; ?>"></td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function printSchedule() {
            window.print();
        }
        
        function exportPDF() {
            // This would typically use a library like jsPDF or server-side PDF generation
            alert('PDF export will be implemented with a PDF library');
            // window.location.href = '../api/export-schedule.php?id=<?php echo $scheduleId; ?>&format=pdf';
        }
        
        function shareSchedule() {
            const shareUrl = '<?php echo SITE_URL; ?>shared-schedule.php?token=<?php echo $schedule['share_token']; ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($schedule['schedule_name']); ?>',
                    text: 'Check out my NUST timetable',
                    url: shareUrl
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(shareUrl).then(function() {
                    alert('Share link copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>