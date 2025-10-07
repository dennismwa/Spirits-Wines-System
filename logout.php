<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    logActivity('Logout', 'User logged out');
    
    if (isset($_SESSION['session_token'])) {
        $stmt = $conn->prepare("UPDATE sessions SET logout_time = NOW() WHERE session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
        $stmt->close();
    }
}

session_destroy();
header('Location: /index');
exit;
?>
