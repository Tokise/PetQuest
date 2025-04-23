<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if pet_id is provided
if (!isset($_POST['pet_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Pet ID is required']);
    exit();
}

$pet_id = $_POST['pet_id'];
$user_id = $_SESSION['user_id'];

// Verify pet ownership and update status
$stmt = $conn->prepare("UPDATE pets SET status = 'found' WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $pet_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Add success message to session
        $_SESSION['success_message'] = "Pet has been marked as found!";
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Pet not found or not owned by user']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update pet status']);
}

$stmt->close();
$conn->close();
?> 