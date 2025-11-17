<?php
// Suppress error output to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Suppress any output from db_connect.php
    ob_start();
    require_once __DIR__ . '/db_connect.php';
    $dbOutput = ob_get_clean();
    
    // Check if connection was established
    if (!isset($conn) || !$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn->connect_error ?? 'Unknown error'));
    }
    
    require_once __DIR__ . '/audit_log.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'PHP Error: ' . $e->getMessage()]);
    exit;
}

// Basic product info
$productName = $_POST['productName'] ?? '';
$categoryID  = intval($_POST['categoryID'] ?? 0);
$size        = $_POST['size'] ?? '';
$unitID      = intval($_POST['unit_id'] ?? 0);
$price       = floatval($_POST['price'] ?? 0);
$isActive    = isset($_POST['isActive']) && $_POST['isActive'] != '0' ? 1 : 0;

// Validate
if (!$productName || !$categoryID || !$unitID) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: product name, category, or unit']);
    exit;
}

// Get unit details
try {
    $unitStmt = $conn->prepare("SELECT unit_name FROM product_units WHERE unit_id = ?");
    if (!$unitStmt) {
        throw new Exception('Failed to prepare unit query: ' . $conn->error);
    }
    $unitStmt->bind_param("i", $unitID);
    if (!$unitStmt->execute()) {
        throw new Exception('Failed to execute unit query: ' . $unitStmt->error);
    }
    $unitResult = $unitStmt->get_result();
    $unitData = $unitResult->fetch_assoc();
    if (!$unitData) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Unit not found']);
        exit;
    }
    $unitName = $unitData['unit_name'] ?? 'piece';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Error fetching unit: ' . $e->getMessage()]);
    exit;
}

// Insert product
try {
    $stmt = $conn->prepare("INSERT INTO products (productName, categoryID, unit_type, unit_value, base_price, isActive) VALUES (?,?,?,?,?,?)");
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
    }
    // Store literal values in variables for bind_param (requires variables, not literals)
    $unitValue = 1.00;
    $stmt->bind_param("sisddi", $productName, $categoryID, $unitName, $unitValue, $price, $isActive);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute insert: ' . $stmt->error);
    }
    $productID = $stmt->insert_id;
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// If size is provided, add it to sizes table and create price entry
if (!empty($size)) {
    try {
        // Check if size exists, if not create it
        $sizeCheckStmt = $conn->prepare("SELECT sizeID FROM sizes WHERE sizeName = ?");
        if (!$sizeCheckStmt) {
            throw new Exception('Failed to prepare size check: ' . $conn->error);
        }
        $sizeCheckStmt->bind_param("s", $size);
        if (!$sizeCheckStmt->execute()) {
            throw new Exception('Failed to execute size check: ' . $sizeCheckStmt->error);
        }
        $sizeResult = $sizeCheckStmt->get_result();

        if ($sizeResult->num_rows > 0) {
            $sizeData = $sizeResult->fetch_assoc();
            $sizeID = $sizeData['sizeID'];
        } else {
            // Create new size
            $sizeInsertStmt = $conn->prepare("INSERT INTO sizes (sizeName, defaultPrice, isActive) VALUES (?, ?, 1)");
            if (!$sizeInsertStmt) {
                throw new Exception('Failed to prepare size insert: ' . $conn->error);
            }
            $sizeInsertStmt->bind_param("sd", $size, $price);
            if (!$sizeInsertStmt->execute()) {
                throw new Exception('Failed to execute size insert: ' . $sizeInsertStmt->error);
            }
            $sizeID = $sizeInsertStmt->insert_id;
        }

        // Add price entry if price > 0
        if ($price > 0) {
            $priceStmt = $conn->prepare("INSERT INTO product_prices (productID, sizeID, unit_id, price) VALUES (?, ?, ?, ?)");
            if (!$priceStmt) {
                throw new Exception('Failed to prepare price insert: ' . $conn->error);
            }
            $priceStmt->bind_param("iiid", $productID, $sizeID, $unitID, $price);
            if (!$priceStmt->execute()) {
                throw new Exception('Failed to execute price insert: ' . $priceStmt->error);
            }
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Error processing size/price: ' . $e->getMessage()]);
        exit;
    }
}

// Log audit activity
try {
    if (isset($_SESSION['userID'])) {
        logProductActivity($conn, $_SESSION['userID'], 'add', $productName, "Product ID: $productID, Category ID: $categoryID, Unit: $unitName, Size: $size, Price: ₱$price");
    }
} catch (Exception $e) {
    // Log error but don't fail the request
    error_log('Audit log error: ' . $e->getMessage());
}

// Clear any unexpected output
ob_clean();
echo json_encode(['status' => 'success', 'message' => 'Product added successfully', 'productID' => $productID]);
exit;
