<?php
// index.php - Landing page for NUST Timetable Manager
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NUST Timetable Manager - Home</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="assets/js/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="assets/images/logo.png" alt="NUST Logo" class="nav-logo">
                <span class="nav-title">NUST Timetable Manager</span>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link active">Home</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="features.php" class="nav-link">Features</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li><a href="dashboard/index.php" class="nav-link">Dashboard</a></li>
                    <li><a href="logout.php" class="nav-link btn-logout">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                    <li><a href="register.php" class="nav-link btn-register">Register</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Welcome to NUST Timetable Manager</h1>
            <p class="hero-subtitle">
                Streamline your academic journey with our intelligent scheduling system.
                Create, manage, and optimize your university timetable with ease.
            </p>
            <div class="hero-buttons">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard/create-schedule.php" class="btn btn-primary">Create Timetable</a>
                    <a href="dashboard/schedules.php" class="btn btn-secondary">My Schedules</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="login.php" class="btn btn-secondary">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <div class="timetable-preview">
                <div class="preview-header">
                    <span>Monday</span>
                    <span>Tuesday</span>
                    <span>Wednesday</span>
                    <span>Thursday</span>
                    <span>Friday</span>
                </div>
                <div class="preview-grid">
                    <div class="time-slot">07:30</div>
                    <div class="class-block theory">WAD621S</div>
                    <div class="class-block practical">DSA521S (P)</div>
                    <div class="time-slot">08:30</div>
                    <div class="class-block theory">NET621S</div>
                    <div class="empty-slot"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Overview -->
    <section class="features-overview">
        <div class="container">
            <h2 class="section-title">Why Choose NUST Timetable Manager?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Drag & Drop Interface</h3>
                    <p>Easily create your timetable with our intuitive drag-and-drop system. No technical expertise required.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚠️</div>
                    <h3>Conflict Detection</h3>
                    <p>Automatic detection of scheduling conflicts ensures you never double-book your time slots.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">☁️</div>
                    <h3>Cloud Storage</h3>
                    <p>Access your schedules from anywhere. Your timetables are securely stored and synced across devices.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Mobile Responsive</h3>
                    <p>Works perfectly on all devices - desktop, tablet, or smartphone. Manage your schedule on the go.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔄</div>
                    <h3>Multiple Versions</h3>
                    <p>Create and compare different schedule scenarios. Perfect for planning alternative timetables.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📤</div>
                    <h3>Export & Share</h3>
                    <p>Export your timetable as PDF or share it with classmates using a unique link.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h4>Register Account</h4>
                    <p>Create your free account using your NUST student details</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h4>Browse Courses</h4>
                    <p>Search and select from available Faculty of Computing courses</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h4>Build Timetable</h4>
                    <p>Drag courses onto your schedule grid and arrange your perfect timetable</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h4>Save & Share</h4>
                    <p>Save your timetable and share it with friends or export as PDF</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Organize Your Academic Life?</h2>
            <p>Join hundreds of NUST students already using our timetable manager</p>
            <a href="register.php" class="btn btn-large">Start Creating Your Timetable</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>NUST Timetable Manager</h4>
                    <p>Faculty of Computing and Informatics</p>
                    <p>© 2025 Namibia University of Science and Technology</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="features.php">Features</a></li>
                        <li><a href="dashboard/help.php">Help & Support</a></li>
                        <li><a href="https://www.nust.na">NUST Website</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>13 Storch Street, Private Bag 13388</p>
                    <p>Windhoek, Namibia</p>
                    <p>Email: csi@nust.na</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        $(document).ready(function() {
            $('.nav-toggle').click(function() {
                $('.nav-menu').toggleClass('active');
                $(this).toggleClass('active');
            });
        });
    </script>
</body>
</html>