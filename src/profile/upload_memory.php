<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description']);
    $upload_dir = '../../uploads/memories/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file = $_FILES['memory_image'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_ext, $allowed)) {
        header('Location: index.php?error=Invalid file format');
        exit();
    }

    $filename = uniqid() . '.' . $file_ext;
    
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $stmt = $conn->prepare("INSERT INTO memories (user_id, image_path, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $_SESSION['user_id'], $filename, $description);
        
        if ($stmt->execute()) {
            header('Location: index.php?success=Memory uploaded successfully');
        } else {
            header('Location: index.php?error=Failed to save memory');
        }
    } else {
        header('Location: index.php?error=Failed to upload image');
    }
} else {
    header('Location: index.php');
}
exit();
