<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'chat_service.php';

header('Content-Type: application/json');

// Check if conversation_id is provided
if (!isset($_GET['conversation_id']) || empty($_GET['conversation_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing conversation ID']);
    exit;
}

$conversationId = (int)$_GET['conversation_id'];
$userType = isset($_GET['is_owner']) && $_GET['is_owner'] == '1' ? 'owner' : 'founder';

// Mark messages as read
$chatService->markAsRead($conversationId, $userType);

// Get messages for the conversation
$messages = $chatService->getMessages($conversationId);

// Transform messages for the frontend
$formattedMessages = [];
foreach ($messages as $message) {
    $formattedMessages[] = [
        'id' => $message['id'],
        'message' => $message['message'],
        'sender_name' => $message['sender_name'] ?? $message['owner_name'] ?? 'Unknown',
        'sender_type' => $message['message_type'],
        'type' => $message['type'],
        'is_read' => (bool)$message['is_read'],
        'created_at' => $message['created_at'],
        'formatted_time' => date('M j, g:i a', strtotime($message['created_at']))
    ];
}

echo json_encode([
    'status' => 'success',
    'data' => $formattedMessages
]);