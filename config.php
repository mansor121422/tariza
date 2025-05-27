<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tariza_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Create PDO connection
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // Set default timezone
    date_default_timezone_set('Asia/Manila');
    
} catch(PDOException $e) {
    die("Database connection failed. Please ensure MySQL is running and try again.");
}

// Session configuration (only for web requests)
if (php_sapi_name() !== 'cli') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session timeout to 30 minutes
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Ensure logs directory exists
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// XAMPP-specific database configuration
$db_config = [
    'host' => '127.0.0.1', // Use IP instead of localhost
    'port' => '3306',
    'dbname' => 'tariza_db',
    'user' => 'root',
    'pass' => ''
];

try {
    // Create DSN with explicit parameters
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        $db_config['host'],
        $db_config['port'],
        $db_config['dbname']
    );

    // Create PDO connection
    $conn = new PDO(
        $dsn,
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_TIMEOUT => 5, // 5 seconds timeout
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );

    // Test the connection
    $conn->query("SELECT 1");

} catch (PDOException $e) {
    // Log the detailed error
    $error_message = sprintf(
        "Database connection failed: %s\nDSN: %s\nTrace: %s",
        $e->getMessage(),
        $dsn,
        $e->getTraceAsString()
    );
    error_log($error_message);
    
    // If this is an API endpoint (expecting JSON)
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
        strpos($_SERVER['REQUEST_URI'], '/user/payment-') !== false) {
        header('Content-Type: application/json');
        http_response_code(503); // Service Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed. Please ensure MySQL is running.',
            'debug' => [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]
        ]);
        exit();
    }
    
    // For regular pages
    die("Database connection failed. Please ensure MySQL is running and try again.");
} 