<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isAdmin($conn, $_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Set pge title
$page_title = 'Dashboard';

// Get statistics for dashboard
$stmt = $conn->query("SELECT COUNT(*) as total_reservations FROM reservations");
$total_reservations = $stmt->fetch()['total_reservations'];

$stmt = $conn->query("SELECT COUNT(*) as total_users FROM users WHERE role_id != 1"); // Excluding admins
$total_users = $stmt->fetch()['total_users'];

$stmt = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
$total_rooms = $stmt->fetch()['total_rooms'];

$stmt = $conn->query("SELECT COUNT(*) as pending_reservations FROM reservations WHERE status = 'pending'");
$pending_reservations = $stmt->fetch()['pending_reservations'];

// Get monthly reservation statistics for the current year
$stmt = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM reservations 
    WHERE YEAR(created_at) = YEAR(CURRENT_DATE)
    GROUP BY MONTH(created_at)
    ORDER BY month
");
$monthly_stats = $stmt->fetchAll();

// Get room occupancy data
$stmt = $conn->query("
    SELECT r.name, 
           COUNT(res.id) as total_bookings,
           ROUND(COUNT(res.id) * 100.0 / (
               SELECT COUNT(*) 
               FROM reservations 
               WHERE status = 'confirmed'
           ), 1) as occupancy_rate
    FROM rooms r
    LEFT JOIN reservations res ON r.id = res.room_id AND res.status = 'confirmed'
    GROUP BY r.id, r.name
    ORDER BY total_bookings DESC
    LIMIT 5
");
$room_stats = $stmt->fetchAll();

// Get recent activities
$stmt = $conn->query("
    SELECT 
        'reservation' as type,
        r.id,
        r.status,
        r.created_at as timestamp,
        u.username,
        CONCAT('New reservation by ', u.username) as description
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION
    SELECT 
        'room' as type,
        rm.id,
        rm.status,
        rm.created_at as timestamp,
        'Admin' as username,
        CONCAT('Room ', rm.name, ' was added') as description
    FROM rooms rm
    WHERE rm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY timestamp DESC
    LIMIT 10
");
$recent_activities = $stmt->fetchAll();

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
                    <h1 class="m-0">Dashboard</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Info boxes -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-calendar-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Reservations</span>
                            <span class="info-box-number">
                                <?php echo $total_reservations; ?>
                                <small class="text-success ml-2">
                                    <i class="fas fa-arrow-up"></i> 
                                    <?php 
                                    $stmt = $conn->query("
                                        SELECT COUNT(*) as count 
                                        FROM reservations 
                                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ");
                                    echo $stmt->fetch()['count'] . " new this week";
                                    ?>
                                </small>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Users</span>
                            <span class="info-box-number">
                                <?php echo $total_users; ?>
                                <small class="text-success ml-2">
                                    <i class="fas fa-arrow-up"></i>
                                    <?php 
                                    $stmt = $conn->query("
                                        SELECT COUNT(*) as count 
                                        FROM users 
                                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ");
                                    echo $stmt->fetch()['count'] . " new";
                                    ?>
                                </small>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-door-open"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Available Rooms</span>
                            <span class="info-box-number">
                                <?php 
                                $stmt = $conn->query("
                                    SELECT COUNT(*) as count 
                                    FROM rooms 
                                    WHERE status = 'available'
                                ");
                                echo $stmt->fetch()['count'] . " / " . $total_rooms;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Pending Reservations</span>
                            <span class="info-box-number"><?php echo $pending_reservations; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Monthly Reservations Chart -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Monthly Reservations Overview</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="reservationsChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Room Occupancy -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top Room Occupancy</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Room</th>
                                        <th>Bookings</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($room_stats as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['name']); ?></td>
                                        <td><?php echo $room['total_bookings']; ?></td>
                                        <td>
                                            <div class="progress progress-xs">
                                                <div class="progress-bar bg-success" style="width: <?php echo $room['occupancy_rate']; ?>%"></div>
                                            </div>
                                            <small><?php echo $room['occupancy_rate']; ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Reservations -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Reservations</h3>
                        </div>
                        <div class="card-body table-responsive p-0" style="height: 300px;">
                            <table class="table table-head-fixed text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Check-in</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->query("
                                        SELECT r.*, u.username 
                                        FROM reservations r 
                                        JOIN users u ON r.user_id = u.id 
                                        ORDER BY r.created_at DESC 
                                        LIMIT 10
                                    ");
                                    while ($row = $stmt->fetch()) {
                                        $status_class = match($row['status']) {
                                            'confirmed' => 'success',
                                            'pending' => 'warning',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                        echo "<tr>
                                            <td>{$row['id']}</td>
                                            <td>{$row['username']}</td>
                                            <td>" . date('M d, Y', strtotime($row['checkin'])) . "</td>
                                            <td><span class='badge badge-{$status_class}'>{$row['status']}</span></td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activities</h3>
                        </div>
                        <div class="card-body">
                            <div class="timeline timeline-inverse">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div>
                                        <i class="fas fa-<?php echo $activity['type'] === 'reservation' ? 'calendar-alt' : 'door-open'; ?> bg-primary"></i>
                                        <div class="timeline-item">
                                            <span class="time">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('M d, H:i', strtotime($activity['timestamp'])); ?>
                                            </span>
                                            <h3 class="timeline-header">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </h3>
                                            <?php if ($activity['type'] === 'reservation'): ?>
                                                <div class="timeline-footer">
                                                    <a href="reservations.php?id=<?php echo $activity['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'components/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Reservations Chart
    var ctx = document.getElementById('reservationsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php 
                $months = [];
                $confirmed = [];
                $cancelled = [];
                foreach ($monthly_stats as $stat) {
                    $months[] = date('F', mktime(0, 0, 0, $stat['month'], 1));
                    $confirmed[] = $stat['confirmed'];
                    $cancelled[] = $stat['cancelled'];
                }
                echo json_encode($months);
            ?>,
            datasets: [{
                label: 'Confirmed Reservations',
                data: <?php echo json_encode($confirmed); ?>,
                borderColor: '#28a745',
                tension: 0.1
            }, {
                label: 'Cancelled Reservations',
                data: <?php echo json_encode($cancelled); ?>,
                borderColor: '#dc3545',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script> 