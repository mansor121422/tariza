<?php
session_start();
require_once 'config/database.php';
require_once 'includes/ReservationHandler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

try {
    // Get form data
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $checkin_date = $_POST['checkin_date'];
    $checkout_date = $_POST['checkout_date'];
    $checkin_time = '14:00:00'; // Fixed check-in time at 2 PM
    $checkout_time = '12:00:00'; // Fixed check-out time at 12 PM
    $purpose = trim($_POST['purpose']);
    $num_people = isset($_POST['num_people']) ? (int)$_POST['num_people'] : 0;
    $user_id = (int)$_SESSION['user_id'];

    // Validate required fields
    if (!$room_id || !$checkin_date || !$checkout_date || !$purpose || !$num_people) {
        throw new Exception('All fields are required');
    }

    // Create datetime strings
    $checkin = date('Y-m-d H:i:s', strtotime("$checkin_date $checkin_time"));
    $checkout = date('Y-m-d H:i:s', strtotime("$checkout_date $checkout_time"));

    // Validate dates
    $checkin_datetime = new DateTime($checkin);
    $checkout_datetime = new DateTime($checkout);
    $current_datetime = new DateTime();

    if ($checkin_datetime <= $current_datetime) {
        throw new Exception('Check-in date must be in the future');
    }

    if ($checkout_datetime <= $checkin_datetime) {
        throw new Exception('Check-out date must be after check-in date');
    }

    // Initialize ReservationHandler
    $reservationHandler = new ReservationHandler($conn);
    
    // Create temporary reservation
    $result = $reservationHandler->createReservation(
        $user_id,
        $room_id,
        $checkin,
        $checkout,
        $num_people,
        $purpose
    );
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }

    // Store reservation details in session for payment page
    $_SESSION['temp_reservation'] = $result['details'];
    $_SESSION['temp_reservation']['purpose'] = $purpose;

    // Redirect to payment page
    header('Location: payment.php');
    exit();

} catch (Exception $e) {
    // Store error message in session
    $_SESSION['reservation_error'] = $e->getMessage();
    
    // Redirect back to index with error
    header('Location: index.php');
    exit();
} 