<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link">
        <img src="https://adminlte.io/themes/v3/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Reservation System</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="https://adminlte.io/themes/v3/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?php echo $_SESSION['username'] ?? 'User'; ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Home</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="rooms.php" class="nav-link <?php echo $current_page === 'rooms.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-door-open"></i>
                        <p>Available Rooms</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="make-reservation.php" class="nav-link <?php echo $current_page === 'make-reservation.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-calendar-plus"></i>
                        <p>Make Reservation</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reservations.php" class="nav-link <?php echo $current_page === 'reservations.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>My Reservations</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <p>Profile</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside> 