<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to send messages';
    echo json_encode($response);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Check if all required parameters are present
if (!isset($_POST['pet_id']) || !isset($_POST['message']) || !isset($_POST['user_id'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Get and sanitize input
$petId = filter_input(INPUT_POST, 'pet_id', FILTER_SANITIZE_NUMBER_INT);
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
$recipientId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

// Validate input
if (empty($petId) || empty($message) || empty($recipientId)) {
    $response['message'] = 'Invalid parameters';
    echo json_encode($response);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify pet exists and belongs to recipient
    $checkPetStmt = $conn->prepare("SELECT * FROM pets WHERE id = ? AND user_id = ?");
    $checkPetStmt->execute([$petId, $recipientId]);
    
    if ($checkPetStmt->rowCount() === 0) {
        $response['message'] = 'Invalid pet selected';
        echo json_encode($response);
        exit;
    }
    
    // Insert message
    $insertStmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, pet_id, message, sent_at, is_read) 
                                 VALUES (?, ?, ?, ?, NOW(), 0)");
    
    $result = $insertStmt->execute([$currentUserId, $recipientId, $petId, $message]);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Message sent successfully';
    } else {
        $response['message'] = 'Failed to send message';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response); 