<?php
// QR Code Generator for Share Links

require_once '../config/database.php';

// Set appropriate headers for image
header('Content-Type: image/png');
header('Cache-Control: max-age=3600'); // Cache for 1 hour

try {
    $token = $_GET['token'] ?? '';
    $size = max(150, min(500, (int)($_GET['size'] ?? 300)));
    
    if (empty($token)) {
        throw new Exception('Share token is required');
    }
    
    $db = getDB();
    
    // Verify share token exists and is valid
    $sql = "SELECT 
                ss.share_token,
                ss.expires_at,
                s.name as schedule_name
            FROM schedule_shares ss
            JOIN schedules s ON ss.schedule_id = s.id
            WHERE ss.share_token = ? AND ss.expires_at > NOW()";
    
    $stmt = $db->query($sql, [$token]);
    $share_data = $stmt->fetch();
    
    if (!$share_data) {
        throw new Exception('Invalid or expired share token');
    }
    
    // Generate share URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $share_url = "{$protocol}://{$host}/shared/{$token}";
    
    // Generate QR code
    $qr_image = generateQRCode($share_url, $size);
    
    // Output the QR code image
    imagepng($qr_image);
    imagedestroy($qr_image);
    
} catch (Exception $e) {
    // Generate error image
    $error_image = createErrorImage($e->getMessage());
    imagepng($error_image);
    imagedestroy($error_image);
}

// Generate QR Code image

function generateQRCode($data, $size = 300) {
    $qrCode = new QrCode($data);
    $qrCode->setSize($size);
    $qrCode->setMargin(10);
    
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    
    return imagecreatefromstring($result->getString());
    
    // Placeholder implementation - creates a simple image with text
    $image = imagecreate($size, $size);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 29, 44, 93);
    
    // Fill background
    imagefill($image, 0, 0, $white);
    
    // Draw border
    imagerectangle($image, 0, 0, $size-1, $size-1, $black);
    
    // Add title
    $font_size = 3;
    $title = "NUST Timetable";
    $text_width = imagefontwidth($font_size) * strlen($title);
    $text_x = ($size - $text_width) / 2;
    imagestring($image, $font_size, $text_x, 20, $title, $blue);
    
    // Add QR placeholder pattern (simple squares)
    $square_size = 8;
    $margin = 50;
    $pattern_size = $size - (2 * $margin);
    
    for ($x = $margin; $x < $size - $margin; $x += $square_size * 2) {
        for ($y = $margin + 30; $y < $size - $margin; $y += $square_size * 2) {
            imagefilledrectangle($image, $x, $y, $x + $square_size, $y + $square_size, $black);
        }
    }
    
    // Add URL at bottom
    $url_text = "Scan to view schedule";
    $url_width = imagefontwidth(2) * strlen($url_text);
    $url_x = ($size - $url_width) / 2;
    imagestring($image, 2, $url_x, $size - 30, $url_text, $black);
    
    return $image;
}

// Create error image
function createErrorImage($message, $size = 300) {
    $image = imagecreate($size, $size);
    $white = imagecolorallocate($image, 255, 255, 255);
    $red = imagecolorallocate($image, 220, 38, 38);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $white);
    imagerectangle($image, 0, 0, $size-1, $size-1, $red);
    
    // Add error message
    $font_size = 2;
    $text = "QR Code Error";
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_x = ($size - $text_width) / 2;
    imagestring($image, $font_size, $text_x, $size/2 - 20, $text, $red);
    
    // Add simplified error message
    $error_text = substr($message, 0, 20) . "...";
    $error_width = imagefontwidth(1) * strlen($error_text);
    $error_x = ($size - $error_width) / 2;
    imagestring($image, 1, $error_x, $size/2 + 10, $error_text, $black);
    
    return $image;
}