<?php
session_start();
require_once 'config/database.php';

// Define base path for includes
define('BASE_PATH', __DIR__);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if there's a reservation to display
if (!isset($_SESSION['reservation_success']) || !isset($_SESSION['reservation_details'])) {
    header('Location: index.php');
    exit();
}

$reservation_details = $_SESSION['reservation_details'];

// Get room details
$stmt = $conn->prepare("
    SELECT r.*, rm.name as room_name, rm.image_path 
    FROM reservations r 
    JOIN rooms rm ON r.room_id = rm.id 
    WHERE r.id = ?
");
$stmt->execute([$reservation_details['id']]);
$reservation = $stmt->fetch();

// Clear the session variables
unset($_SESSION['reservation_success']);
unset($_SESSION['reservation_details']);

// Set page title
$page_title = "Reservation Confirmation";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Room Reservation - <?php echo $page_title; ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .confirmation-card {
            max-width: 800px;
            margin: 20px auto;
        }
        .reservation-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .detail-row {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #dee2e6;
            padding-bottom: 10px;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #6c757d;
        }
        .total-price {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .payment-options {
            margin: 30px 0;
        }
        .payment-method-card {
            transition: transform 0.2s;
        }
        .payment-method-card:hover {
            transform: translateY(-5px);
        }
        .payment-icon {
            font-size: 48px;
            color: #28a745;
        }
        .fab.fa-paypal {
            color: #003087;
        }
        #paypal-button-container {
            max-width: 100%;
            margin: 10px auto;
        }
        body.loading::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        body.loading::before {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 10000;
        }
        
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        body.loading .loading-overlay {
            display: block;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <!-- Navbar -->
    <?php include BASE_PATH . '/components/navbar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Reservation Confirmation</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container">
                <div class="card confirmation-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h2 class="mb-4">Thank You for Your Reservation!</h2>
                        <p class="lead">Please select your payment method to complete the reservation:</p>
                        
                        <div class="payment-options mb-4">
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <div class="payment-method-card mb-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-money-bill-wave payment-icon"></i>
                                                <h4 class="mt-3">Cash on Hand</h4>
                                                <p class="text-muted">Pay at our office during check-in</p>
                                                <form method="POST" action="process-payment.php">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation_details['id']; ?>">
                                                    <input type="hidden" name="payment_method" value="cash">
                                                    <button type="submit" class="btn btn-success btn-block">
                                                        <i class="fas fa-check"></i> Select Cash Payment
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="payment-method-card mb-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fab fa-paypal payment-icon"></i>
                                                <h4 class="mt-3">PayPal</h4>
                                                <p class="text-muted">Pay securely via PayPal</p>
                                                <div id="paypal-button-container"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="reservation-details">
                            <div class="detail-row">
                                <span class="detail-label">Reservation ID:</span>
                                <span>#<?php echo str_pad($reservation_details['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Room:</span>
                                <span><?php echo htmlspecialchars($reservation['room_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-in:</span>
                                <span><?php echo date('F j, Y g:i A', strtotime($reservation_details['checkin'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Check-out:</span>
                                <span><?php echo date('F j, Y g:i A', strtotime($reservation_details['checkout'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Number of Days:</span>
                                <span><?php echo $reservation_details['num_days']; ?> day(s)</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Number of People:</span>
                                <span><?php echo $reservation_details['num_people']; ?> person(s)</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Price:</span>
                                <span class="total-price">₱<?php echo number_format($reservation_details['total_price'], 2); ?></span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="text-muted mb-3">A confirmation email will be sent after payment is completed.</p>
                            <div class="btn-group">
                                <a href="reservations.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i> View My Reservations
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

    <!-- Footer -->
    <?php include BASE_PATH . '/components/footer.php'; ?>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- PayPal SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=AfnAsy5IAZUZ6wSw8e4-zWmZ3yuVVcL3-TvY3hZrT51Lpg99mSoIogJzyE4jWc-u2tXvBrXjbxvU1xt9&currency=PHP&intent=capture"></script>
<script>
    paypal.Buttons({
        // Customize button (optional)
        style: {
            layout: 'vertical',
            color:  'blue',
            shape:  'rect',
            label:  'paypal'
        },

        // Create order
        createOrder: function(data, actions) {
            const amount = '<?php echo number_format($reservation_details['total_price'], 2, '.', ''); ?>';
            console.log('Creating order for amount:', amount);
            
            return actions.order.create({
                purchase_units: [{
                    description: 'Room Reservation #<?php echo str_pad($reservation_details['id'], 6, '0', STR_PAD_LEFT); ?>',
                    amount: {
                        currency_code: 'PHP',
                        value: amount
                    },
                    reference_id: '<?php echo $reservation_details['id']; ?>'
                }],
                application_context: {
                    shipping_preference: 'NO_SHIPPING'
                }
            });
        },

        // Handle approve
        onApprove: function(data, actions) {
            // Show loading state
            document.body.classList.add('loading');
            console.log('Payment approved:', data);

            return actions.order.capture().then(function(orderData) {
                console.log('Capture result:', orderData);
                
                // Send the payment details to our server
                return fetch('user/payment-success.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        orderID: data.orderID,
                        reservation_id: '<?php echo $reservation_details['id']; ?>',
                        paymentData: orderData
                    })
                })
                .then(response => response.json())
                .then(result => {
                    console.log('Server response:', result);
                    
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Payment Successful!',
                            text: 'Your reservation has been confirmed.',
                            confirmButtonText: 'View My Reservations'
                        }).then((result) => {
                            window.location.href = 'user/dashboard.php';
                        });
                    } else {
                        throw new Error(result.error || 'Payment verification failed');
                    }
                })
                .catch(error => {
                    console.error('Payment processing error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Processing Failed',
                        text: error.message || 'There was an error processing your payment. Please try again.',
                        confirmButtonText: 'OK'
                    });
                })
                .finally(() => {
                    document.body.classList.remove('loading');
                });
            });
        },

        // Handle errors
        onError: function(err) {
            console.error('PayPal Error:', err);
            Swal.fire({
                icon: 'error',
                title: 'Payment Error',
                text: 'There was an error with PayPal. Please try again.',
                confirmButtonText: 'OK'
            });
        },

        // Handle cancel
        onCancel: function(data) {
            console.log('Payment cancelled:', data);
            fetch('user/payment-cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    reservation_id: '<?php echo $reservation_details['id']; ?>'
                })
            })
            .then(response => response.json())
            .then(result => {
                Swal.fire({
                    icon: 'info',
                    title: 'Payment Cancelled',
                    text: 'You have cancelled the payment. Your reservation is still pending.',
                    confirmButtonText: 'OK'
                });
            });
        }
    }).render('#paypal-button-container');
</script>

<!-- Add SweetAlert2 for better notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Add loading overlay -->
<div class="loading-overlay">
    <div class="loading-spinner"></div>
</div>
</body>
</html> 