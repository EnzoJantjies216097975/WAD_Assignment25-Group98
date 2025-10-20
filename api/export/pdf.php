<?php
// PDF Export API

require_once '../config/database.php';
require_once '../middleware/auth.php';

setJsonHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Check authentication
$user_id = requireAuth();

try {
    $data = getRequestBody();
    
    // Validate required fields
    validateRequired($data, ['schedule_id']);
    
    $schedule_id = (int)$data['schedule_id'];
    $format = $data['format'] ?? 'pdf';
    $orientation = $data['orientation'] ?? 'landscape';
    $include_details = $data['include_details'] ?? true;
    $color_coding = $data['color_coding'] ?? true;
    
    $db = getDB();
    
    // Get schedule details
    $schedule_sql = "SELECT 
                        s.id,
                        s.name,
                        s.description,
                        s.semester,
                        s.year,
                        s.courses_data,
                        u.full_name as owner_name,
                        u.student_number,
                        u.program
                     FROM schedules s
                     JOIN users u ON s.user_id = u.id
                     WHERE s.id = ? AND s.user_id = ?";
    
    $stmt = $db->query($schedule_sql, [$schedule_id, $user_id]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        sendError('Schedule not found', 404);
    }
    
    // Parse courses data
    $courses_data = json_decode($schedule['courses_data'] ?? '[]', true) ?: [];
    
    if (empty($courses_data)) {
        sendError('Schedule has no courses to export');
    }
    
    // Get detailed course information
    $course_codes = array_column($courses_data, 'code');
    $course_details = [];
    
    if (!empty($course_codes)) {
        $placeholders = str_repeat('?,', count($course_codes) - 1) . '?';
        $course_sql = "SELECT code, name, credits, lecturers, venue FROM courses WHERE code IN ($placeholders)";
        $course_stmt = $db->query($course_sql, $course_codes);
        $course_results = $course_stmt->fetchAll();
        
        foreach ($course_results as $course) {
            $course['lecturers'] = json_decode($course['lecturers'] ?? '[]', true);
            $course_details[$course['code']] = $course;
        }
    }
    
    // Generate PDF content
    $pdf_content = generatePDFContent($schedule, $courses_data, $course_details, [
        'orientation' => $orientation,
        'include_details' => $include_details,
        'color_coding' => $color_coding
    ]);
    
    // Log the export activity
    logActivity('export_pdf', ['schedule_id' => $schedule_id, 'format' => $format]);
    
    // Send response
    sendResponse([
        'success' => true,
        'message' => 'PDF generated successfully',
        'export_data' => [
            'schedule_name' => $schedule['name'],
            'format' => 'pdf',
            'orientation' => $orientation,
            'file_size' => strlen($pdf_content),
            'download_url' => generateDownloadUrl($schedule_id, 'pdf'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]
    ]);
    
} catch (Exception $e) {
    sendError('PDF export failed: ' . $e->getMessage(), 500);
}

// Generate PDF content (simplified version)
function generatePDFContent($schedule, $courses_data, $course_details, $options) {
    $html = generateHTMLTable($schedule, $courses_data, $course_details, $options);
    
    // This is where you would use a PDF library
    // For example with mPDF:
    /*
    require_once 'vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf([
        'orientation' => $options['orientation'] === 'landscape' ? 'L' : 'P',
        'format' => 'A4'
    ]);
    $mpdf->WriteHTML($html);
    return $mpdf->Output('', 'S'); // Return as string
    */
    
    // For now, return HTML content
    return $html;
}

/**
 * Generate HTML table for timetable
 */
function generateHTMLTable($schedule, $courses_data, $course_details, $options) {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $time_slots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    
    // Create timetable grid
    $grid = [];
    foreach ($courses_data as $course) {
        if (isset($course['day'], $course['time'])) {
            $grid[$course['day']][$course['time']] = $course;
        }
    }
    
    $html = "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$schedule['name']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info-table { width: 100%; margin-bottom: 20px; }
            .info-table td { padding: 5px; border: 1px solid #ddd; }
            .timetable { width: 100%; border-collapse: collapse; }
            .timetable th, .timetable td { border: 1px solid #333; padding: 8px; text-align: center; }
            .timetable th { background-color: #1d2c5d; color: white; font-weight: bold; }
            .time-header { background-color: #fcaf17 !important; color: #1d2c5d !important; }
            .course-cell { background-color: #e3f2fd; font-size: 12px; }
            .course-code { font-weight: bold; }
            .course-name { font-size: 10px; }
            .empty-cell { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>{$schedule['name']}</h1>
            <h3>Semester {$schedule['semester']}, {$schedule['year']}</h3>
        </div>
        
        <table class='info-table'>
            <tr>
                <td><strong>Student:</strong> {$schedule['owner_name']}</td>
                <td><strong>Student Number:</strong> {$schedule['student_number']}</td>
            </tr>
            <tr>
                <td><strong>Program:</strong> {$schedule['program']}</td>
                <td><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</td>
            </tr>
        </table>
        
        <table class='timetable'>
            <thead>
                <tr>
                    <th class='time-header'>Time</th>";
    
    foreach ($days as $day) {
        $html .= "<th>{$day}</th>";
    }
    
    $html .= "</tr></thead><tbody>";
    
    foreach ($time_slots as $time) {
        $html .= "<tr><td class='time-header'>{$time}</td>";
        
        foreach ($days as $day) {
            if (isset($grid[$day][$time])) {
                $course = $grid[$day][$time];
                $course_info = $course_details[$course['code']] ?? [];
                
                $html .= "<td class='course-cell'>";
                $html .= "<div class='course-code'>{$course['code']}</div>";
                
                if ($options['include_details'] && !empty($course_info['name'])) {
                    $html .= "<div class='course-name'>{$course_info['name']}</div>";
                    
                    if (!empty($course_info['lecturers'][0])) {
                        $html .= "<div class='course-lecturer'>{$course_info['lecturers'][0]}</div>";
                    }
                    
                    if (!empty($course_info['venue'])) {
                        $html .= "<div class='course-venue'>{$course_info['venue']}</div>";
                    }
                }
                
                $html .= "</td>";
            } else {
                $html .= "<td class='empty-cell'>&nbsp;</td>";
            }
        }
        
        $html .= "</tr>";
    }
    
    $html .= "</tbody></table>";
    
    // Add course list if details included
    if ($options['include_details']) {
        $html .= "<div style='margin-top: 30px;'>
                    <h3>Course Details</h3>
                    <table class='info-table'>";
        
        foreach ($courses_data as $course) {
            $info = $course_details[$course['code']] ?? [];
            $html .= "<tr>
                        <td><strong>{$course['code']}</strong></td>
                        <td>" . ($info['name'] ?? 'N/A') . "</td>
                        <td>" . ($info['credits'] ?? 'N/A') . " Credits</td>
                      </tr>";
        }
        
        $html .= "</table></div>";
    }
    
    $html .= "</body></html>";
    
    return $html;
}

// Generate download URL for the exported file
function generateDownloadUrl($schedule_id, $format) {
    $token = bin2hex(random_bytes(16));
    
    // In production, store this token temporarily in database or cache
    // For now, return a placeholder URL
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    return "{$protocol}://{$host}/api/export/download.php?token={$token}&format={$format}";
}