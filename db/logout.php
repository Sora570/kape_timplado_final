<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

// Log logout activity if user is logged in
if (isset($_SESSION['userID']) && isset($_SESSION['username'])) {
    try {
        logLogout($conn, $_SESSION['userID'], $_SESSION['username']);
    } catch (Exception $e) {
        // Don't fail logout if audit logging fails
        error_log("Audit logging on logout failed: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Return JSON response for better error handling
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>