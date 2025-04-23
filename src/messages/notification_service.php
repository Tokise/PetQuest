<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;
    $messageId = isset($data['message_id']) ? (int)$data['message_id'] : 0;
    $userType = isset($data['is_owner']) ? 'owner' : 'founder';
    
    if ($conversationId && $messageId) {
        // Update message read status
        $table = $userType === 'owner' ? 'founder_messages' : 'owner_messages';
        $stmt = $conn->prepare("UPDATE $table SET is_read = 1 WHERE id = ? AND conversation_id = ?");
        $stmt->bind_param("ii", $messageId, $conversationId);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
