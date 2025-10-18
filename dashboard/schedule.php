<?php
// dashboard/schedules.php - View and manage all schedules
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$db = Database::getInstance();

// Get all user's schedules
$schedulesStmt = $db->query(
    "SELECT s.*, 
            (SELECT COUNT(*) FROM schedule_items WHERE schedule_id = s.schedule_id) as total_classes,
            (SELECT COUNT(DISTINCT course_id) FROM schedule_items WHERE schedule_id = s.schedule_id) as unique_courses
     FROM schedules s 
     WHERE s.user_id = ? AND s.is_active = 1 
     ORDER BY s.updated_at DESC",
    [$_SESSION['user_id']]
);
$schedules = $schedulesStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedules - NUST Timetable Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <style>
        .schedules-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary-orange);
        }
        
        .page-title {
            color: var(--primary-blue);
            font-size: 32px;
            font-weight: bold;
        }
        
        .page-subtitle {
            color: var(--text-light);
            font-size: 16px;
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn-create {
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
            padding: 12px 24px;
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
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-orange);
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-label {
            color: var(--text-dark);
            font-weight: 500;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 2px solid var(--gray-light);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }
        
        .schedules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .schedule-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .schedule-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange), var(--primary-blue));
        }
        
        .schedule-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-orange);
        }
        
        .schedule-header {
            margin-bottom: 15px;
        }
        
        .schedule-title {
            color: var(--primary-blue);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .schedule-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: var(--text-light);
        }
        
        .meta-icon {
            font-size: 16px;
        }
        
        .schedule-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: var(--background);
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-orange);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 2px;
        }
        
        .schedule-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .action-btn {
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-view {
            background: var(--primary-blue);
            color: white;
        }
        
        .btn-edit {
            background: var(--primary-orange);
            color: white;
        }
        
        .btn-delete {
            background: white;
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-light);
        }
        
        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 24px;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }
        
        .empty-text {
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        .share-modal {
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
        
        .share-modal.active {
            display: flex;
        }
        
        .share-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .share-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .share-title {
            color: var(--primary-blue);
            margin-bottom: 20px;
        }
        
        .share-link-box {
            background: var(--background);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            word-break: break-all;
        }
        
        .copy-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-orange);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .schedules-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: start;
                gap: 15px;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
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
                <li><a href="course-browser.php" class="nav-link">Courses</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="schedules-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">My Timetables</h1>
                <p class="page-subtitle">Manage and organize all your academic schedules</p>
            </div>
            <div class="header-actions">
                <a href="create-schedule.php" class="btn-create">
                    <span>➕</span> Create New Timetable
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="searchSchedules" placeholder="Search timetables...">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Semester:</label>
                <select class="filter-select" id="filterSemester">
                    <option value="">All</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Year:</label>
                <select class="filter-select" id="filterYear">
                    <option value="">All</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Sort by:</label>
                <select class="filter-select" id="sortSchedules">
                    <option value="updated">Last Updated</option>
                    <option value="created">Date Created</option>
                    <option value="name">Name</option>
                    <option value="classes">Number of Classes</option>
                </select>
            </div>
        </div>

        <!-- Schedules Grid -->
        <?php if(count($schedules) > 0): ?>
            <div class="schedules-grid" id="schedulesGrid">
                <?php foreach($schedules as $schedule): ?>
                    <div class="schedule-card" data-schedule-id="<?php echo $schedule['schedule_id']; ?>"
                         data-semester="<?php echo $schedule['semester']; ?>"
                         data-year="<?php echo $schedule['year']; ?>"
                         data-name="<?php echo htmlspecialchars($schedule['schedule_name']); ?>">
                        
                        <div class="schedule-header">
                            <h3 class="schedule-title"><?php echo htmlspecialchars($schedule['schedule_name']); ?></h3>
                        </div>
                        
                        <div class="schedule-meta">
                            <span class="meta-item">
                                <span class="meta-icon">📅</span>
                                Semester <?php echo $schedule['semester']; ?>
                            </span>
                            <span class="meta-item">
                                <span class="meta-icon">📆</span>
                                <?php echo $schedule['year']; ?>
                            </span>
                            <span class="meta-item">
                                <span class="meta-icon">🕒</span>
                                <?php echo date('M d, Y', strtotime($schedule['updated_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="schedule-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $schedule['total_classes']; ?></div>
                                <div class="stat-label">Total Classes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $schedule['unique_courses']; ?></div>
                                <div class="stat-label">Courses</div>
                            </div>
                        </div>
                        
                        <div class="schedule-actions">
                            <button onclick="viewSchedule(<?php echo $schedule['schedule_id']; ?>)" 
                                    class="action-btn btn-view">
                                <span>👁️</span> View
                            </button>
                            <button onclick="editSchedule(<?php echo $schedule['schedule_id']; ?>)" 
                                    class="action-btn btn-edit">
                                <span>✏️</span> Edit
                            </button>
                            <button onclick="deleteSchedule(<?php echo $schedule['schedule_id']; ?>, '<?php echo htmlspecialchars($schedule['schedule_name']); ?>')" 
                                    class="action-btn btn-delete">
                                <span>🗑️</span> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h2 class="empty-title">No Timetables Yet</h2>
                <p class="empty-text">You haven't created any timetables yet. Start by creating your first schedule!</p>
                <a href="create-schedule.php" class="btn-create">
                    <span>➕</span> Create Your First Timetable
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Share Modal -->
    <div class="share-modal" id="shareModal">
        <div class="share-content">
            <span class="share-close" onclick="closeShareModal()">&times;</span>
            <h3 class="share-title">Share Timetable</h3>
            <p>Share this link with others to let them view your timetable:</p>
            <div class="share-link-box" id="shareLink"></div>
            <button class="copy-btn" onclick="copyShareLink()">Copy Link</button>
        </div>
    </div>

    <script>
        // View schedule
        function viewSchedule(id) {
            window.location.href = 'view-schedule.php?id=' + id;
        }
        
        // Edit schedule
        function editSchedule(id) {
            window.location.href = 'edit-schedule.php?id=' + id;
        }
        
        // Delete schedule
        function deleteSchedule(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                $.ajax({
                    url: '../api/delete-schedule.php',
                    method: 'POST',
                    data: { schedule_id: id },
                    success: function(response) {
                        if (response.success) {
                            // Remove card from grid
                            $(`[data-schedule-id="${id}"]`).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if grid is empty
                                if ($('.schedule-card').length === 0) {
                                    location.reload();
                                }
                            });
                            
                            alert('Timetable deleted successfully');
                        } else {
                            alert('Error deleting timetable: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error connecting to server');
                    }
                });
            }
        }
        
        // Search functionality
        $('#searchSchedules').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('.schedule-card').each(function() {
                const name = $(this).data('name').toLowerCase();
                if (name.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Filter functionality
        $('#filterSemester, #filterYear').on('change', function() {
            const semester = $('#filterSemester').val();
            const year = $('#filterYear').val();
            
            $('.schedule-card').each(function() {
                const cardSemester = $(this).data('semester').toString();
                const cardYear = $(this).data('year').toString();
                
                let show = true;
                
                if (semester && cardSemester !== semester) {
                    show = false;
                }
                
                if (year && cardYear !== year) {
                    show = false;
                }
                
                if (show) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Sort functionality
        $('#sortSchedules').on('change', function() {
            const sortBy = $(this).val();
            const $grid = $('#schedulesGrid');
            const $cards = $grid.find('.schedule-card').sort(function(a, b) {
                switch(sortBy) {
                    case 'name':
                        return $(a).data('name').localeCompare($(b).data('name'));
                    case 'created':
                        return new Date($(b).find('.meta-item:last').text()) - new Date($(a).find('.meta-item:last').text());
                    case 'classes':
                        return $(b).find('.stat-value:first').text() - $(a).find('.stat-value:first').text();
                    default: // updated
                        return 0;
                }
            });
            
            $grid.html($cards);
        });
        
        // Share modal functions
        function closeShareModal() {
            $('#shareModal').removeClass('active');
        }
        
        function copyShareLink() {
            const shareLink = $('#shareLink').text();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(shareLink).then(function() {
                    alert('Link copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = shareLink;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Link copied to clipboard!');
            }
        }
    </script>
</body>
</html>