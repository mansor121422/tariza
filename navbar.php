<?php
// Ensure this file is included, not accessed directly
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
?>
<nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
    <div class="container">
        <a href="index.php" class="navbar-brand">
            <img src="assets/img/logo.png" alt="Logo" class="brand-image">
            <span class="brand-text font-weight-light">Room Reservation</span>
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" 
                aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <!-- Left navbar links -->
            <ul class="navbar-nav ml-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a href="admin/dashboard.php" class="nav-link">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin/rooms.php" class="nav-link">
                                <i class="fas fa-door-open"></i> Rooms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin/reservations.php" class="nav-link">
                                <i class="fas fa-calendar-check"></i> Reservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin/users.php" class="nav-link">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reservations.php" class="nav-link">
                                <i class="fas fa-calendar-alt"></i> My Reservations
                            </a>
                        </li>
                    <?php endif; ?>
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
                <?php else: ?>
                    <li class="nav-item">
                        <a href="login.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="register.php" class="nav-link">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 