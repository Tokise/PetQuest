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
    $response['message'] = 'You must be logged in to report a profile';
    echo json_encode($response);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Check if all required parameters are present
if (!isset($_POST['user_id']) || !isset($_POST['reason'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Get and sanitize input
$reportedUserId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
$reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
$details = filter_input(INPUT_POST, 'details', FILTER_SANITIZE_STRING) ?? '';

// Validate input
if (empty($reportedUserId) || empty($reason)) {
    $response['message'] = 'Invalid parameters';
    echo json_encode($response);
    exit;
}

// Prevent reporting yourself
if ($reportedUserId == $currentUserId) {
    $response['message'] = 'You cannot report your own profile';
    echo json_encode($response);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if reported user exists
    $checkUserStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkUserStmt->execute([$reportedUserId]);
    
    if ($checkUserStmt->rowCount() === 0) {
        $response['message'] = 'User does not exist';
        echo json_encode($response);
        exit;
    }
    
    // Check if already reported
    $checkReportStmt = $conn->prepare("SELECT id FROM profile_reports WHERE reporter_id = ? AND reported_user_id = ? AND status = 'pending'");
    $checkReportStmt->execute([$currentUserId, $reportedUserId]);
    
    if ($checkReportStmt->rowCount() > 0) {
        $response['message'] = 'You have already reported this profile';
        echo json_encode($response);
        exit;
    }
    
    // Insert report
    $insertStmt = $conn->prepare("INSERT INTO profile_reports (reporter_id, reported_user_id, reason, details, reported_at, status) 
                                 VALUES (?, ?, ?, ?, NOW(), 'pending')");
    
    $result = $insertStmt->execute([$currentUserId, $reportedUserId, $reason, $details]);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Profile reported successfully';
    } else {
        $response['message'] = 'Failed to submit report';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
echo json_encode($response); 