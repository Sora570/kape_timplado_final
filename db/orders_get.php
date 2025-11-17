<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$userID = $_SESSION['userID'];

// --- NEW FUNCTION: Parse the JSON item string into a readable format ---
function formatOrderItems($json_items, $conn) {
    if (!$json_items) return 'No items found.';

    // Decode the JSON string into a PHP array
    $items = json_decode($json_items, true);
    if (!is_array($items) || empty($items)) return 'No items found.';

    $output_lines = [];

    foreach ($items as $item) {
        $productID = $item['productID'] ?? null;
        $sizeID = $item['sizeID'] ?? null;
        $qty = $item['quantity'] ?? 1;
        $unitPrice = $item['unitPrice'] ?? 0;

        $productName = "Unknown Product";
        $sizeName = "";

        // Get Product Name
        if ($productID) {
            $stmt_prod = $conn->prepare("SELECT productName FROM products WHERE productID = ?");
            $stmt_prod->bind_param("i", $productID);
            $stmt_prod->execute();
            $result_prod = $stmt_prod->get_result();
            if ($row_prod = $result_prod->fetch_assoc()) {
                $productName = $row_prod['productName'];
            }
            $stmt_prod->close();
        }

        // Get Size Name
        if ($sizeID) {
            $stmt_size = $conn->prepare("SELECT sizeName FROM sizes WHERE sizeID = ?");
            $stmt_size->bind_param("i", $sizeID);
            $stmt_size->execute();
            $result_size = $stmt_size->get_result();
            if ($row_size = $result_size->fetch_assoc()) {
                $sizeName = " ({$row_size['sizeName']})";
            }
            $stmt_size->close();
        }

        // Format the final line
        $output_lines[] = "{$qty}x {$productName}{$sizeName} @ P" . number_format($unitPrice, 2);
    }

    // Join all lines with HTML break for proper display in table
    return implode("<br>", $output_lines);
}


try {
    // --- 1. Corrected SQL Query with 'items' included ---
    $query = "SELECT
                orderID,
                referenceNumber,
                paymentMethod,
                totalAmount,
                status,
                createdAt AS created_at,
                orderSummary    -- <<< Included the items field
            FROM
                orders
            ORDER BY
                createdAt DESC";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception('SQL Prepare Failed: ' . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('SQL Execution Failed: ' . $stmt->error);
    }

    // --- 2. Process Data and Format Items ---
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'orderID' => $row['orderID'],

            // Format the raw JSON into readable text
            'items' => formatOrderItems($row['orderSummary'], $conn),

            'totalAmount' => floatval($row['totalAmount'] ?: 0),
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'referenceNumber' => $row['referenceNumber'] ?? '',
            'paymentMethod' => $row['paymentMethod'] ?? '',
            'orderSummaryRaw' => $row['orderSummary']
        ];
    }

    $stmt->close();

    // --- 3. Encode and Output Final JSON with Case-Insensitive Filtering ---
    echo json_encode([
        'status' => 'success',
        // Use strtolower() to handle case variations like 'Completed' vs 'completed'
        'pending' => array_filter($orders, fn($o) => strtolower($o['status']) === 'pending'),
        'completed' => array_filter($orders, fn($o) => strtolower($o['status']) === 'completed')
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}

// Close the connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
