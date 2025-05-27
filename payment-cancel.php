<?php
// Prevent PHP errors from being displayed in the response
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';

// Ensure we're sending JSON response
header('Content-Type: application/json');

try {
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Get and validate the POST data
    $raw_data = file_get_contents('php://input');
    if (!$raw_data) {
        throw new Exception('No data received');
    }

    $data = json_decode($raw_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data: ' . json_last_error_msg());
    }
    
    if (!isset($data['reservation_id'])) {
        throw new Exception('Missing reservation ID');
    }

    // Start transaction
    $conn->beginTransaction();

    // Update reservation status
    $stmt = $conn->prepare("
        UPDATE reservations 
        SET payment_status = 'cancelled',
            updated_at = NOW()
        WHERE id = ? 
        AND user_id = ? 
        AND status = 'pending'
        AND payment_status = 'pending'
    ");

    if (!$stmt->execute([$data['reservation_id'], $_SESSION['user_id']])) {
        throw new Exception('Failed to update reservation status');
    }

    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        throw new Exception('Reservation not found or already processed');
    }

    // Log the cancellation
    $stmt = $conn->prepare("
        INSERT INTO payment_logs (
            reservation_id,
            status,
            notes,
            created_at
        ) VALUES (?, 'cancelled', 'Payment cancelled by user', NOW())
    ");

    $stmt->execute([$data['reservation_id']]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment cancelled successfully'
    ]);
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Payment cancellation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
} 