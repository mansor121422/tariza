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

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/rooms/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Define room statuses and their properties
$room_statuses = [
    'available' => [
        'label' => 'Available',
        'badge_class' => 'success',
        'icon' => 'check-circle',
        'description' => 'Room is available for booking'
    ],
    'unavailable' => [
        'label' => 'Unavailable',
        'badge_class' => 'danger',
        'icon' => 'times-circle',
        'description' => 'Room is currently occupied'
    ],
    'maintenance' => [
        'label' => 'Maintenance',
        'badge_class' => 'warning',
        'icon' => 'tools',
        'description' => 'Room is under maintenance'
    ],
    'reserved' => [
        'label' => 'Reserved',
        'badge_class' => 'info',
        'icon' => 'calendar-check',
        'description' => 'Room is reserved for future booking'
    ]
];

// Handle room operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_room'])) {
        $name = trim($_POST['name']);
        $capacity = (int)$_POST['capacity'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['room_image']['name'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed)) {
                $new_filename = uniqid('room_') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['room_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/rooms/' . $new_filename;
                } else {
                    $error = 'Failed to upload image';
                }
            } else {
                $error = 'Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.';
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO rooms (name, capacity, description, status, image_path, price) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $capacity, $description, $status, $image_path, $_POST['price']])) {
                $success = 'Room added successfully';
            } else {
                $error = 'Failed to add room';
            }
        }
    } 
    elseif (isset($_POST['edit_room'])) {
        $room_id = (int)$_POST['room_id'];
        $name = trim($_POST['name']);
        $capacity = (int)$_POST['capacity'];
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        $price = floatval($_POST['price']);
        
        // Handle image upload for edit
        $image_path = null;
        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['room_image']['name'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed)) {
                // Delete old image if exists
                $stmt = $conn->prepare("SELECT image_path FROM rooms WHERE id = ?");
                $stmt->execute([$room_id]);
                $old_image = $stmt->fetch()['image_path'];
                if ($old_image && file_exists('../' . $old_image)) {
                    unlink('../' . $old_image);
                }
                
                $new_filename = uniqid('room_') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['room_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/rooms/' . $new_filename;
                } else {
                    $error = 'Failed to upload image';
                }
            } else {
                $error = 'Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.';
            }
        }

        if (empty($error)) {
            if ($image_path) {
                $stmt = $conn->prepare("UPDATE rooms SET name = ?, capacity = ?, description = ?, status = ?, image_path = ?, price = ? WHERE id = ?");
                $result = $stmt->execute([$name, $capacity, $description, $status, $image_path, $price, $room_id]);
            } else {
                $stmt = $conn->prepare("UPDATE rooms SET name = ?, capacity = ?, description = ?, status = ?, price = ? WHERE id = ?");
                $result = $stmt->execute([$name, $capacity, $description, $status, $price, $room_id]);
            }
            
            if ($result) {
                $success = 'Room updated successfully';
            } else {
                $error = 'Failed to update room';
            }
        }
    }
    elseif (isset($_POST['delete_room'])) {
        $room_id = (int)$_POST['room_id'];
        
        // Delete room image if exists
        $stmt = $conn->prepare("SELECT image_path FROM rooms WHERE id = ?");
        $stmt->execute([$room_id]);
        $image_path = $stmt->fetch()['image_path'];
        if ($image_path && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        
        // Check if room has any reservations
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE room_id = ? AND room_id IS NOT NULL");
        $stmt->execute([$room_id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Cannot delete room with existing reservations';
        } else {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            if ($stmt->execute([$room_id])) {
                $success = 'Room deleted successfully';
            } else {
                $error = 'Failed to delete room';
            }
        }
    }
    elseif (isset($_POST['update_room_status'])) {
        $room_id = (int)$_POST['room_id'];
        $new_status = $_POST['status'];
        $current_status = $_POST['current_status'];
        
        if (!array_key_exists($new_status, $room_statuses)) {
            $error = 'Invalid room status';
        } else {
            try {
                // Check for active reservations if changing to maintenance
                if ($new_status === 'maintenance') {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as active_bookings
                        FROM reservations
                        WHERE room_id = ?
                        AND status = 'confirmed'
                        AND checkout > NOW()
                    ");
                    $stmt->execute([$room_id]);
                    if ($stmt->fetch()['active_bookings'] > 0) {
                        throw new Exception('Cannot set room to maintenance - there are active or upcoming reservations');
                    }
                }
                
                // Update room status
                $stmt = $conn->prepare("
                    UPDATE rooms 
                    SET 
                        status = ?,
                        updated_at = NOW(),
                        status_updated_by = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$new_status, $_SESSION['user_id'], $room_id])) {
                    $success = "Room status updated to " . $room_statuses[$new_status]['label'];
                    
                    // Log the status change
                    $stmt = $conn->prepare("
                        INSERT INTO room_status_logs (
                            room_id, 
                            previous_status, 
                            new_status, 
                            changed_by, 
                            changed_at
                        ) VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$room_id, $current_status, $new_status, $_SESSION['user_id']]);
                } else {
                    throw new Exception('Failed to update room status');
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch all rooms
$stmt = $conn->query("SELECT * FROM rooms ORDER BY name");
$rooms = $stmt->fetchAll();

// Set page title
$page_title = "Manage Rooms";

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
                    <h1 class="m-0">Manage Rooms</h1>
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
                    <h3 class="card-title">Rooms List</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRoomModal">
                            <i class="fas fa-plus"></i> Add New Room
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="roomsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Capacity</th>
                                <th>Price</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td>
                                    <?php if ($room['image_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($room['image_path']); ?>" class="room-image" alt="Room Image">
                                    <?php else: ?>
                                        <img src="../assets/img/no-image.jpg" class="room-image" alt="No Image">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($room['name']); ?></td>
                                <td><?php echo $room['capacity']; ?> people</td>
                                <td>₱<?php echo number_format($room['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($room['description']); ?></td>
                                <td>
                                    <?php
                                    $status = $room['status'];
                                    $status_info = $room_statuses[$status] ?? $room_statuses['available'];
                                    ?>
                                    <span class="badge badge-<?php echo $status_info['badge_class']; ?> px-2 py-1">
                                        <i class="fas fa-<?php echo $status_info['icon']; ?> mr-1"></i>
                                        <?php echo $status_info['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#editRoomModal<?php echo $room['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                        <button type="submit" name="delete_room" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to delete this room?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Room Modal -->
                            <div class="modal fade" id="editRoomModal<?php echo $room['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editRoomModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editRoomModalLabel">Edit Room</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form method="POST" action="" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                <div class="form-group">
                                                    <label for="name">Room Name</label>
                                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($room['name']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="capacity">Capacity</label>
                                                    <input type="number" class="form-control" id="capacity" name="capacity" value="<?php echo $room['capacity']; ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="price">Price (₱)</label>
                                                    <input type="number" class="form-control" id="price" name="price" value="<?php echo $room['price']; ?>" step="0.01" min="0" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="description">Description</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($room['description']); ?></textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label for="status">Status</label>
                                                    <select class="form-control" id="status" name="status" required>
                                                        <?php foreach ($room_statuses as $value => $info): ?>
                                                            <option value="<?php echo $value; ?>" 
                                                                    <?php echo isset($room) && $room['status'] === $value ? 'selected' : ''; ?>
                                                                    class="text-<?php echo $info['badge_class']; ?>">
                                                                <?php echo $info['label']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="form-text text-muted status-description"></small>
                                                </div>
                                                <div class="form-group">
                                                    <label for="room_image">Room Image</label>
                                                    <input type="file" class="form-control-file" id="room_image" name="room_image" accept="image/*" onchange="previewImage(this, 'imagePreview<?php echo $room['id']; ?>')">
                                                    <?php if ($room['image_path']): ?>
                                                        <img src="../<?php echo htmlspecialchars($room['image_path']); ?>" id="imagePreview<?php echo $room['id']; ?>" class="room-image-preview mt-2" alt="Room Image">
                                                    <?php else: ?>
                                                        <img src="../assets/img/no-image.jpg" id="imagePreview<?php echo $room['id']; ?>" class="room-image-preview mt-2" alt="No Image">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="submit" name="edit_room" class="btn btn-primary">Save Changes</button>
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

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1" role="dialog" aria-labelledby="addRoomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoomModalLabel">Add New Room</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Room Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Price (₱)</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <?php foreach ($room_statuses as $value => $info): ?>
                                <option value="<?php echo $value; ?>" 
                                        class="text-<?php echo $info['badge_class']; ?>">
                                    <?php echo $info['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted status-description"></small>
                    </div>
                    <div class="form-group">
                        <label for="add_room_image">Room Image</label>
                        <input type="file" class="form-control-file" id="add_room_image" name="room_image" accept="image/*" onchange="previewImage(this, 'addImagePreview')">
                        <img src="../assets/img/no-image.jpg" id="addImagePreview" class="room-image-preview mt-2" alt="No Image">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include footer -->
<?php include 'components/footer.php'; ?>

<!-- Page specific scripts -->
<script>
    $(document).ready(function() {
        $('#roomsTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true
        });
    });

    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $(document).ready(function() {
        // Status description update
        const statusDescriptions = <?php echo json_encode(array_map(function($status) {
            return $status['description'];
        }, $room_statuses)); ?>;
        
        $('select[name="status"]').on('change', function() {
            const status = $(this).val();
            const description = statusDescriptions[status] || '';
            $(this).siblings('.status-description').text(description);
        }).trigger('change');
        
        // Confirm status changes
        $('form').on('submit', function(e) {
            const statusSelect = $(this).find('select[name="status"]');
            if (statusSelect.length && statusSelect.data('original-value') !== statusSelect.val()) {
                e.preventDefault();
                Swal.fire({
                    title: 'Update Room Status?',
                    text: 'This may affect existing reservations.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, update it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $(this).off('submit').submit();
                    }
                });
            }
        });
        
        // Store original status values
        $('select[name="status"]').each(function() {
            $(this).data('original-value', $(this).val());
        });
        
        // Add animation to status changes
        $('.badge').addClass('transition');
    });
</script>

<style>
.badge {
    font-size: 0.875rem;
    transition: all 0.3s ease;
}
.badge i {
    font-size: 0.875rem;
}
.badge:hover {
    transform: scale(1.05);
}
.status-description {
    margin-top: 0.5rem;
    font-style: italic;
}
select option.text-success {
    color: #28a745;
}
select option.text-danger {
    color: #dc3545;
}
select option.text-warning {
    color: #ffc107;
}
select option.text-info {
    color: #17a2b8;
}
</style>