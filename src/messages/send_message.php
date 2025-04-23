<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/pdo.php';
require_once 'chat_service.php';

header('Content-Type: application/json');

// Debug logging
error_log('Received message request: ' . json_encode($_POST));

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Check required fields
$requiredFields = ['conversation_id', 'message'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
    ]);
    exit;
}

// Get data from POST
$data = [
    'conversation_id' => $_POST['conversation_id'],
    'message' => $_POST['message'],
    'pet_id' => $_POST['pet_id'] ?? null,
    'is_owner' => isset($_POST['is_owner']) ? $_POST['is_owner'] : '0'
];

// Add owner or founder data
if (filter_var($data['is_owner'], FILTER_VALIDATE_BOOLEAN)) {
    // Owner is sending message
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $data['owner_id'] = $_SESSION['user_id'];
} else {
    // Founder is sending message
    if (!isset($_POST['founder_email']) || empty($_POST['founder_email'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing founder email']);
        exit;
    }
    
    $data['founder_name'] = $_POST['founder_name'] ?? 'Anonymous';
    $data['founder_email'] = $_POST['founder_email'];
}

// Send message using chat service
try {
    $messageData = $chatService->sendMessage($data);

    if ($messageData) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => $messageData
        ]);
    } else {
        http_response_code(500);
        error_log('Failed to send message: No message data returned from chatService');
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Exception sending message: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
} 