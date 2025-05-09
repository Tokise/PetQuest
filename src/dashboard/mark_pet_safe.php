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
    $new_status = trim($_POST['status'] ?? ''); // Expecting 'safe'

    // Validation
    if (!$pet_id) {
        $response['message'] = 'Invalid Pet ID.';
        echo json_encode($response);
        exit();
    }
    if ($new_status !== 'safe') { // Only allow 'safe' for this script
        $response['message'] = 'Invalid status update.';
        echo json_encode($response);
        exit();
    }

    // Verify pet ownership
    $stmt_check_owner = $conn->prepare("SELECT owner_id, status as current_status FROM pets WHERE id = ?");
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
        $response['message'] = 'You are not authorized to update this pet.';
        echo json_encode($response);
        exit();
    }

    // If pet is already safe, no need to update, but still success for idempotency
    if ($pet_data['current_status'] === 'safe') {
        $response['success'] = true;
        $response['message'] = 'Pet is already marked as safe.';
        echo json_encode($response);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Update pet status to 'safe'
        $stmt_update_pet = $conn->prepare("UPDATE pets SET status = ? WHERE id = ? AND owner_id = ?");
        $stmt_update_pet->bind_param("sii", $new_status, $pet_id, $user_id);
        $stmt_update_pet->execute();

        if ($stmt_update_pet->affected_rows > 0) {
            // If the pet was previously 'missing', resolve the report in missing_reports table
            if ($pet_data['current_status'] === 'missing') {
                $stmt_resolve_report = $conn->prepare("UPDATE missing_reports SET status = 'resolved' WHERE pet_id = ? AND status = 'active'");
                $stmt_resolve_report->bind_param("i", $pet_id);
                $stmt_resolve_report->execute();
                // We don't strictly need to check affected_rows for the report update, as the main action is pet status.
            }
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Pet status updated to safe.';
        } else {
            $conn->rollback();
            $response['message'] = 'Failed to update pet status. It might have been updated by another process or already in the desired state.';
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error marking pet safe: " . $e->getMessage());
        $response['message'] = 'A database error occurred: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?> 