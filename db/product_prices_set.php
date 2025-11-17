<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$pid = intval($_POST['productID'] ?? 0);
if (!$pid) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

try {
    // Prepare statement for upsert (insert or update)
    $stmt = $conn->prepare("INSERT INTO product_prices (productID, sizeID, unit_id, price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price)");

    // Loop through all posted price keys
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'price_') === 0 && $value !== "") {
            // Extract sizeID and unit_id from key like "price_1_2"
            $parts = explode('_', $key);
            if (count($parts) === 3) {
                $sizeID = intval($parts[1]);
                $unit_id = intval($parts[2]);
                $price = floatval($value);

                if ($sizeID > 0 && $unit_id > 0 && $price >= 0) {
                    $stmt->bind_param("iiid", $pid, $sizeID, $unit_id, $price);
                    $stmt->execute();
                }
            }
        }
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
