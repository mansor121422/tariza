<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ReservationHandler.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Define valid status transitions and their properties
$status_info = [
    'pending' => [
        'class' => 'warning',
        'icon' => 'clock',
        'transitions' => ['confirmed', 'cancelled']
    ],
    'confirmed' => [
        'class' => 'success',
        'icon' => 'check-circle',
        'transitions' => ['completed', 'cancelled']
    ],
    'completed' => [
        'class' => 'info',
        'icon' => 'check-double',
        'transitions' => []
    ],
    'cancelled' => [
        'class' => 'danger',
        'icon' => 'times-circle',
        'transitions' => []
    ]
];

// Handle reservation status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    try {
        $reservation_id = (int)$_POST['reservation_id'];
        $new_status = $_POST['status'];
        $current_status = $_POST['current_status'];
        
        // Validate status transition
        if (!isset($status_info[$current_status]) || 
            !in_array($new_status, $status_info[$current_status]['transitions'])) {
            throw new Exception("Invalid status transition from " . ucfirst($current_status) . " to " . ucfirst($new_status));
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Get reservation details with room info
        $stmt = $conn->prepare("
            SELECT r.*, rm.name as room_name, rm.status as room_status,
                   u.email, u.username
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found');
        }
        
        if ($reservation['status'] !== $current_status) {
            throw new Exception('Reservation status has been changed by another user');
        }
        
        // Additional validation based on new status
        if ($new_status === 'confirmed') {
            // Check payment status
            if ($reservation['payment_status'] !== 'paid' && $reservation['payment_method'] !== 'cash') {
                throw new Exception('Cannot confirm reservation: Payment is not completed');
            }
            
            // Check for conflicting reservations
            $stmt = $conn->prepare("
                SELECT COUNT(*) as conflicts
                FROM reservations 
                WHERE room_id = ? 
                AND status = 'confirmed'
                AND id != ?
                AND (
                    (checkin < ? AND checkout > ?) OR
                    (checkin < ? AND checkout > ?) OR
                    (checkin >= ? AND checkout <= ?)
                )
            ");
            $stmt->execute([
                $reservation['room_id'],
                $reservation_id,
                $reservation['checkout'],
                $reservation['checkin'],
                $reservation['checkout'],
                $reservation['checkout'],
                $reservation['checkin'],
                $reservation['checkout']
            ]);
            
            if ($stmt->fetch()['conflicts'] > 0) {
                throw new Exception("Cannot confirm reservation: Room {$reservation['room_name']} has conflicting bookings");
            }
            
            // Check if room is under maintenance
            if ($reservation['room_status'] === 'maintenance') {
                throw new Exception("Cannot confirm reservation: Room {$reservation['room_name']} is under maintenance");
            }
        }
        
        // Update reservation status
        $stmt = $conn->prepare("
            UPDATE reservations 
            SET status = ?,
                updated_at = NOW(),
                status_updated_by = ?
            WHERE id = ?
        ");
        
        if (!$stmt->execute([$new_status, $_SESSION['user_id'], $reservation_id])) {
            throw new Exception('Failed to update reservation status');
        }
        
        // Update room status based on reservation status
        if ($new_status === 'confirmed') {
            $stmt = $conn->prepare("
                UPDATE rooms 
                SET status = 'unavailable',
                    last_booked = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reservation['room_id']]);
        } elseif (in_array($new_status, ['cancelled', 'completed'])) {
            // Check if there are other active confirmed reservations
            $stmt = $conn->prepare("
                SELECT COUNT(*) as active_bookings
                FROM reservations 
                WHERE room_id = ? 
                AND status = 'confirmed' 
                AND id != ?
                AND checkout > NOW()
            ");
            $stmt->execute([$reservation['room_id'], $reservation_id]);
            
            if ($stmt->fetch()['active_bookings'] == 0) {
                $stmt = $conn->prepare("
                    UPDATE rooms 
                    SET status = 'available',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$reservation['room_id']]);
            }
        }
        
        // Log the status change
        $stmt = $conn->prepare("
            INSERT INTO reservation_logs (
                reservation_id,
                user_id,
                action,
                old_status,
                new_status,
                created_at
            ) VALUES (?, ?, 'status_change', ?, ?, NOW())
        ");
        $stmt->execute([
            $reservation_id,
            $_SESSION['user_id'],
            $current_status,
            $new_status
        ]);
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success_message'] = "Reservation #{$reservation_id} status updated to " . ucfirst($new_status);
        
        // Send email notification
        try {
            $to = $reservation['email'];
            $subject = "Reservation Status Updated - #{$reservation_id}";
            $message = "Dear " . htmlspecialchars($reservation['username']) . ",\n\n";
            $message .= "Your reservation status has been updated to " . ucfirst($new_status) . ".\n\n";
            $message .= "Reservation Details:\n";
            $message .= "Room: " . htmlspecialchars($reservation['room_name']) . "\n";
            $message .= "Check-in: " . date('F j, Y g:i A', strtotime($reservation['checkin'])) . "\n";
            $message .= "Check-out: " . date('F j, Y g:i A', strtotime($reservation['checkout'])) . "\n";
            $message .= "\nThank you for choosing our service.\n";
            
            $headers = "From: no-reply@example.com";
            
            mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log("Failed to send status update email: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Set page title
$page_title = "Manage Reservations";

// Fetch all reservations with user and room details
$stmt = $conn->query("
    SELECT r.*, 
           u.username as user_name, 
           u.email as user_email,
           rm.name as room_name,
           rm.capacity as room_capacity,
           rm.price as room_price,
           rm.status as room_status
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN rooms rm ON r.room_id = rm.id
    ORDER BY r.created_at DESC
");
$reservations = $stmt->fetchAll();

// Include header
include 'components/header.php';
// Include sidebar
include 'components/sidebar.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Reservations</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Reservations List</h3>
                </div>
                <div class="card-body">
                    <table id="reservationsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>People</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo str_pad($reservation['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($reservation['user_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($reservation['user_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($reservation['room_name']); ?><br>
                                    <small class="text-muted">Capacity: <?php echo $reservation['room_capacity']; ?></small>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($reservation['checkin'])); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($reservation['checkout'])); ?></td>
                                <td><?php echo $reservation['num_people']; ?></td>
                                <td>₱<?php echo number_format($reservation['total_price'], 2); ?></td>
                                <td>
                                    <?php 
                                    $status = $reservation['status'];
                                    $info = $status_info[$status] ?? ['class' => 'secondary', 'icon' => 'question-circle'];
                                    ?>
                                    <span class="badge badge-<?php echo $info['class']; ?> px-2 py-1">
                                        <i class="fas fa-<?php echo $info['icon']; ?> mr-1"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" 
                                            data-target="#viewReservationModal<?php echo $reservation['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" 
                                            data-target="#updateStatusModal<?php echo $reservation['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- View Reservation Modal -->
                            <div class="modal fade" id="viewReservationModal<?php echo $reservation['id']; ?>">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">Reservation Details</h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="reservation-details">
                                                <p><strong>Reservation ID:</strong> #<?php echo str_pad($reservation['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                                <p><strong>User:</strong> <?php echo htmlspecialchars($reservation['user_name']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($reservation['user_email']); ?></p>
                                                <p><strong>Room:</strong> <?php echo htmlspecialchars($reservation['room_name']); ?></p>
                                                <p><strong>Check-in:</strong> <?php echo date('F j, Y g:i A', strtotime($reservation['checkin'])); ?></p>
                                                <p><strong>Check-out:</strong> <?php echo date('F j, Y g:i A', strtotime($reservation['checkout'])); ?></p>
                                                <p><strong>Number of People:</strong> <?php echo $reservation['num_people']; ?></p>
                                                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($reservation['purpose']); ?></p>
                                                <p><strong>Total Price:</strong> ₱<?php echo number_format($reservation['total_price'], 2); ?></p>
                                                <p><strong>Status:</strong> <?php echo ucfirst($reservation['status']); ?></p>
                                                <p><strong>Created At:</strong> <?php echo date('F j, Y g:i A', strtotime($reservation['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateStatusModal<?php echo $reservation['id']; ?>">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">Update Reservation Status</h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $reservation['status']; ?>">
                                                <div class="form-group">
                                                    <label>Current Status</label>
                                                    <div class="current-status mb-3">
                                                        <span class="badge badge-<?php echo $status_info[$reservation['status']]['class']; ?> px-3 py-2">
                                                            <i class="fas fa-<?php echo $status_info[$reservation['status']]['icon']; ?> mr-1"></i>
                                                            <?php echo ucfirst($reservation['status']); ?>
                                                        </span>
                                                    </div>
                                                    <label for="status">Update Status To</label>
                                                    <select class="form-control" name="status" required>
                                                        <?php
                                                        $available_statuses = $status_info[$reservation['status']]['transitions'];
                                                        if (!empty($available_statuses)):
                                                            foreach ($available_statuses as $status):
                                                                $info = $status_info[$status];
                                                        ?>
                                                            <option value="<?php echo $status; ?>" class="text-<?php echo $info['class']; ?>">
                                                                <?php echo ucfirst($status); ?>
                                                            </option>
                                                        <?php 
                                                            endforeach;
                                                        else:
                                                        ?>
                                                            <option value="" disabled selected>No status changes available</option>
                                                        <?php endif; ?>
                                                    </select>
                                                    <?php if (empty($available_statuses)): ?>
                                                        <small class="text-muted">
                                                            This reservation's status cannot be changed further.
                                                            <?php if ($reservation['status'] === 'cancelled'): ?>
                                                                Cancelled reservations cannot be reactivated.
                                                            <?php elseif ($reservation['status'] === 'completed'): ?>
                                                                Completed reservations cannot be modified.
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (in_array('confirmed', $available_statuses)): ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i> 
                                                        Confirming this reservation will check for scheduling conflicts and update room availability.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="submit" name="update_status" class="btn btn-primary" 
                                                        <?php echo empty($available_statuses) ? 'disabled' : ''; ?>>
                                                    Update Status
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Include footer -->
<?php include 'components/footer.php'; ?>

<!-- Page specific scripts -->
<script>
    $(document).ready(function() {
        $('#reservationsTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "order": [[3, "desc"]] // Sort by check-in date by default
        });
        
        // Add animation to status changes
        $('.badge').addClass('transition');
        
        // Confirm status changes
        $('form[name="update_status"]').on('submit', function(e) {
            e.preventDefault();
            const currentStatus = $(this).find('input[name="current_status"]').val();
            const newStatus = $(this).find('select[name="status"]').val();
            
            Swal.fire({
                title: 'Update Status?',
                html: `Are you sure you want to change the status from <b>${currentStatus}</b> to <b>${newStatus}</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    });
</script> 