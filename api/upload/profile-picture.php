<?php
// Profile Picture Upload API

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
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture'])) {
        sendError('No file uploaded');
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate file upload
    validateFileUpload($file, ['image/jpeg', 'image/png', 'image/gif'], 5242880); // 5MB max
    
    // Create upload directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;
    
    // Get current profile picture to delete later
    $db = getDB();
    $current_sql = "SELECT profile_picture FROM users WHERE id = ?";
    $current_stmt = $db->query($current_sql, [$user_id]);
    $current_user = $current_stmt->fetch();
    $old_picture = $current_user['profile_picture'] ?? null;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Resize image if needed (optional)
    $resized_path = resizeImage($file_path, 300, 300);
    
    // Update database
    $relative_path = 'uploads/profile_pictures/' . $new_filename;
    $update_sql = "UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?";
    $db->query($update_sql, [$relative_path, $user_id]);
    
    // Delete old profile picture if exists
    if ($old_picture && file_exists(__DIR__ . '/../../' . $old_picture)) {
        unlink(__DIR__ . '/../../' . $old_picture);
    }
    
    // Generate URL for the uploaded image
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $image_url = "{$protocol}://{$host}/{$relative_path}";
    
    // Log the upload activity
    logActivity('upload_profile_picture', ['filename' => $new_filename]);
    
    sendResponse([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'data' => [
            'filename' => $new_filename,
            'path' => $relative_path,
            'url' => $image_url,
            'size' => filesize($file_path),
            'type' => mime_content_type($file_path)
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Upload failed: ' . $e->getMessage(), 500);
}

// Resize image to specified dimensions
function resizeImage($source_path, $max_width, $max_height) {
    $image_info = getimagesize($source_path);
    
    if (!$image_info) {
        throw new Exception('Invalid image file');
    }
    
    list($orig_width, $orig_height, $image_type) = $image_info;
    
    // Calculate new dimensions
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
    $new_width = round($orig_width * $ratio);
    $new_height = round($orig_height * $ratio);
    
    // Create image resource based on type
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            throw new Exception('Unsupported image type');
    }
    
    if (!$source_image) {
        throw new Exception('Failed to create image resource');
    }
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled(
        $new_image, $source_image,
        0, 0, 0, 0,
        $new_width, $new_height,
        $orig_width, $orig_height
    );
    
    // Save resized image (overwrite original)
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $source_path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $source_path, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $source_path);
            break;
    }
    
    // Clean up memory
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $source_path;
}