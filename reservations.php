<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Handle reservation cancellation
if (isset($_POST['cancel_reservation']) && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Verify ownership of the reservation and get room_id
        $stmt = $conn->prepare("SELECT id, room_id FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->execute([$reservation_id, $_SESSION['user_id']]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            // Update reservation status
            $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$reservation_id]);
            
            // Check if there are other confirmed reservations for this room
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM reservations 
                WHERE room_id = ? 
                AND status = 'confirmed' 
                AND id != ?
            ");
            $stmt->execute([$reservation['room_id'], $reservation_id]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                // No other confirmed reservations, make room available
                $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $stmt->execute([$reservation['room_id']]);
            }
            
            $conn->commit();
            $success = 'Reservation cancelled successfully';
        } else {
            throw new Exception('Invalid reservation');
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Get user's statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_reservations = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as pending FROM reservations WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_reservations = $stmt->fetch()['pending'];

$stmt = $conn->prepare("SELECT COUNT(*) as confirmed FROM reservations WHERE user_id = ? AND status = 'confirmed'");
$stmt->execute([$_SESSION['user_id']]);
$confirmed_reservations = $stmt->fetch()['confirmed'];

// Fetch user's reservations$stmt = $conn->prepare("    SELECT r.*, rm.name as room_name     FROM reservations r    LEFT JOIN rooms rm ON r.room_id = rm.id     WHERE r.user_id = ?     ORDER BY r.checkin DESC");$stmt->execute([$_SESSION['user_id']]);$reservations = $stmt->fetchAll();

// Fetch available rooms for the modal
$stmt = $conn->query("SELECT * FROM rooms WHERE status = 'available' ORDER BY name");
$available_rooms = $stmt->fetchAll();

// Set page title
$page_title = "My Reservations";
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
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <style>
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        .main-header {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .reservation-card {
            transition: transform 0.2s;
        }
        .reservation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        /* Price Display Styles */
        .selected-room-info {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            margin: 15px 0;
        }
        .selected-room-info p {
            margin: 10px 0;
            font-size: 1.1em;
        }
        .selected-room-info .room-price {
            color: #28a745;
            font-weight: 600;
            font-size: 1.2em;
        }
        .selected-room-info .room-capacity {
            color: #17a2b8;
            font-weight: 600;
        }
        .price-calculation {
            background-color: #f8fff9;
            border: none;
            border-left: 4px solid #28a745;
            margin: 20px 0;
            padding: 15px;
        }
        .price-calculation .alert-heading {
            color: #28a745;
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .price-calculation p {
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .price-calculation .num-days,
        .price-calculation .price-per-day {
            font-weight: 500;
            color: #495057;
        }
        .price-calculation hr {
            border-top: 2px solid #e9ecef;
            margin: 15px 0;
        }
        .price-calculation .total-price {
            color: #28a745;
            font-size: 1.3em;
            font-weight: 700;
        }
        /* Form Field Styles */
        .form-group label {
            font-weight: 600;
            color: #495057;
        }
        .form-control {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .form-text.text-muted {
            font-size: 0.9em;
            margin-top: 5px;
        }
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            border-radius: 8px 8px 0 0;
        }
        .modal-title {
            color: #495057;
            font-weight: 600;
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 8px 8px;
        }
        /* Button Styles */
        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0069d9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <img src="assets/img/logo.png" alt="Logo" class="brand-image">
                <span class="brand-text font-weight-light">Room Reservation</span>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">My Reservations</h1>
                    </div>
                    <div class="col-sm-6">
                        <button type="button" class="btn btn-primary float-sm-right" data-toggle="modal" data-target="#reservationModal">
                            <i class="fas fa-plus"></i> New Reservation
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row">
                    <div class="col-12 col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-calendar-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total</span>
                                <span class="info-box-number"><?php echo $total_reservations; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Pending</span>
                                <span class="info-box-number"><?php echo $pending_reservations; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Confirmed</span>
                                <span class="info-box-number"><?php echo $confirmed_reservations; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reservations List -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($reservations)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Reservations Yet</h4>
                                <p class="text-muted">Click the "New Reservation" button to make your first reservation.</p>
                            </div>
                        <?php else: ?>
                            <table id="reservationsTable" class="table table-bordered table-striped">
                                                                <thead>                                    <tr>                                        <th>Room</th>                                        <th>Check-in</th>                                        <th>Check-out</th>                                        <th>Purpose</th>                                        <th>Status</th>                                        <th>Actions</th>                                    </tr>                                </thead>                                <tbody>                                    <?php foreach ($reservations as $reservation): ?>                                    <tr>                                        <td><?php echo htmlspecialchars($reservation['room_name']); ?></td>                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['checkin'])); ?></td>                                        <td><?php echo date('M d, Y g:i A', strtotime($reservation['checkout'])); ?></td>                                        <td><?php echo htmlspecialchars($reservation['purpose']); ?></td>                                        <td>                                            <span class="badge badge-<?php                                                 echo $reservation['status'] === 'confirmed' ? 'success' :                                                     ($reservation['status'] === 'pending' ? 'warning' : 'danger');                                             ?>">                                                <?php echo ucfirst($reservation['status']); ?>                                            </span>                                        </td>                                        <td>                                            <?php if ($reservation['status'] != 'cancelled' && strtotime($reservation['checkin']) > time()): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" name="cancel_reservation" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Modal -->
    <div class="modal fade" id="reservationModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">New Reservation</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="process-reservation.php" method="POST" id="reservationForm">
                    <div class="modal-body">
                        <!-- Room Selection -->
                        <div class="form-group">
                            <label for="room_id">Select Room</label>
                            <select class="form-control" id="room_id" name="room_id" required>
                                <option value="">Choose a room...</option>
                                <?php foreach ($available_rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" 
                                            data-price="<?php echo $room['price']; ?>"
                                            data-capacity="<?php echo $room['capacity']; ?>">
                                        <?php echo htmlspecialchars($room['name']); ?> 
                                        (Capacity: <?php echo $room['capacity']; ?> - ₱<?php echo number_format($room['price'], 2); ?>/day)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Room Details -->
                        <div class="selected-room-info" style="display: none;">
                            <div class="alert alert-info">
                                <p class="mb-1"><strong>Room Price:</strong> ₱<span class="room-price">0.00</span> per day</p>
                                <p class="mb-0"><strong>Maximum Capacity:</strong> <span class="room-capacity">0</span> people</p>
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="checkin_date">Check-in Date</label>
                                    <input type="date" class="form-control" id="checkin_date" name="checkin_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted">Check-in time is fixed at 2:00 PM</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="checkout_date">Check-out Date</label>
                                    <input type="date" class="form-control" id="checkout_date" name="checkout_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted">Check-out time is fixed at 12:00 PM</small>
                                </div>
                            </div>
                        </div>

                        <!-- Number of People -->
                        <div class="form-group">
                            <label for="num_people">Number of People</label>
                            <input type="number" class="form-control" id="num_people" name="num_people" 
                                   min="1" required>
                            <div class="invalid-feedback">
                                Number of people exceeds room capacity
                            </div>
                        </div>

                        <!-- Purpose -->
                        <div class="form-group">
                            <label for="purpose">Purpose of Stay</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                        </div>

                        <!-- Price Calculation -->
                        <div class="price-calculation" style="display: none;">
                            <div class="alert alert-primary">
                                <h5 class="mb-1">Price Summary</h5>
                                <p class="mb-1">Number of Days: <span class="num-days">0</span></p>
                                <p class="mb-1">Price per Day: ₱<span class="price-per-day">0.00</span></p>
                                <h4 class="mb-0">Total Price: ₱<span class="total-price">0.00</span></h4>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="float-right d-none d-sm-inline">
                Book your space today
            </div>
            <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#">Room Reservation</a>.</strong> All rights reserved.
        </div>
    </footer>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize date inputs with min date
    var today = new Date().toISOString().split('T')[0];
    $('#checkin_date, #checkout_date').attr('min', today);
    
    // Show room info when a room is selected
    $('#room_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price');
        var capacity = selectedOption.data('capacity');
        
        if (price && capacity) {
            $('.room-price').text(price.toFixed(2));
            $('.room-capacity').text(capacity);
            $('.selected-room-info').slideDown();
            
            // Update price calculation if dates are already selected
            calculatePrice();
        } else {
            $('.selected-room-info').slideUp();
            $('.price-calculation').slideUp();
        }
        
        // Reset and validate number of people
        $('#num_people').val('');
        validatePeople();
    });

    // Calculate price when dates change
    $('#checkin_date, #checkout_date').change(function() {
        calculatePrice();
        validateDates();
    });

    // Validate number of people
    $('#num_people').on('input', function() {
        validatePeople();
    });

    // Price calculation function
    function calculatePrice() {
        var checkinDate = $('#checkin_date').val();
        var checkoutDate = $('#checkout_date').val();
        var pricePerDay = parseFloat($('#room_id option:selected').data('price')) || 0;
        
        if (checkinDate && checkoutDate && pricePerDay) {
            var checkin = new Date(checkinDate + 'T14:00:00');
            var checkout = new Date(checkoutDate + 'T12:00:00');
            
            if (checkout > checkin) {
                var timeDiff = checkout.getTime() - checkin.getTime();
                var numDays = Math.ceil(timeDiff / (1000 * 3600 * 24));
                var totalPrice = numDays * pricePerDay;
                
                $('.num-days').text(numDays);
                $('.price-per-day').text(pricePerDay.toFixed(2));
                $('.total-price').text(totalPrice.toFixed(2));
                $('.price-calculation').slideDown();
            }
        }
    }

    // Date validation function
    function validateDates() {
        var checkinDate = $('#checkin_date').val();
        var checkoutDate = $('#checkout_date').val();
        var submitBtn = $('button[type="submit"]');
        
        if (checkinDate && checkoutDate) {
            var checkin = new Date(checkinDate + 'T14:00:00');
            var checkout = new Date(checkoutDate + 'T12:00:00');
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (checkin < today) {
                alert('Check-in date cannot be in the past');
                $('#checkin_date').val('');
                submitBtn.prop('disabled', true);
                return false;
            }
            
            if (checkout <= checkin) {
                alert('Check-out date must be after check-in date');
                $('#checkout_date').val('');
                submitBtn.prop('disabled', true);
                return false;
            }
            
            submitBtn.prop('disabled', false);
            return true;
        }
    }

    // People validation function
    function validatePeople() {
        var numPeople = parseInt($('#num_people').val()) || 0;
        var capacity = parseInt($('#room_id option:selected').data('capacity')) || 0;
        var submitBtn = $('button[type="submit"]');
        
        if (numPeople > capacity) {
            $('#num_people').addClass('is-invalid');
            submitBtn.prop('disabled', true);
        } else {
            $('#num_people').removeClass('is-invalid');
            submitBtn.prop('disabled', false);
        }
    }

    // Form validation
    $('#reservationForm').on('submit', function(e) {
        if (!validateDates()) {
            e.preventDefault();
            return false;
        }
        
        var numPeople = parseInt($('#num_people').val());
        var capacity = parseInt($('#room_id option:selected').data('capacity'));
        
        if (numPeople > capacity) {
            e.preventDefault();
            alert('Number of people exceeds room capacity');
            return false;
        }
        
        // All validations passed
        return true;
    });
});
</script>
</body>
</html> 