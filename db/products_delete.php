<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

$productID = intval($_POST['productID'] ?? 0);

if (!$productID) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
    exit;
}

try {
    // Get product name for audit log
    $productQuery = $conn->prepare("SELECT productName FROM products WHERE productID = ?");
    $productQuery->bind_param("i", $productID);
    $productQuery->execute();
    $productResult = $productQuery->get_result();
    $product = $productResult->fetch_assoc();
    $productName = $product ? $product['productName'] : 'Unknown';

    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE productID = ?");
    $stmt->bind_param("i", $productID);
    
    if ($stmt->execute()) {
        // Clean up production cost overrides from database
        // Note: If foreign keys are set up with CASCADE, this will happen automatically
        $deleteOverridesStmt = $conn->prepare("DELETE FROM production_cost_overrides WHERE productID = ?");
        $deleteOverridesStmt->bind_param("i", $productID);
        $deleteOverridesStmt->execute();
        $deleteOverridesStmt->close();
        
        // Log audit activity
        if (isset($_SESSION['userID'])) {
            logProductActivity($conn, $_SESSION['userID'], 'delete', $productName, "Product ID: $productID");
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>