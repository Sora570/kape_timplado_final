<?php
// db/inventory_add.php
// Suppress error output to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db_connect.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Failed to load database connection: ' . $e->getMessage()]);
    exit;
}

$inventoryName = trim($_POST['InventoryName'] ?? '');
$size = trim($_POST['Size'] ?? '');
$unit = trim($_POST['Unit'] ?? '');
$currentStock = floatval($_POST['Current_Stock'] ?? 0);
$costPrice = floatval($_POST['Cost_Price'] ?? 0);
$reorderPoint = floatval($_POST['reorder_point'] ?? 0);
$qtyPerOrder = trim($_POST['qty_per_order'] ?? '');
$status = trim($_POST['Status'] ?? 'In_Stock');
$reorderPoint = max(0, $reorderPoint);

// Validate input
if (!$inventoryName || !$size || !$unit) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

if ($currentStock < 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Stock values cannot be negative']);
    exit;
}

if ($costPrice < 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Prices cannot be negative']);
    exit;
}
$totalValue = round($currentStock * $costPrice, 2);

$conn->begin_transaction();

try {
    // Check if inventory entry already exists
    $checkStmt = $conn->prepare("SELECT inventoryID FROM inventory WHERE `InventoryName` = ? AND `Size` = ? AND `Unit` = ?");
    $checkStmt->bind_param('sss', $inventoryName, $size, $unit);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Inventory entry already exists for this item']);
        exit;
    }

    // Insert new inventory entry
    $stmt = $conn->prepare("INSERT INTO inventory (`InventoryName`, `Size`, `Unit`, `Current_Stock`, `Cost_Price`, `Total_Value`, `reorder_point`, `qty_per_order`, `Status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Prepare value for qty_per_order
    $qtyValue = $qtyPerOrder !== '' ? $qtyPerOrder : null;
    
    $stmt->bind_param(
        'sssddddds',
        $inventoryName,
        $size,
        $unit,
        $currentStock,
        $costPrice,
        $totalValue,
        $reorderPoint,
        $qtyValue,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $inventoryID = $conn->insert_id;

    $conn->commit();
    
    // Clear any unexpected output before sending JSON
    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'Inventory entry added successfully', 'inventoryID' => $inventoryID]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'PHP Error: ' . $e->getMessage()]);
    exit;
}

?>
