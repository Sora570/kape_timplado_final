<?php
// Start output buffering to catch any unexpected output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

// Clear any output that might have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    $cat = trim($_POST['categoryName'] ?? '');
    if (!$cat) {
        echo json_encode(['status'=>'error','message'=>'Category name required']);
        exit;
    }

    // Check if category already exists
    $checkStmt = $conn->prepare("SELECT categoryID FROM categories WHERE categoryName = ?");
    $checkStmt->bind_param("s", $cat);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        $checkStmt->close();
        echo json_encode(['status'=>'error','message'=>'Category name already exists']);
        exit;
    }
    $checkStmt->close();

    $stmt = $conn->prepare("INSERT INTO categories (categoryName, isActive) VALUES (?, 1)");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $cat);

    if ($stmt->execute()) {
        $categoryID = $conn->insert_id;
        
        // Log audit activity
        if (isset($_SESSION['userID'])) {
            logCategoryActivity($conn, $_SESSION['userID'], 'add', $cat, "Category ID: $categoryID");
        }
        
        echo json_encode(['status'=>'success','categoryID'=>$categoryID]);
    } else {
        // Check for duplicate entry error
        if ($conn->errno == 1062) {
            echo json_encode(['status'=>'error','message'=>'Category name already exists']);
        } else {
            echo json_encode(['status'=>'error','message'=>$stmt->error]);
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
