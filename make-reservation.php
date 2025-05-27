<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $reservation_date = $_POST['reservation_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $purpose = trim($_POST['purpose']);
    $num_people = (int)$_POST['num_people'];

    // Validation
    if (empty($reservation_date) || empty($start_time) || empty($end_time) || empty($purpose) || $num_people < 1) {
        $error = 'Please fill in all fields';
    } elseif (strtotime($reservation_date) < strtotime(date('Y-m-d'))) {
        $error = 'Reservation date cannot be in the past';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = 'End time must be after start time';
    } else {
        // Check for conflicting reservations
        $stmt = $conn->prepare("SELECT id FROM reservations 
            WHERE reservation_date = ? 
            AND ((start_time <= ? AND end_time > ?) 
            OR (start_time < ? AND end_time >= ?)
            OR (start_time >= ? AND end_time <= ?))
            AND status != 'cancelled'");
        $stmt->execute([$reservation_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
        
        if ($stmt->fetch()) {
            $error = 'This time slot is already booked';
        } else {
            // Create reservation
            $stmt = $conn->prepare("INSERT INTO reservations (user_id, reservation_date, start_time, end_time, purpose, num_people) 
                VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $reservation_date, $start_time, $end_time, $purpose, $num_people])) {
                $success = 'Reservation created successfully!';
            } else {
                $error = 'Failed to create reservation. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Reservation - Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Reservation System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="reservations.php">My Reservations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="form-container">
                    <h2 class="text-center mb-4">Make a Reservation</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="reservation_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="reservation_date" name="reservation_date" 
                                min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="num_people" class="form-label">Number of People</label>
                            <input type="number" class="form-control" id="num_people" name="num_people" 
                                min="1" max="50" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Make Reservation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 