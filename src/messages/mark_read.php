<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if message_id is provided
if (!isset($_POST['message_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ID is required']);
    exit();
}

$message_id = (int)$_POST['message_id'];
$user_id = $_SESSION['user_id'];

// Verify that the message belongs to the user's pet and mark as read
$stmt = $conn->prepare("
    UPDATE messages m 
    JOIN pets p ON m.pet_id = p.id 
    SET m.is_read = 1 
    WHERE m.id = ? AND p.user_id = ?
");
$stmt->bind_param("ii", $message_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found or not for your pet']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update message']);
}

$stmt->close();
$conn->close();
?> 