<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please login to create a memory.';
    header('Location: ../../auth/login.php'); 
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $description = trim($_POST['description'] ?? '');
    
    // Define base upload directory for memories
    $base_memories_upload_dir = '../../uploads/memories/';
    $image_upload_dir = $base_memories_upload_dir . 'images/';
    $video_upload_dir = $base_memories_upload_dir . 'videos/';
    
    $uploaded_image_info = null; // To store image details for DB
    $uploaded_video_info = null; // To store video details for DB

    // Create directories if they don't exist
    foreach ([$image_upload_dir, $video_upload_dir] as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                $_SESSION['error_message'] = "Failed to create upload directory: {$dir}";
                header('Location: ../dashboard/dashboard.php');
                exit();
            }
        }
    }

    // Handle Image Upload
    if (isset($_FILES['memory_image']) && $_FILES['memory_image']['error'] === UPLOAD_ERR_OK) {
        $image_file = $_FILES['memory_image'];
        $image_original_filename = $image_file['name'];
        $image_file_size = $image_file['size'];
        $image_file_ext = strtolower(pathinfo($image_original_filename, PATHINFO_EXTENSION));
        $allowed_image_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $max_image_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($image_file_ext, $allowed_image_exts)) {
            $_SESSION['error_message'] = 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF.';
            // Consider redirecting back or showing error on the same page
            header('Location: ../dashboard/dashboard.php'); 
            exit();
        }
        if ($image_file_size > $max_image_size) {
            $_SESSION['error_message'] = 'Image file is too large. Max 5MB.';
            header('Location: ../dashboard/dashboard.php');
            exit();
        }

        $image_new_filename = uniqid('img_', true) . '.' . $image_file_ext;
        $image_target_path = $image_upload_dir . $image_new_filename;

        if (move_uploaded_file($image_file['tmp_name'], $image_target_path)) {
            $uploaded_image_info = [
                'file_path' => 'images/' . $image_new_filename, // Relative to $base_memories_upload_dir
                'original_filename' => $image_original_filename,
                'file_size' => $image_file_size,
                'media_type' => 'image'
            ];
        } else {
            $_SESSION['error_message'] = 'Failed to upload image.';
            header('Location: ../dashboard/dashboard.php');
            exit();
        }
    }

    // Handle Video Upload
    if (isset($_FILES['memory_video']) && $_FILES['memory_video']['error'] === UPLOAD_ERR_OK) {
        $video_file = $_FILES['memory_video'];
        $video_original_filename = $video_file['name'];
        $video_file_size = $video_file['size'];
        $video_file_ext = strtolower(pathinfo($video_original_filename, PATHINFO_EXTENSION));
        $allowed_video_exts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
        $max_video_size = 50 * 1024 * 1024; // 50MB

        if (!in_array($video_file_ext, $allowed_video_exts)) {
            $_SESSION['error_message'] = 'Invalid video format. Allowed: MP4, WEBM, OGG, MOV, AVI, MKV.';
            header('Location: ../dashboard/dashboard.php');
            exit();
        }
        if ($video_file_size > $max_video_size) {
            $_SESSION['error_message'] = 'Video file is too large. Max 50MB.';
            header('Location: ../dashboard/dashboard.php');
            exit();
        }

        $video_new_filename = uniqid('vid_', true) . '.' . $video_file_ext;
        $video_target_path = $video_upload_dir . $video_new_filename;

        if (move_uploaded_file($video_file['tmp_name'], $video_target_path)) {
            $uploaded_video_info = [
                'file_path' => 'videos/' . $video_new_filename, // Relative to $base_memories_upload_dir
                'original_filename' => $video_original_filename,
                'file_size' => $video_file_size,
                'media_type' => 'video'
            ];
        } else {
            $_SESSION['error_message'] = 'Failed to upload video.';
            header('Location: ../dashboard/dashboard.php');
            exit();
        }
    }

    // Proceed only if there's a description or at least one media file uploaded
    if (!empty($description) || $uploaded_image_info !== null || $uploaded_video_info !== null) {
        $conn->begin_transaction();
        try {
            // 1. Insert into memories table
            $stmt_memory = $conn->prepare("INSERT INTO memories (user_id, description) VALUES (?, ?)");
            if (!$stmt_memory) throw new Exception("Memory prepare failed: " . $conn->error);
            
            $stmt_memory->bind_param("is", $user_id, $description);
            if (!$stmt_memory->execute()) throw new Exception("Memory execute failed: " . $stmt_memory->error);
            
            $memory_id = $conn->insert_id;
            if (!$memory_id) throw new Exception("Failed to get last insert ID for memory.");
            $stmt_memory->close();

            // 2. Insert into memory_media table for each uploaded file
            $media_to_insert = [];
            if ($uploaded_image_info) $media_to_insert[] = $uploaded_image_info;
            if ($uploaded_video_info) $media_to_insert[] = $uploaded_video_info;

            foreach ($media_to_insert as $index => $media_info) {
                $stmt_media = $conn->prepare("INSERT INTO memory_media (memory_id, user_id, media_type, file_path, original_filename, file_size, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt_media) throw new Exception("Media prepare failed: " . $conn->error);
                
                $sort_order = $index; // Simple sort order
                $stmt_media->bind_param("iisssii", $memory_id, $user_id, $media_info['media_type'], $media_info['file_path'], $media_info['original_filename'], $media_info['file_size'], $sort_order);
                
                if (!$stmt_media->execute()) throw new Exception("Media execute failed for {$media_info['media_type']}: " . $stmt_media->error);
                $stmt_media->close();
            }

            $conn->commit();
            $_SESSION['success_message'] = 'Memory uploaded successfully!';
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Memory upload transaction failed: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to save memory: ' . $e->getMessage();
        }
        header('Location: ../dashboard/dashboard.php');
        exit();

    } else {
        $_SESSION['error_message'] = 'Please provide a description, or upload an image or a video for your memory.';
        header('Location: ../dashboard/dashboard.php');
        exit();
    }

} else {
    // Not a POST request, redirect to dashboard
    header('Location: ../dashboard/dashboard.php');
    exit();
}
?>
