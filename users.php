<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Don't allow admin deletion
    $stmt = $conn->prepare("
        SELECT r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user && $user['role_name'] !== 'admin') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = 'User deleted successfully';
        } else {
            $error = 'Failed to delete user';
        }
    } else {
        $error = 'Cannot delete admin user';
    }
}

// Fetch all users except current admin
$stmt = $conn->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id != ? 
    ORDER BY u.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Set page title
$page_title = "Manage Users";

// Include header and sidebar
include 'components/header.php';
include 'components/sidebar.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Manage Users</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
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

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Users List</h3>
                </div>
                <div class="card-body">
                    <table id="usersTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role_name'] === 'admin' ? 'danger' : 'info'; ?>">
                                        <?php echo ucfirst($user['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['role_name'] !== 'admin'): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to delete this user? This will also delete all their reservations.')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'components/footer.php'; ?>

<!-- Page specific scripts -->
<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true
        });
    });
</script> 