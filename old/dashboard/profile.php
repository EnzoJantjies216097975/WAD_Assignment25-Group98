<?php
// dashboard/profile.php - User profile management page
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Get user details
$userStmt = $db->query(
    "SELECT * FROM users WHERE user_id = ?",
    [$_SESSION['user_id']]
);
$user = $userStmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $yearOfStudy = intval($_POST['year_of_study']);
        $program = sanitizeInput($_POST['program']);
        
        // Validate email
        if (!validateEmail($email)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } else {
            // Check if email is already taken by another user
            $emailCheck = $db->query(
                "SELECT user_id FROM users WHERE email = ? AND user_id != ?",
                [$email, $_SESSION['user_id']]
            );
            
            if ($emailCheck->rowCount() > 0) {
                $message = 'Email address is already in use.';
                $messageType = 'error';
            } else {
                // Update user profile
                $updateStmt = $db->query(
                    "UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        year_of_study = ?, 
                        program = ?
                     WHERE user_id = ?",
                    [$firstName, $lastName, $email, $yearOfStudy, $program, $_SESSION['user_id']]
                );
                
                if ($updateStmt) {
                    // Update session variables
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['email'] = $email;
                    $_SESSION['year_of_study'] = $yearOfStudy;
                    $_SESSION['program'] = $program;
                    
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    
                    // Refresh user data
                    $userStmt = $db->query("SELECT * FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
                    $user = $userStmt->fetch();
                } else {
                    $message = 'Failed to update profile. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            $messageType = 'error';
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $db->query(
                "UPDATE users SET password = ? WHERE user_id = ?",
                [$hashedPassword, $_SESSION['user_id']]
            );
            
            if ($passwordStmt) {
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to change password. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get user statistics
$statsStmt = $db->query(
    "SELECT 
        COUNT(DISTINCT s.schedule_id) as total_schedules,
        COUNT(DISTINCT si.course_id) as unique_courses,
        COUNT(si.item_id) as total_classes
     FROM schedules s
     LEFT JOIN schedule_items si ON s.schedule_id = si.schedule_id
     WHERE s.user_id = ? AND s.is_active = 1",
    [$_SESSION['user_id']]
);
$stats = $statsStmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NUST Timetable Manager</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-orange));
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }
        
        .profile-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .profile-subtitle {
            font-size: 16px;
            opacity: 0.95;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        .profile-sidebar {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            height: fit-content;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-orange), var(--primary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            margin: 0 auto 20px;
            font-weight: bold;
        }
        
        .profile-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-name {
            font-size: 24px;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }
        
        .profile-student-number {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .profile-stats {
            display: flex;
            justify-content: space-around;
            padding: 20px 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-orange);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .profile-forms {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }
        
        .form-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            color: var(--primary-blue);
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-orange);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input, .form-select {
            padding: 12px;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }
        
        .form-input:disabled {
            background: var(--background);
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        
        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
        
        .account-actions {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-top: 20px;
        }
        
        .danger-zone {
            border: 2px solid var(--danger);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .danger-title {
            color: var(--danger);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
                <li><a href="index.php" class="nav-link">Dashboard</a></li>
                <li><a href="schedules.php" class="nav-link">My Schedules</a></li>
                <li><a href="create-schedule.php" class="nav-link">Create</a></li>
                <li><a href="profile.php" class="nav-link active">Profile</a></li>
                <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <h1>My Profile</h1>
            <p class="profile-subtitle">Manage your account settings and personal information</p>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="profile-student-number">Student #<?php echo htmlspecialchars($user['student_number']); ?></p>
                    <p><?php echo htmlspecialchars($user['program']); ?></p>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_schedules']; ?></div>
                        <div class="stat-label">Schedules</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['unique_courses']; ?></div>
                        <div class="stat-label">Courses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
                        <div class="stat-label">Classes</div>
                    </div>
                </div>
                
                <div style="text-align: center; color: var(--text-light); font-size: 14px;">
                    <p>Member since: <?php echo formatDate($user['created_at'], 'F Y'); ?></p>
                    <p>Last login: <?php echo $user['last_login'] ? formatDate($user['last_login'], 'M d, Y H:i') : 'Never'; ?></p>
                </div>
            </div>

            <!-- Forms -->
            <div>
                <div class="profile-forms">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">Personal Information</h3>
                        <form method="POST" action="profile.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Student Number</label>
                                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['student_number']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Year of Study</label>
                                    <select name="year_of_study" class="form-select" required>
                                        <option value="1" <?php echo $user['year_of_study'] == 1 ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo $user['year_of_study'] == 2 ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo $user['year_of_study'] == 3 ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo $user['year_of_study'] == 4 ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Program</label>
                                    <select name="program" class="form-select" required>
                                        <option value="Software Development" <?php echo $user['program'] == 'Software Development' ? 'selected' : ''; ?>>Software Development</option>
                                        <option value="Computer Science" <?php echo $user['program'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                        <option value="Information Technology" <?php echo $user['program'] == 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                        <option value="Cyber Security" <?php echo $user['program'] == 'Cyber Security' ? 'selected' : ''; ?>>Cyber Security</option>
                                        <option value="Data Science" <?php echo $user['program'] == 'Data Science' ? 'selected' : ''; ?>>Data Science</option>
                                        <option value="Computer Systems and Networks" <?php echo $user['program'] == 'Computer Systems and Networks' ? 'selected' : ''; ?>>Computer Systems and Networks</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="form-section">
                        <h3 class="section-title">Change Password</h3>
                        <form method="POST" action="profile.php">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-input" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-input" required minlength="8">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-input" required minlength="8">
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
                
                <!-- Account Actions -->
                <div class="account-actions">
                    <h3 class="section-title">Account Actions</h3>
                    
                    <div class="danger-zone">
                        <h4 class="danger-title">Danger Zone</h4>
                        <p style="color: var(--text-light); margin-bottom: 15px;">
                            Once you delete your account, there is no going back. Please be certain.
                        </p>
                        <button class="btn-danger" onclick="confirmDeleteAccount()">Delete Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDeleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                if (confirm('This will permanently delete your account and all your schedules. Are you absolutely sure?')) {
                    // Implement account deletion
                    alert('Account deletion is disabled for demo purposes');
                }
            }
        }
        
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut(500);
            }, 5000);
        });
    </script>
</body>
</html>