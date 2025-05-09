<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required. Please login.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memory_id = filter_input(INPUT_POST, 'memory_id', FILTER_VALIDATE_INT);
    $comment_text = trim($_POST['comment_text'] ?? '');

    if (!$memory_id) {
        $response['message'] = 'Invalid Memory ID.';
        echo json_encode($response);
        exit();
    }
    if (empty($comment_text)) {
        $response['message'] = 'Comment text cannot be empty.';
        echo json_encode($response);
        exit();
    }
    if (strlen($comment_text) > 1000) { // Example max length
        $response['message'] = 'Comment is too long (max 1000 characters).';
        echo json_encode($response);
        exit();
    }

    // Check if memory exists (optional but good practice)
    $stmt_check_memory = $conn->prepare("SELECT id FROM memories WHERE id = ?");
    $stmt_check_memory->bind_param("i", $memory_id);
    $stmt_check_memory->execute();
    $memory_result = $stmt_check_memory->get_result();
    if ($memory_result->num_rows === 0) {
        $response['message'] = 'Memory not found.';
        echo json_encode($response);
        exit();
    }
    $stmt_check_memory->close();

    try {
        $stmt = $conn->prepare("INSERT INTO memory_comments (memory_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $memory_id, $user_id, $comment_text);
        
        if ($stmt->execute()) {
            $comment_id = $stmt->insert_id;
            // Fetch the newly added comment along with user details to return
            $new_comment_stmt = $conn->prepare(
                "SELECT mc.*, u.name as user_name, u.profile_picture as user_profile_picture 
                 FROM memory_comments mc 
                 JOIN users u ON mc.user_id = u.id 
                 WHERE mc.id = ?"
            );
            $new_comment_stmt->bind_param("i", $comment_id);
            $new_comment_stmt->execute();
            $new_comment_result = $new_comment_stmt->get_result();
            $comment_data = $new_comment_result->fetch_assoc();
            $new_comment_stmt->close();

            $response['success'] = true;
            $response['message'] = 'Comment posted successfully.';
            $response['comment'] = $comment_data; // Send back the new comment data
        } else {
            error_log("Failed to save comment: " . $stmt->error);
            $response['message'] = 'Failed to save comment: ' . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error posting comment: " . $e->getMessage());
        $response['message'] = 'A database error occurred: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?> 