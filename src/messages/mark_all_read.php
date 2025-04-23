<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark all unread messages as read for the user's pets
$stmt = $conn->prepare("
    UPDATE messages m 
    JOIN pets p ON m.pet_id = p.id 
    SET m.is_read = 1 
    WHERE p.user_id = ? AND m.is_read = 0
");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update messages']);
}

$stmt->close();
$conn->close();
?> 