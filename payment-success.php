<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set page title
$page_title = "Payment Success";
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
        .success-card {
            max-width: 600px;
            margin: 40px auto;
        }
        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .success-message {
            color: #28a745;
            font-size: 24px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <!-- Navbar -->
    <?php include 'components/navbar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Payment Success</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container">
                <div class="card success-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h2 class="success-message">Payment Processed Successfully!</h2>
                        
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <p class="lead"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
                        <?php else: ?>
                            <p class="lead">Your payment has been processed and your reservation is confirmed.</p>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">A confirmation email has been sent to your registered email address.</p>
                        
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

    <!-- Footer -->
    <?php include 'components/footer.php'; ?>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>