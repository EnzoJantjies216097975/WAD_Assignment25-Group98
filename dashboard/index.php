<?php
// dashboard/index.php - Main dashboard page
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$db = Database::getInstance();

// Get user's schedules count
$schedulesStmt = $db->query(
    "SELECT COUNT(*) as total_schedules FROM schedules WHERE user_id = ? AND is_active = 1",
    [$_SESSION['user_id']]
);
$schedulesCount = $schedulesStmt->fetch()['total_schedules'];

// Get recent schedules
$recentStmt = $db->query(
    "SELECT s.*, 
            (SELECT COUNT(*) FROM schedule_items WHERE schedule_id = s.schedule_id) as total_classes
     FROM schedules s 
     WHERE s.user_id = ? AND s.is_active = 1 
     ORDER BY s.updated_at DESC 
     LIMIT 5",
    [$_SESSION['user_id']]
);
$recentSchedules = $recentStmt->fetchAll();

// Get user details
$userStmt = $db->query(
    "SELECT * FROM users WHERE user_id = ?",
    [$_SESSION['user_id']]
);
$user = $userStmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NUST Timetable Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <style>
        .dashboard-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-orange));
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .welcome-text h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            font-size: 18px;
            opacity: 0.95;
        }
        
        .welcome-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-dashboard {
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
        
        .btn-white {
            background: white;
            color: var(--primary-blue);
        }
        
        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline:hover {
            background: white;
            color: var(--primary-blue);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.orange {
            background: linear-gradient(135deg, #FFE5DC, #FFD4C4);
            color: var(--primary-orange);
        }
        
        .stat-icon.blue {
            background: linear-gradient(135deg, #E6F2FF, #CCE5FF);
            color: var(--primary-blue);
        }
        
        .stat-icon.green {
            background: linear-gradient(135deg, #E8F8F5, #D0F0E8);
            color: var(--success);
        }
        
        .stat-icon.purple {
            background: linear-gradient(135deg, #F3E8FF, #E6D0FF);
            color: #9B59B6;
        }
        
        .stat-details h3 {
            font-size: 28px;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }
        
        .stat-details p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .content-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .recent-schedules {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-light);
        }
        
        .section-title {
            color: var(--primary-blue);
            font-size: 20px;
            font-weight: 600;
        }
        
        .view-all-link {
            color: var(--primary-orange);
            font-weight: 500;
            font-size: 14px;
        }
        
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .schedule-item {
            padding: 15px;
            background: var(--background);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .schedule-item:hover {
            border-color: var(--primary-orange);
            transform: translateX(5px);
        }
        
        .schedule-info h4 {
            color: var(--primary-blue);
            margin-bottom: 5px;
        }
        
        .schedule-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: var(--text-light);
        }
        
        .schedule-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: var(--primary-blue);
            color: white;
        }
        
        .btn-edit {
            background: var(--primary-orange);
            color: white;
        }
        
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        
        .action-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .action-item {
            padding: 15px;
            background: linear-gradient(135deg, var(--background), white);
            border-radius: 8px;
            border-left: 4px solid var(--primary-orange);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-sm);
        }
        
        .action-icon {
            font-size: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 968px) {
            .content-sections {
                grid-template-columns: 1fr;
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
                <li><a href="index.php" class="nav-link active">Dashboard</a></li>
                <li><a href="schedules.php" class="nav-link">My Schedules</a></li>
                <li><a href="create-schedule.php" class="nav-link">Create</a></li>
                <li><a href="course-browser.php" class="nav-link">Courses</a></li>
                <li><a href="profile.php" class="nav-link">Profile</a></li>
                <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h1>
                    <p>Student Number: <?php echo htmlspecialchars($user['student_number']); ?> | 
                       <?php echo htmlspecialchars($user['program']); ?> - Year <?php echo $user['year_of_study']; ?></p>
                </div>
                <div class="welcome-actions">
                    <a href="create-schedule.php" class="btn-dashboard btn-white">
                        <span>➕</span> Create New Timetable
                    </a>
                    <a href="schedules.php" class="btn-dashboard btn-outline">
                        <span>📅</span> View All Schedules
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">📚</div>
                <div class="stat-details">
                    <h3><?php echo $schedulesCount; ?></h3>
                    <p>Total Schedules</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">📝</div>
                <div class="stat-details">
                    <h3><?php echo $user['year_of_study']; ?></h3>
                    <p>Year of Study</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-details">
                    <h3>Active</h3>
                    <p>Account Status</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">🎓</div>
                <div class="stat-details">
                    <h3>2025</h3>
                    <p>Current Year</p>
                </div>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="content-sections">
            <!-- Recent Schedules -->
            <div class="recent-schedules">
                <div class="section-header">
                    <h2 class="section-title">Recent Timetables</h2>
                    <a href="schedules.php" class="view-all-link">View All →</a>
                </div>
                
                <?php if(count($recentSchedules) > 0): ?>
                    <div class="schedule-list">
                        <?php foreach($recentSchedules as $schedule): ?>
                            <div class="schedule-item">
                                <div class="schedule-info">
                                    <h4><?php echo htmlspecialchars($schedule['schedule_name']); ?></h4>
                                    <div class="schedule-meta">
                                        <span>📚 <?php echo $schedule['total_classes']; ?> classes</span>
                                        <span>📅 Semester <?php echo $schedule['semester']; ?></span>
                                        <span>🕒 Updated <?php echo date('M d, Y', strtotime($schedule['updated_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="schedule-actions">
                                    <a href="view-schedule.php?id=<?php echo $schedule['schedule_id']; ?>" 
                                       class="btn-small btn-view">View</a>
                                    <a href="edit-schedule.php?id=<?php echo $schedule['schedule_id']; ?>" 
                                       class="btn-small btn-edit">Edit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No schedules yet. Create your first timetable!</p>
                        <a href="create-schedule.php" class="btn-dashboard btn-white" style="margin-top: 15px;">
                            Create Timetable
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="section-header">
                    <h2 class="section-title">Quick Actions</h2>
                </div>
                
                <div class="action-list">
                    <a href="create-schedule.php" class="action-item">
                        <span class="action-icon">➕</span>
                        <div>
                            <strong>Create New Timetable</strong>
                            <div style="font-size: 12px; color: var(--text-light);">Build a new schedule</div>
                        </div>
                    </a>
                    
                    <a href="course-browser.php" class="action-item">
                        <span class="action-icon">🔍</span>
                        <div>
                            <strong>Browse Courses</strong>
                            <div style="font-size: 12px; color: var(--text-light);">Explore available courses</div>
                        </div>
                    </a>
                    
                    <a href="share-export.php" class="action-item">
                        <span class="action-icon">📤</span>
                        <div>
                            <strong>Share & Export</strong>
                            <div style="font-size: 12px; color: var(--text-light);">Share or download schedules</div>
                        </div>
                    </a>
                    
                    <a href="profile.php" class="action-item">
                        <span class="action-icon">⚙️</span>
                        <div>
                            <strong>Account Settings</strong>
                            <div style="font-size: 12px; color: var(--text-light);">Manage your profile</div>
                        </div>
                    </a>
                    
                    <a href="help.php" class="action-item">
                        <span class="action-icon">❓</span>
                        <div>
                            <strong>Help & Support</strong>
                            <div style="font-size: 12px; color: var(--text-light);">Get assistance</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Add hover effects to cards
            $('.stat-card, .schedule-item, .action-item').hover(
                function() {
                    $(this).css('cursor', 'pointer');
                }
            );
            
            // Click handlers for action items
            $('.action-item').click(function(e) {
                // Navigation is handled by the href attribute
            });
        });
    </script>
</body>
</html>