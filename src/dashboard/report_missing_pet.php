<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required. Please login.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_id = filter_input(INPUT_POST, 'pet_id', FILTER_VALIDATE_INT);
    $last_seen_date = trim($_POST['last_seen_date'] ?? '');
    $last_seen_location = trim($_POST['last_seen_location'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');

    // Validation
    if (!$pet_id) {
        $response['message'] = 'Invalid Pet ID.';
        echo json_encode($response);
        exit();
    }
    if (empty($last_seen_date)) {
        $response['message'] = 'Last seen date is required.';
        echo json_encode($response);
        exit();
    }
    if (empty($last_seen_location)) {
        $response['message'] = 'Last seen location is required.';
        echo json_encode($response);
        exit();
    }

    // Verify pet ownership
    $stmt_check_owner = $conn->prepare("SELECT owner_id FROM pets WHERE id = ?");
    $stmt_check_owner->bind_param("i", $pet_id);
    $stmt_check_owner->execute();
    $pet_owner_result = $stmt_check_owner->get_result();
    if ($pet_owner_result->num_rows === 0) {
        $response['message'] = 'Pet not found.';
        echo json_encode($response);
        exit();
    }
    $pet_data = $pet_owner_result->fetch_assoc();
    if ($pet_data['owner_id'] !== $user_id) {
        $response['message'] = 'You are not authorized to report this pet as missing.';
        echo json_encode($response);
        exit();
    }

    $conn->begin_transaction();

    try {
        // Update pet status to 'missing'
        $stmt_update_pet = $conn->prepare("UPDATE pets SET status = 'missing' WHERE id = ? AND owner_id = ?");
        $stmt_update_pet->bind_param("ii", $pet_id, $user_id);
        $stmt_update_pet->execute();

        if ($stmt_update_pet->affected_rows > 0) {
            // Insert into missing_reports table
            // Get user email for contact_info (can be retrieved or assumed to be session user's email)
            $userStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $user_contact = $userResult->fetch_assoc();
            $contact_info = $user_contact['email'] ?? 'Not provided';

            $stmt_insert_report = $conn->prepare("INSERT INTO missing_reports (pet_id, owner_id, last_seen_date, last_seen_location, contact_info, additional_info, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt_insert_report->bind_param("iissss", $pet_id, $user_id, $last_seen_date, $last_seen_location, $contact_info, $additional_info);
            $stmt_insert_report->execute();

            if ($stmt_insert_report->affected_rows > 0) {
                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Pet reported missing successfully.';
            } else {
                $conn->rollback();
                $response['message'] = 'Failed to create missing report entry. Pet status was updated, but report failed.'; // More specific error
            }
        } else {
            $conn->rollback();
            $response['message'] = 'Failed to update pet status or pet already marked as missing.';
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error reporting missing pet: " . $e->getMessage()); // Log the actual error
        $response['message'] = 'A database error occurred: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?> 