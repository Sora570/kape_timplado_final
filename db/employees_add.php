<?php
header('Content-Type: text/plain');
session_start();

require_once __DIR__ . '/db_connect.php';

try {
    // Check admin access
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("Access denied. Admin permissions required.");
    }
    
    // Basic Information
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Login Information
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $employeeId = trim($_POST['employeeId'] ?? '');
    
    $requiredFields = [
        'First name' => $firstName,
        'Last name' => $lastName,
        'Email' => $email,
        'Phone number' => $phone,
        'Address' => $address,
        'Username' => $username,
        'Employee ID' => $employeeId
    ];
    
    foreach ($requiredFields as $label => $value) {
        if ($value === '') {
            throw new Exception("$label is required");
        }
    }
    
    if (!in_array($role, ['admin', 'cashier'], true)) {
        throw new Exception("Invalid role specified");
    }
    
    $credentialSecret = '';
    if ($role === 'admin') {
        if ($password === '') {
            throw new Exception("Password is required for admin accounts");
        }
        $credentialSecret = $password;
    } else {
        if ($pin === '') {
            throw new Exception("PIN is required for cashier accounts");
        }
        if (!preg_match('/^[0-9]{4}$/', $pin)) {
            throw new Exception("PIN must be a 4-digit number");
        }
        if ($password !== '' && $password !== $pin) {
            throw new Exception("For cashier accounts, the password (if provided) must match the PIN.");
        }
        $credentialSecret = $pin;
    }
    
    $hashedPassword = password_hash($credentialSecret, PASSWORD_DEFAULT);
    
    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT userID FROM users WHERE username = ?");
    $checkStmt->bind_param('s', $username);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        throw new Exception("Username already exists");
    }
    $checkStmt->close();
    
    // Check if employee ID already exists
    $checkEmployeeId = $conn->prepare("SELECT userID FROM users WHERE employee_id = ?");
    $checkEmployeeId->bind_param('s', $employeeId);
    $checkEmployeeId->execute();
    
    if ($checkEmployeeId->get_result()->num_rows > 0) {
        throw new Exception("Employee ID already exists");
    }
    $checkEmployeeId->close();
    
    // Insert new employee with all information
    $stmt = $conn->prepare("INSERT INTO users (username, passwordHash, role, employee_id, first_name, last_name, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sssssssss', $username, $hashedPassword, $role, $employeeId, $firstName, $lastName, $email, $phone, $address);
    
    if ($stmt->execute()) {
        $newUserID = $conn->insert_id;
        
        // Log the action
        $auditStmt = $conn->prepare("INSERT INTO audit_logs (userID, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $auditAction = "employee_added";
        $auditDetails = "Admin added new employee: " . $username;
        $auditStmt->bind_param('iss', $_SESSION['userID'], $auditAction, $auditDetails);
        $auditStmt->execute();
        
        echo "success";
    } else {
        throw new Exception("Failed to create employee account");
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo $e->getMessage();
}
?>
