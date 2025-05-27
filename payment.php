<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has a temporary reservation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['temp_reservation'])) {
    header('Location: index.php');
    exit();
}

$reservation = $_SESSION['temp_reservation'];

// Include header
include 'components/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title mb-0">Complete Your Reservation</h3>
                </div>
                <div class="card-body">
                    <!-- Reservation Summary -->
                    <div class="reservation-summary mb-4">
                        <h4>Reservation Details</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Room:</th>
                                    <td><?php echo htmlspecialchars($reservation['room']['name']); ?></td>
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
                                    <th>Number of Days:</th>
                                    <td><?php echo $reservation['num_days']; ?></td>
                                </tr>
                                <tr>
                                    <th>Number of People:</th>
                                    <td><?php echo $reservation['num_people']; ?></td>
                                </tr>
                                <tr>
                                    <th>Purpose:</th>
                                    <td><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td class="h4 text-primary">₱<?php echo number_format($reservation['total_price'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Options -->
                    <div class="payment-options">
                        <h4 class="mb-4">Select Payment Method</h4>
                        
                        <div class="row">
                            <!-- PayPal Option -->
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fab fa-paypal fa-3x text-primary mb-3"></i>
                                        <h5>Pay with PayPal</h5>
                                        <p class="text-muted">Secure online payment</p>
                                        <div id="paypal-button-container"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cash Option -->
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                                        <h5>Cash Payment</h5>
                                        <p class="text-muted">Pay at our office during check-in</p>
                                        <form action="process-payment.php" method="POST">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <input type="hidden" name="payment_method" value="cash">
                                            <button type="submit" class="btn btn-success btn-block">
                                                <i class="fas fa-check"></i> Select Cash Payment
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PayPal Script -->
<script src="https://www.paypal.com/sdk/js?client-id=AfnAsy5IAZUZ6wSw8e4-zWmZ3yuVVcL3-TvY3hZrT51Lpg99mSoIogJzyE4jWc-u2tXvBrXjbxvU1xt9&currency=PHP"></script>
<script>
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    description: 'Room Reservation',
                    amount: {
                        currency_code: 'PHP',
                        value: '<?php echo number_format($reservation['total_price'], 2, '.', ''); ?>'
                    }
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Send payment details to server
                return fetch('process-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        reservation_id: '<?php echo $reservation['id']; ?>',
                        payment_method: 'paypal',
                        payment_details: details
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        window.location.href = 'reservation-success.php';
                    } else {
                        alert('Payment processing failed: ' + result.message);
                    }
                });
            });
        },
        onError: function(err) {
            console.error('PayPal Error:', err);
            alert('There was an error processing your payment. Please try again.');
        }
    }).render('#paypal-button-container');
</script>

<?php include 'components/footer.php'; ?> 