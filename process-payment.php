<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to update reservation payment status
function updateReservationPayment($conn, $reservation_id, $payment_method, $payment_details = null) {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Update reservation payment status
        $stmt = $conn->prepare("
            UPDATE reservations 
            SET payment_status = ?, 
                payment_method = ?,
                payment_details = ?,
                payment_date = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        $payment_status = $payment_method === 'cash' ? 'pending' : 'paid';
        $details = $payment_details ? json_encode($payment_details) : null;
        
        $stmt->execute([
            $payment_status,
            $payment_method,
            $details,
            $reservation_id,
            $_SESSION['user_id']
        ]);
        
        // Commit transaction
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        return false;
    }
}

// Handle PayPal payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $response = [
        'success' => false,
        'message' => 'Payment processing failed'
    ];
    
    if (isset($data['reservation_id']) && isset($data['payment_method']) && $data['payment_method'] === 'paypal') {
        if (updateReservationPayment($conn, $data['reservation_id'], 'paypal', $data['payment_details'])) {
            $response = [
                'success' => true,
                'message' => 'Payment processed successfully'
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle Cash on Hand payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash') {
    $reservation_id = (int)$_POST['reservation_id'];
    
    if (updateReservationPayment($conn, $reservation_id, 'cash')) {
        $_SESSION['success_message'] = 'Cash payment option selected. Please pay at our office during check-in.';
        header('Location: payment-success.php');
    } else {
        $_SESSION['error_message'] = 'Failed to process payment selection. Please try again.';
        header('Location: reservations.php');
    }
    exit();
}

// Invalid request
header('Location: index.php');
exit(); 