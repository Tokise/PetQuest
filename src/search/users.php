<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get search query and page
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Changed from 10 to 5
$offset = ($page - 1) * $limit;

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query must be at least 2 characters']);
    exit();
}

try {
    // Get total count first
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM users 
        WHERE (name LIKE ? OR email LIKE ?) 
        AND id != ?
    ");
    
    $searchTerm = "%{$query}%";
    $countStmt->bind_param("ssi", $searchTerm, $searchTerm, $_SESSION['user_id']);
    $countStmt->execute();
    $totalResult = $countStmt->get_result()->fetch_assoc();
    $total = $totalResult['total'];

    // Search for users with pagination
    $stmt = $conn->prepare("
        SELECT id, name, email, profile_picture, bio 
        FROM users 
        WHERE (name LIKE ? OR email LIKE ?) 
        AND id != ? 
        ORDER BY name
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param("ssiii", $searchTerm, $searchTerm, $_SESSION['user_id'], $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($user = $result->fetch_assoc()) {
        $users[] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'profile_picture' => !empty($user['profile_picture']) ? 
                SITE_URL . '/uploads/profile/' . $user['profile_picture'] : 
                SITE_URL . '/assets/images/default-profile.png',
            'bio' => $user['bio'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'hasMore' => ($offset + $limit) < $total,
        'currentPage' => $page,
        'message' => count($users) > 0 ? '' : 'No users found'
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching']);
}