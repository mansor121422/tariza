<?php
// Ensure this file is included, not accessed directly
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
?>
<footer class="main-footer">
    <div class="container">
        <div class="float-right d-none d-sm-inline">
            Book your space today
        </div>
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#">Room Reservation</a>.</strong> All rights reserved.
    </div>
</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Additional Scripts -->
<?php if(isset($page_specific_scripts)) echo $page_specific_scripts; ?>
</body>
</html> 