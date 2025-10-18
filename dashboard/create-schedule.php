<?php
// dashboard/create-schedule.php - Main timetable creation interface
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$db = Database::getInstance();

// Fetch all courses from database
$courses_stmt = $db->query("
    SELECT course_id, course_code, course_name, department, credits, 
           year_level, semester, theory_lecturer, practical_lecturer, color_code
    FROM courses 
    ORDER BY year_level, course_code
");
$courses = $courses_stmt->fetchAll();

// Fetch all venues
$venues_stmt = $db->query("SELECT * FROM venues ORDER BY venue_code");
$venues = $venues_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Timetable - NUST Timetable Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/timetable.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        .dashboard-container {
            display: flex;
            height: calc(100vh - 70px);
            background: var(--background);
        }
        
        /* Sidebar with courses */
        .courses-sidebar {
            width: 300px;
            background: white;
            border-right: 2px solid var(--gray-light);
            overflow-y: auto;
            padding: 20px;
        }
        
        .sidebar-header {
            margin-bottom: 20px;
        }
        
        .sidebar-title {
            color: var(--primary-blue);
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .course-search {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .course-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            flex: 1;
            padding: 8px;
            border: 1px solid var(--gray-light);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background: var(--primary-orange);
            color: white;
            border-color: var(--primary-orange);
        }
        
        /* Draggable course cards */
        .course-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .course-card {
            padding: 12px;
            border-radius: 8px;
            cursor: move;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }
        
        .course-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .course-card.dragging {
            opacity: 0.5;
        }
        
        .course-code {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .course-name {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .course-details {
            display: flex;
            gap: 10px;
            font-size: 11px;
            color: var(--text-light);
        }
        
        .course-badge {
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--light-blue);
            color: var(--primary-blue);
            font-size: 10px;
        }
        
        /* Main timetable area */
        .timetable-container {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
            overflow-y: auto;
        }
        
        .timetable-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }
        
        .timetable-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .schedule-name-input {
            padding: 8px 12px;
            border: 2px solid var(--gray-light);
            border-radius: 6px;
            font-size: 16px;
            width: 300px;
        }
        
        .timetable-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
        }
        
        .btn-clear {
            background: white;
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        .btn-export {
            background: var(--primary-blue);
            color: white;
        }
        
        /* Timetable grid */
        .timetable-grid {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            min-width: 1000px;
        }
        
        .timetable-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2px;
        }
        
        .timetable-table th,
        .timetable-table td {
            padding: 8px;
            text-align: center;
            border: 1px solid var(--gray-light);
        }
        
        .timetable-table thead th {
            background: var(--primary-blue);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .time-header {
            background: var(--primary-orange) !important;
            width: 100px;
            font-size: 14px;
        }
        
        .time-cell {
            background: #f8f8f8;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 13px;
            width: 100px;
        }
        
        .slot-cell {
            height: 60px;
            position: relative;
            background: white;
            transition: all 0.3s;
        }
        
        .slot-cell.droppable {
            background: var(--light-orange);
            border: 2px dashed var(--primary-orange);
        }
        
        .slot-cell.occupied {
            padding: 4px;
        }
        
        .slot-cell.lunch-break {
            background: linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0),
                        linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0);
            background-size: 10px 10px;
            background-position: 0 0, 5px 5px;
            color: var(--text-light);
            font-style: italic;
            pointer-events: none;
        }
        
        .slot-cell.part-time {
            background: #fffbf0;
            border: 1px solid #ffa500;
        }
        
        /* Scheduled class block */
        .scheduled-class {
            padding: 6px;
            border-radius: 6px;
            height: 100%;
            cursor: move;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            font-size: 11px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .scheduled-class.practical {
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .scheduled-class .class-code {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 2px;
        }
        
        .scheduled-class .class-type {
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: 9px;
            background: rgba(0,0,0,0.2);
            padding: 1px 4px;
            border-radius: 3px;
        }
        
        .scheduled-class .class-room {
            font-size: 10px;
            opacity: 0.9;
        }
        
        .scheduled-class .remove-btn {
            position: absolute;
            top: 2px;
            left: 4px;
            width: 16px;
            height: 16px;
            background: rgba(255,255,255,0.9);
            color: var(--danger);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }
        
        .scheduled-class:hover .remove-btn {
            display: flex;
        }
        
        /* Modal for class details */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .modal-title {
            color: var(--primary-blue);
            margin-bottom: 20px;
        }
        
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-control {
            padding: 10px;
            border: 2px solid var(--gray-light);
            border-radius: 6px;
            width: 100%;
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
                <li><a href="schedules.php" class="nav-link">My Schedules</a></li>
                <li><a href="create-schedule.php" class="nav-link active">Create</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><span class="nav-link">Welcome, <?php echo $_SESSION['user_name']; ?></span></li>
                <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Courses Sidebar -->
        <div class="courses-sidebar">
            <div class="sidebar-header">
                <h3 class="sidebar-title">Available Courses</h3>
                <input type="text" class="course-search" placeholder="Search courses..." id="courseSearch">
                <div class="course-filter">
                    <button class="filter-btn active" data-type="all">All</button>
                    <button class="filter-btn" data-type="theory">Theory</button>
                    <button class="filter-btn" data-type="practical">Practical</button>
                </div>
            </div>
            
            <div class="course-list" id="courseList">
                <?php foreach($courses as $course): ?>
                    <!-- Theory Class Card -->
                    <div class="course-card draggable-course" 
                         data-course-id="<?php echo $course['course_id']; ?>"
                         data-course-code="<?php echo $course['course_code']; ?>"
                         data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                         data-class-type="theory"
                         data-duration="1"
                         data-lecturer="<?php echo htmlspecialchars($course['theory_lecturer']); ?>"
                         data-color="<?php echo $course['color_code']; ?>"
                         style="background: linear-gradient(135deg, <?php echo $course['color_code']; ?>22, <?php echo $course['color_code']; ?>11);">
                        <div class="course-code"><?php echo $course['course_code']; ?> - Theory</div>
                        <div class="course-name"><?php echo $course['course_name']; ?></div>
                        <div class="course-details">
                            <span class="course-badge">1 Hour</span>
                            <span><?php echo $course['theory_lecturer']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Practical Class Card -->
                    <div class="course-card draggable-course" 
                         data-course-id="<?php echo $course['course_id']; ?>"
                         data-course-code="<?php echo $course['course_code']; ?>"
                         data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                         data-class-type="practical"
                         data-duration="2"
                         data-lecturer="<?php echo htmlspecialchars($course['practical_lecturer']); ?>"
                         data-color="<?php echo $course['color_code']; ?>"
                         style="background: linear-gradient(135deg, <?php echo $course['color_code']; ?>33, <?php echo $course['color_code']; ?>22);">
                        <div class="course-code"><?php echo $course['course_code']; ?> - Practical</div>
                        <div class="course-name"><?php echo $course['course_name']; ?></div>
                        <div class="course-details">
                            <span class="course-badge">2 Hours</span>
                            <span><?php echo $course['practical_lecturer']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Timetable Area -->
        <div class="timetable-container">
            <div class="timetable-header">
                <div class="timetable-title">
                    <h2>Create New Timetable</h2>
                    <input type="text" class="schedule-name-input" id="scheduleName" 
                           placeholder="Enter timetable name..." value="Semester 2 - 2025">
                </div>
                <div class="timetable-actions">
                    <button class="action-btn btn-clear" id="clearBtn">Clear All</button>
                    <button class="action-btn btn-export" id="exportBtn">Export PDF</button>
                    <button class="action-btn btn-save" id="saveBtn">Save Timetable</button>
                </div>
            </div>

            <div class="timetable-grid">
                <table class="timetable-table">
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
                        $time_slots = [
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
                        
                        foreach($time_slots as $slot):
                            $time = $slot[0];
                            $type = $slot[1];
                        ?>
                        <tr>
                            <td class="time-cell"><?php echo $time; ?></td>
                            <?php for($day = 1; $day <= 5; $day++): ?>
                                <?php if($type == 'lunch'): ?>
                                    <td class="slot-cell lunch-break" colspan="1">Lunch Break</td>
                                <?php else: ?>
                                    <td class="slot-cell <?php echo $type == 'part-time' ? 'part-time' : ''; ?> droppable-slot" 
                                        data-day="<?php echo $day; ?>" 
                                        data-time="<?php echo $time; ?>"
                                        data-slot-type="<?php echo $type; ?>">
                                    </td>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Class Details Modal -->
    <div class="modal" id="classModal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title">Add Class Details</h3>
            <form class="modal-form" id="classDetailsForm">
                <input type="hidden" id="modalCourseId">
                <input type="hidden" id="modalSlotDay">
                <input type="hidden" id="modalSlotTime">
                
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" class="form-control" id="modalCourseName" readonly>
                </div>
                
                <div class="form-group">
                    <label>Class Type</label>
                    <input type="text" class="form-control" id="modalClassType" readonly>
                </div>
                
                <div class="form-group">
                    <label>Venue</label>
                    <select class="form-control" id="modalVenue">
                        <option value="">Select Venue...</option>
                        <?php foreach($venues as $venue): ?>
                            <option value="<?php echo $venue['venue_id']; ?>">
                                <?php echo $venue['venue_code']; ?> - <?php echo $venue['venue_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Additional Notes (Optional)</label>
                    <textarea class="form-control" id="modalNotes" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="action-btn btn-save" style="flex: 1;" id="confirmAddClass">Add to Timetable</button>
                    <button type="button" class="action-btn btn-clear" style="flex: 1;" id="cancelAddClass">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/timetable.js"></script>
</body>
</html>