<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];
$max_file_size = 5 * 1024 * 1024; // 5MB in bytes

// Define upload paths using constants from config
$profile_upload_path = dirname(dirname(__DIR__)) . '/uploads/profile/';
$cover_upload_path = dirname(dirname(__DIR__)) . '/uploads/cover/';

// Create directories if they don't exist
foreach ([$profile_upload_path, $cover_upload_path] as $path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0777, true)) {
            $response = ['success' => false, 'message' => "Failed to create upload directory: $path"];
            echo json_encode($response);
            exit();
        }
        chmod($path, 0777);
    }
    if (!is_writable($path)) {
        $response = ['success' => false, 'message' => "Upload directory not writable: $path"];
        echo json_encode($response);
        exit();
    }
}

function validateImageUpload($file, $type) {
    global $max_file_size;
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception("Invalid file parameter for $type");
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        throw new Exception(getUploadErrorMessage($file['error']));
    }

    $filename = $file['name'];
    $filesize = $file['size'];
    $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if ($filesize > $max_file_size) {
        throw new Exception("$type file size exceeds 5MB limit");
    }
    
    if (!in_array($filetype, $allowed)) {
        throw new Exception("Invalid file type for $type. Allowed: JPG, JPEG, PNG, GIF");
    }
    
    return $filetype;
}

if (isset($_POST['update_profile'])) {
    try {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($name)) {
            throw new Exception('Name cannot be empty');
        }
        
        $updates_made = false;
        $conn->begin_transaction();
        
        // Update basic info
        $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $bio, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update profile info");
        }
        $updates_made = true;

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $filetype = validateImageUpload($_FILES['profile_picture'], 'Profile picture');
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $filetype;
            $upload_path = $profile_upload_path . $new_filename;

            // Get old profile picture to delete later
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $old_profile = $stmt->get_result()->fetch_assoc()['profile_picture'];

            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                throw new Exception('Failed to save profile picture file');
            }

            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile picture in database");
            }

            // Delete old profile picture if it exists
            if ($old_profile && $old_profile !== 'default-profile.png') {
                $old_path = $profile_upload_path . $old_profile;
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            $updates_made = true;
        }

        // Handle cover photo upload
        if (isset($_FILES['cover_picture']) && $_FILES['cover_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $filetype = validateImageUpload($_FILES['cover_picture'], 'Cover photo');
            $new_filename = 'cover_' . $user_id . '_' . time() . '.' . $filetype;
            $upload_path = $cover_upload_path . $new_filename;

            // Debug information
            error_log("Uploading cover photo:");
            error_log("File type: " . $filetype);
            error_log("New filename: " . $new_filename);
            error_log("Upload path: " . $upload_path);

            // Get old cover photo to delete later
            $stmt = $conn->prepare("SELECT cover_picture FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $old_cover = $stmt->get_result()->fetch_assoc()['cover_picture'];

            if (!move_uploaded_file($_FILES['cover_picture']['tmp_name'], $upload_path)) {
                error_log("Failed to move uploaded file to: " . $upload_path);
                throw new Exception('Failed to save cover photo file');
            }

            error_log("File uploaded successfully to: " . $upload_path);

            $stmt = $conn->prepare("UPDATE users SET cover_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if (!$stmt->execute()) {
                error_log("Failed to update database with new cover picture: " . $new_filename);
                throw new Exception("Failed to update cover photo in database");
            }

            error_log("Database updated successfully with new cover picture: " . $new_filename);

            // Delete old cover photo if it exists
            if ($old_cover && $old_cover !== 'default-cover.jpg') {
                $old_path = $cover_upload_path . $old_cover;
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            $updates_made = true;
        }

        // Commit transaction if we made it this far
        $conn->commit();
        $response = ['success' => true, 'message' => $updates_made ? 'Profile updated successfully' : 'No changes were made'];

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Profile update error: " . $e->getMessage());
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>