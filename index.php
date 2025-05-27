<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch all available rooms
$stmt = $conn->query("
    SELECT r.*, 
           CASE 
               WHEN EXISTS (
                   SELECT 1 
                   FROM reservations res 
                   WHERE res.room_id = r.id 
                   AND res.status = 'confirmed'
                   AND res.checkin <= NOW() 
                   AND res.checkout >= NOW()
               ) THEN 'unavailable'
               ELSE 'available'
           END as current_status
    FROM rooms r 
    WHERE r.status != 'maintenance'
    ORDER BY r.name
");
$rooms = $stmt->fetchAll();

// Set page title
$page_title = "Available Rooms";
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
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        .room-card {
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 25px;
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .room-image {
            height: 250px;
            object-fit: cover;
            width: 100%;
            border-bottom: 3px solid #007bff;
        }
        .room-image-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .room-image-overlay i {
            font-size: 1.1em;
        }
        .btn-reserve:disabled {
            cursor: not-allowed;
            opacity: 0.8;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-reserve:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        .room-card.unavailable .room-image {
            filter: grayscale(30%);
        }
        .room-card.unavailable .card-body {
            position: relative;
        }
        .room-card.unavailable .card-body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.1);
            pointer-events: none;
        }
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1.25rem 1.25rem 0.5rem;
            background: none;
        }
        .price-tag {
            text-align: right;
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            margin-left: 10px;
        }
        .price-tag h4 {
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0;
            line-height: 1.2;
            color: white;
        }
        .price-tag .text-muted {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
            color: #2c3e50;
        }
        .room-description {
            color: #666;
            font-size: 0.95rem;
            margin: 1rem 0;
            min-height: 60px;
        }
        .features-list {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .features-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        .features-list li:last-child {
            margin-bottom: 0;
        }
        .features-list i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            border-radius: 50%;
            margin-right: 10px;
            font-size: 0.9em;
        }
        .features-list .feature-text {
            font-size: 0.95rem;
            color: #495057;
        }
        .btn-reserve {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-reserve:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
            padding: 0;
            list-style: none;
        }
        .amenity-tag {
            background: #e9ecef;
            color: #495057;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        .amenity-tag i {
            margin-right: 5px;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav">
    <?php
    // Check for reservation error message
    if (isset($_SESSION['reservation_error'])) {
        $error_message = $_SESSION['reservation_error'];
        unset($_SESSION['reservation_error']);
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Reservation Failed',
                    text: '" . addslashes($error_message) . "',
                    confirmButtonColor: '#dc3545'
                });
            });
        </script>";
    }

    // Check for success message
    if (isset($_SESSION['success_message'])) {
        $success_message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '" . addslashes($success_message) . "',
                    confirmButtonColor: '#28a745'
                });
            });
        </script>";
    }
    ?>
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <img src="assets/img/logo.png" alt="Logo" class="brand-image">
                <span class="brand-text font-weight-light">Room Reservation</span>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarCollapse">
                <!-- Left navbar links -->
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a href="reservations.php" class="nav-link">
                            <i class="fas fa-calendar-alt"></i> My Reservations
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
                        <h1 class="m-0">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                    </div>
                    <div class="col-sm-6">
                        <span class="float-sm-right">
                            <span class="badge badge-info">
                                <i class="fas fa-door-open"></i> <?php echo count($rooms); ?> Available Rooms
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="content">
            <div class="container">
                <?php if (empty($rooms)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No rooms are currently available.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($rooms as $room): ?>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <div class="card room-card <?php echo $room['current_status'] === 'unavailable' ? 'unavailable' : ''; ?>">
                                    <div class="position-relative">
                                        <?php if ($room['image_path']): ?>
                                            <img src="<?php echo htmlspecialchars($room['image_path']); ?>" 
                                                 class="room-image" 
                                                 alt="<?php echo htmlspecialchars($room['name']); ?>">
                                        <?php else: ?>
                                            <img src="assets/img/no-image.jpg" 
                                                 class="room-image" 
                                                 alt="No Image">
                                        <?php endif; ?>
                                        <div class="room-image-overlay" style="background: <?php echo $room['current_status'] === 'available' ? 'rgba(40, 167, 69, 0.9)' : 'rgba(220, 53, 69, 0.9)'; ?>">
                                            <i class="fas <?php echo $room['current_status'] === 'available' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            <?php echo $room['current_status'] === 'available' ? 'Available Now' : 'Currently Occupied'; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-header-custom">
                                            <h5 class="card-title"><?php echo htmlspecialchars($room['name']); ?></h5>
                                            <div class="price-tag">
                                                <h4>₱<?php echo number_format($room['price'], 2); ?></h4>
                                                <small class="text-muted">per day</small>
                                            </div>
                                        </div>
                                        <p class="room-description"><?php echo htmlspecialchars($room['description']); ?></p>
                                        
                                        <ul class="features-list list-unstyled">
                                            <li>
                                                <i class="fas fa-users text-info"></i>
                                                <span class="feature-text">Maximum Capacity: <?php echo $room['capacity']; ?> people</span>
                                            </li>
                                            <li>
                                                <i class="fas <?php echo $room['current_status'] === 'available' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'; ?>"></i>
                                                <span class="feature-text">Status: <?php echo ucfirst($room['current_status']); ?></span>   
                                            </li>
                                        </ul>

                                        <ul class="amenities-list">
                                            <li class="amenity-tag">
                                                <i class="fas fa-wifi"></i> Free WiFi
                                            </li>
                                            <li class="amenity-tag">
                                                <i class="fas fa-snowflake"></i> AC
                                            </li>
                                            <li class="amenity-tag">
                                                <i class="fas fa-tv"></i> TV
                                            </li>
                                            <li class="amenity-tag">
                                                <i class="fas fa-bath"></i> Private Bath
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <button type="button" 
                                                class="btn btn-primary btn-reserve"
                                                data-toggle="modal" 
                                                data-target="#reservationModal"
                                                data-room-id="<?php echo $room['id']; ?>"
                                                data-room-name="<?php echo htmlspecialchars($room['name']); ?>"
                                                data-room-capacity="<?php echo $room['capacity']; ?>"
                                                data-room-price="<?php echo $room['price']; ?>"
                                                <?php echo $room['current_status'] === 'unavailable' ? 'disabled' : ''; ?>>
                                            <i class="fas <?php echo $room['current_status'] === 'available' ? 'fa-calendar-plus' : 'fa-lock'; ?>"></i>
                                            <?php echo $room['current_status'] === 'available' ? 'Make Reservation' : 'Currently Unavailable'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
                <form action="process-reservation.php" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="room_id">Select Room</label>
                            <select class="form-control" id="room_id" name="room_id" required>
                                <option value="">Choose a room...</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" 
                                            data-price="<?php echo $room['price']; ?>"
                                            data-capacity="<?php echo $room['capacity']; ?>">
                                        <?php echo htmlspecialchars($room['name']); ?> 
                                        (₱<?php echo number_format($room['price'], 2); ?> - Capacity: <?php echo $room['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="selected-room-info alert alert-info" style="display: none;">
                            <p class="mb-1"><strong>Price:</strong> <span class="room-price">₱0.00</span></p>
                            <p class="mb-0"><strong>Maximum Capacity:</strong> <span class="room-capacity">0</span> people</p>
                        </div>
                        <div class="form-group">
                            <label for="checkin_date">Check-in Date</label>
                            <input type="date" class="form-control" id="checkin_date" name="checkin_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <small class="text-muted">Check-in time is fixed at 2:00 PM</small>
                        </div>
                        <div class="form-group">
                            <label for="checkout_date">Check-out Date</label>
                            <input type="date" class="form-control" id="checkout_date" name="checkout_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <small class="text-muted">Check-out time is fixed at 12:00 PM</small>
                        </div>
                        <div class="form-group">
                            <label for="purpose">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="num_people">Number of People</label>
                            <input type="number" class="form-control" id="num_people" name="num_people" 
                                   min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Submit Reservation</button>
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
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
$(document).ready(function() {
    // Form validation with SweetAlert2
    $('#reservationModal form').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        var checkinDate = $('#checkin_date').val();
        var checkoutDate = $('#checkout_date').val();
        var purpose = $('#purpose').val();
        var numPeople = $('#num_people').val();
        
        // Create Date objects for comparison (using fixed times)
        var checkinDateTime = new Date(checkinDate + 'T14:00:00'); // 2:00 PM
        var checkoutDateTime = new Date(checkoutDate + 'T12:00:00'); // 12:00 PM
        
        // Validation checks
        if (!checkinDate || !checkoutDate || !purpose || !numPeople) {
            Swal.fire({
                icon: 'error',
                title: 'Required Fields Missing',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (checkoutDateTime <= checkinDateTime) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Dates',
                text: 'Check-out date must be after check-in date',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }

        // Add hidden inputs for fixed times
        if (!$('#checkin_time').length) {
            $('<input>').attr({
                type: 'hidden',
                id: 'checkin_time',
                name: 'checkin_time',
                value: '14:00'
            }).appendTo(this);
        }
        
        if (!$('#checkout_time').length) {
            $('<input>').attr({
                type: 'hidden',
                id: 'checkout_time',
                name: 'checkout_time',
                value: '12:00'
            }).appendTo(this);
        }

        // If all validations pass, show confirmation dialog
        Swal.fire({
            title: 'Confirm Reservation',
            html: `
                Are you sure you want to proceed with this reservation?<br><br>
                Check-in: ${checkinDate} at 2:00 PM<br>
                Check-out: ${checkoutDate} at 12:00 PM
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Proceed!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit the form
                this.submit();
            }
        });
    });

    // When check-in date changes, update minimum check-out date
    $('#checkin_date').on('change', function() {
        var checkinDate = $(this).val();
        $('#checkout_date').attr('min', checkinDate);
        
        // If check-out date is before check-in date, update it
        if ($('#checkout_date').val() < checkinDate) {
            $('#checkout_date').val(checkinDate);
        }
    });

    // Handle room selection from cards with SweetAlert2
    $('.btn-reserve').on('click', function() {
        var roomId = $(this).data('room-id');
        var roomName = $(this).data('room-name');
        var roomCapacity = $(this).data('room-capacity');
        
        // Set the selected room in the modal
        $('#room_id').val(roomId).trigger('change');
        
        // Set the maximum number of people based on room capacity
        $('#num_people').attr('max', roomCapacity);
        
        // Show room selection confirmation
        Swal.fire({
            icon: 'info',
            title: 'Room Selected',
            text: 'You have selected ' + roomName + '. Please proceed with your reservation details.',
            confirmButtonColor: '#007bff',
            timer: 2000,
            timerProgressBar: true
        });
        
        // Open the reservation modal
        $('#reservationModal').modal('show');
        
        // Focus on the check-in date field
        setTimeout(function() {
            $('#checkin_date').focus();
        }, 500);
    });
});
</script>
</body>
</html> 