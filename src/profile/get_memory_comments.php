<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'comments' => [], 'message' => 'An error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit();
}

$memory_id = filter_input(INPUT_GET, 'memory_id', FILTER_VALIDATE_INT);

if (!$memory_id) {
    $response['message'] = 'Invalid Memory ID.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $conn->prepare(
        "SELECT mc.*, u.name as user_name, u.profile_picture as user_profile_picture 
         FROM memory_comments mc 
         JOIN users u ON mc.user_id = u.id 
         WHERE mc.memory_id = ? 
         ORDER BY mc.created_at ASC"
    );
    $stmt->bind_param("i", $memory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();

    $response['success'] = true;
    $response['comments'] = $comments;
    $response['message'] = 'Comments fetched successfully.';

} catch (Exception $e) {
    error_log("Error fetching comments: " . $e->getMessage());
    $response['message'] = 'Database error while fetching comments: ' . $e->getMessage();
}

echo json_encode($response);
?> 