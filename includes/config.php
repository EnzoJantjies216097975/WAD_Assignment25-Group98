<?php

// Configuration file for NUST Timetable Manager

// Database configuration for WAMP Server
define('DB_HOST', 'localhost');
define('DB_NAME', 'nust_timetable');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default WAMP has no password
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('SITE_NAME', 'NUST Timetable Manager');
define('SITE_URL', 'http://localhost/university-timetable-manager/');
define('SITE_EMAIL', 'support@nust.na');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'NUST_TIMETABLE_SESSION');

// Time zone setting (Namibia)
date_default_timezone_set('Africa/Windhoek');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security settings
define('SECURE_AUTH_KEY', 'your-secret-key-here-change-in-production');
define('PASSWORD_MIN_LENGTH', 8);

// Upload settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// NUST branding colors
define('PRIMARY_COLOR', '#FF6B35'); // NUST Orange
define('SECONDARY_COLOR', '#003D7A'); // NUST Blue
define('ACCENT_COLOR', '#FFA500'); // Gold
define('TEXT_COLOR', '#333333');
define('BACKGROUND_COLOR', '#F5F5F5');

// Timetable settings
define('TIMETABLE_START_TIME', '07:30');
define('TIMETABLE_END_TIME', '21:00');
define('LUNCH_START', '13:30');
define('LUNCH_END', '14:00');
define('PART_TIME_START', '17:15');

// Class duration settings
define('THEORY_DURATION', 1); // 1 hour
define('PRACTICAL_DURATION', 2); // 2 hours

?>

<?php