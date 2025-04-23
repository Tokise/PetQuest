<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['user_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$user_id = $_SESSION['user_id'];
$other_user_id = $_POST['user_id'];

// Check if conversation already exists
$stmt = $conn->prepare("SELECT id FROM conversations WHERE 
    (owner_id = ? AND founder_id = ?) OR 
    (owner_id = ? AND founder_id = ?) 
    LIMIT 1");
$stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Conversation exists
    echo json_encode(['conversation_id' => $row['id']]);
} else {
    // Create new conversation
    $stmt = $conn->prepare("INSERT INTO conversations (owner_id, founder_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $other_user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['conversation_id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to create conversation']);
    }
}
