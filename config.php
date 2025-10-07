<?php
// Start session FIRST before any output or configuration
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'vxjtgclw_Spirits');
define('DB_PASS', 'SGL~3^5O?]Xie%!6');
define('DB_NAME', 'vxjtgclw_Spirits');

// Timezone Configuration
date_default_timezone_set('Africa/Nairobi');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Connect to Database
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection failed. Please contact administrator.");
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error. Please contact administrator.");
}

// Helper Functions
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

function respond($success, $message = '', $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}

function requireOwner() {
    requireAuth();
    if ($_SESSION['role'] !== 'owner') {
        header('Location: /pos.php');
        exit;
    }
}

function logActivity($action, $description = '') {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) return;
    
    $user_id = (int)$_SESSION['user_id'];
    $action = sanitize($action);
    $description = sanitize($description);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
}

function getSettings() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return [
        'company_name' => 'Zuri Wines & Spirits',
        'logo_path' => '/logo.jpg',
        'primary_color' => '#ea580c',
        'currency' => 'KSh',
        'tax_rate' => 0,
        'receipt_footer' => ''
    ];
}

function generateSaleNumber() {
    return 'ZWS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function formatCurrency($amount) {
    $settings = getSettings();
    return $settings['currency'] . ' ' . number_format($amount, 2);
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>
