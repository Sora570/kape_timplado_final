<?php
// db/login.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log_function.php';

$identifier = trim($_POST['identifier'] ?? $_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$identifier || !$password) {
    echo json_encode(['status'=>'error','message'=>'Missing credentials']);
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT userID, username, passwordHash, role FROM users WHERE username = ? OR employee_id = ? LIMIT 1");
$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // Use password_verify for hashed passwords
    if (password_verify($password, $row['passwordHash'])) {
        // successful login
        $_SESSION['userID'] = $row['userID'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        // also set a userEmail for existing logAction usage if you want:
        $_SESSION['userEmail'] = $row['username'];

        // Log successful login
        log_login_attempt($row['userID'], $row['username'], true);

        // Return role information for client-side redirection
        echo json_encode(['status'=>'success','role'=>$row['role'],'user'=>$row['username']]);
        exit;
    } else {
        log_login_attempt($row['userID'], $row['username'], false, 'Password mismatch');
        echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
        exit;
    }
} else {
    log_login_attempt(0, $identifier, false, 'Unknown username');
    echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
    exit;
}
