<?php
// includes/functions.php - Helper functions for NUST Timetable Manager

/**
 * Sanitize user input
 * @param string $data The input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 * @param string $email Email address to validate
 * @return boolean True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate student number (NUST format)
 * @param string $studentNumber Student number to validate
 * @return boolean True if valid, false otherwise
 */
function validateStudentNumber($studentNumber) {
    // NUST student numbers typically have 9 digits
    return preg_match('/^[0-9]{9}$/', $studentNumber);
}

/**
 * Generate a secure random token
 * @param int $length Length of the token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if user is logged in
 * @return boolean True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirect to a specific page
 * @param string $page Page to redirect to
 */
function redirect($page) {
    header("Location: $page");
    exit();
}

/**
 * Format datetime for display
 * @param string $datetime DateTime string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($datetime, $format = 'M d, Y') {
    return date($format, strtotime($datetime));
}

/**
 * Format time for display (remove seconds)
 * @param string $time Time string
 * @return string Formatted time
 */
function formatTime($time) {
    return substr($time, 0, 5);
}

/**
 * Get day name from day number
 * @param int $dayNumber Day number (1-5)
 * @return string Day name
 */
function getDayName($dayNumber) {
    $days = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday'
    ];
    return isset($days[$dayNumber]) ? $days[$dayNumber] : '';
}

/**
 * Get day number from day name
 * @param string $dayName Day name
 * @return int Day number
 */
function getDayNumber($dayName) {
    $days = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5
    ];
    return isset($days[$dayName]) ? $days[$dayName] : 0;
}

/**
 * Check if time slot is lunch break
 * @param string $time Time string
 * @return boolean True if lunch break, false otherwise
 */
function isLunchBreak($time) {
    return $time === '13:30' || $time === '13:30:00';
}

/**
 * Check if time slot is part-time
 * @param string $time Time string
 * @return boolean True if part-time slot, false otherwise
 */
function isPartTimeSlot($time) {
    $partTimeTimes = ['17:15', '18:40', '20:00'];
    return in_array(substr($time, 0, 5), $partTimeTimes);
}

/**
 * Get next time slot
 * @param string $currentTime Current time slot
 * @return string|null Next time slot or null
 */
function getNextTimeSlot($currentTime) {
    $timeSlots = [
        '07:30' => '08:30',
        '08:30' => '09:30',
        '09:30' => '10:30',
        '10:30' => '11:30',
        '11:30' => '12:30',
        '12:30' => '13:30',
        '13:30' => '14:00',
        '14:00' => '15:00',
        '15:00' => '16:00',
        '17:15' => '18:40',
        '18:40' => '20:00',
        '20:00' => null
    ];
    
    $time = substr($currentTime, 0, 5);
    return isset($timeSlots[$time]) ? $timeSlots[$time] : null;
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array Array with 'valid' boolean and 'message' string
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    return [
        'valid' => empty($errors),
        'message' => implode('. ', $errors)
    ];
}

/**
 * Get user's full name
 * @param int $userId User ID
 * @return string Full name
 */
function getUserFullName($userId) {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE user_id = ?",
        [$userId]
    );
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : 'Unknown User';
}

/**
 * Check if schedule belongs to user
 * @param int $scheduleId Schedule ID
 * @param int $userId User ID
 * @return boolean True if belongs to user, false otherwise
 */
function isScheduleOwner($scheduleId, $userId) {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT COUNT(*) as count FROM schedules WHERE schedule_id = ? AND user_id = ?",
        [$scheduleId, $userId]
    );
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Generate share link for schedule
 * @param int $scheduleId Schedule ID
 * @param string $shareToken Share token
 * @return string Share link
 */
function generateShareLink($scheduleId, $shareToken) {
    return SITE_URL . 'shared-schedule.php?id=' . $scheduleId . '&token=' . $shareToken;
}

/**
 * Get course color based on course code
 * @param string $courseCode Course code
 * @return string Hex color code
 */
function getCourseColor($courseCode) {
    $colors = [
        '#FF6B35', '#4A90E2', '#7B68EE', '#2ECC71',
        '#E74C3C', '#F39C12', '#9B59B6', '#1ABC9C',
        '#34495E', '#E67E22', '#3498DB', '#95A5A6'
    ];
    
    // Generate consistent color based on course code
    $hash = crc32($courseCode);
    $index = abs($hash) % count($colors);
    
    return $colors[$index];
}

/**
 * Format file size
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Get academic year
 * @param int $month Current month (optional)
 * @return int Academic year
 */
function getAcademicYear($month = null) {
    if ($month === null) {
        $month = date('n');
    }
    
    $year = date('Y');
    
    // Academic year starts in February at NUST
    if ($month < 2) {
        $year--;
    }
    
    return $year;
}

/**
 * Get current semester
 * @param int $month Current month (optional)
 * @return int Semester (1 or 2)
 */
function getCurrentSemester($month = null) {
    if ($month === null) {
        $month = date('n');
    }
    
    // Semester 1: February - June
    // Semester 2: July - November
    return ($month >= 2 && $month <= 6) ? 1 : 2;
}

/**
 * Log activity for auditing
 * @param int $userId User ID
 * @param string $action Action performed
 * @param string $details Additional details
 */
function logActivity($userId, $action, $details = '') {
    $db = Database::getInstance();
    
    try {
        $db->query(
            "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return boolean True if sent successfully, false otherwise
 */
function sendEmail($to, $subject, $message) {
    $headers = "From: " . SITE_EMAIL . "\r\n";
    $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $fullMessage = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #003D7A;'>NUST Timetable Manager</h2>
            <div style='padding: 20px; background: #f5f5f5; border-radius: 8px;'>
                $message
            </div>
            <p style='color: #666; font-size: 12px; margin-top: 20px;'>
                This email was sent from NUST Timetable Manager. 
                Please do not reply to this email.
            </p>
        </div>
    </body>
    </html>
    ";
    
    return mail($to, $subject, $fullMessage, $headers);
}

/**
 * Export schedule to JSON format
 * @param int $scheduleId Schedule ID
 * @return string JSON string
 */
function exportScheduleToJson($scheduleId) {
    $db = Database::getInstance();
    
    // Get schedule details
    $scheduleStmt = $db->query(
        "SELECT * FROM schedules WHERE schedule_id = ?",
        [$scheduleId]
    );
    $schedule = $scheduleStmt->fetch();
    
    // Get schedule items
    $itemsStmt = $db->query(
        "SELECT si.*, c.course_code, c.course_name, v.venue_code, ts.day_of_week, ts.start_time
         FROM schedule_items si
         JOIN courses c ON si.course_id = c.course_id
         JOIN time_slots ts ON si.slot_id = ts.slot_id
         LEFT JOIN venues v ON si.venue_id = v.venue_id
         WHERE si.schedule_id = ?",
        [$scheduleId]
    );
    $items = $itemsStmt->fetchAll();
    
    $exportData = [
        'schedule' => $schedule,
        'items' => $items,
        'exported_at' => date('Y-m-d H:i:s')
    ];
    
    return json_encode($exportData, JSON_PRETTY_PRINT);
}
?>
<?php