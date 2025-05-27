<?php
// Database configuration
$host = 'localhost';
$dbname = 'reservation_system';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to get user role name
function getUserRole($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['role_name'] : null;
}

// Helper function to check if user has admin role
function isAdmin($conn, $user_id) {
    return getUserRole($conn, $user_id) === 'admin';
}
?> 