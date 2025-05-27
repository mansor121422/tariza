<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has completed a reservation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['reservation_success'])) {
    header('Location: index.php');
    exit();
}

$reservation_id = $_SESSION['reservation_success'];

// Get reservation details
$stmt = $conn->prepare("
    SELECT r.*, rm.name as room_name, rm.price as room_price,
           u.username, u.email
    FROM reservations r
    JOIN rooms rm ON r.room_id = rm.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->execute([$reservation_id, $_SESSION['user_id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: index.php');
    exit();
}

// Clear the session variables
unset($_SESSION['reservation_success']);
unset($_SESSION['temp_reservation']);

// Include header
include 'components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    <h2 class="mt-4 mb-4">Reservation Confirmed!</h2>
                    
                    <div class="alert alert-success">
                        Your reservation has been successfully processed and confirmed.
                        <?php if ($reservation['payment_method'] === 'cash'): ?>
                            <br>Please remember to bring the payment during check-in.
                        <?php endif; ?>
                    </div>

                    <div class="reservation-details mt-4">
                        <h4>Reservation Details</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Booking Reference:</th>
                                    <td>#<?php echo str_pad($reservation['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                </tr>
                                <tr>
                                    <th>Room:</th>
                                    <td><?php echo htmlspecialchars($reservation['room_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Check-in:</th>
                                    <td><?php echo date('F j, Y g:i A', strtotime($reservation['checkin'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Check-out:</th>
                                    <td><?php echo date('F j, Y g:i A', strtotime($reservation['checkout'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td>₱<?php echo number_format($reservation['total_price'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td><?php echo ucfirst($reservation['payment_method']); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i> 
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="text-muted">A confirmation email has been sent to your email address.</p>
                        <div class="btn-group">
                            <a href="user/dashboard.php" class="btn btn-primary">
                                <i class="fas fa-user"></i> Go to Dashboard
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?> 