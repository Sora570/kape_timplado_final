<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$productID   = intval($_POST['productID'] ?? 0);
$productName = $_POST['productName'] ?? '';
$categoryID  = intval($_POST['categoryID'] ?? 0);
$isActive    = isset($_POST['isActive']) ? 1 : 0;

if (!$productID || !$productName || !$categoryID) {
    echo json_encode(['status'=>'error','message'=>'Missing fields']);
    exit;
}

// Update product info
$stmt = $conn->prepare("UPDATE products SET productName=?, categoryID=?, isActive=? WHERE productID=?");
$stmt->bind_param("siii", $productName, $categoryID, $isActive, $productID);
if (!$stmt->execute()) {
    echo json_encode(['status'=>'error','message'=>$stmt->error]);
    exit;
}

// Insert or update prices with REPLACE
foreach ($_POST as $key => $value) {
    if (strpos($key, 'size_') === 0 && is_numeric($value)) {
        $sizeID = intval(str_replace('size_', '', $key));
        $price  = floatval($value);

        $ps = $conn->prepare("REPLACE INTO product_prices (productID, sizeID, price) VALUES (?,?,?)");
        $ps->bind_param("iid", $productID, $sizeID, $price);
        $ps->execute();
    }
}

echo json_encode(['status'=>'success']);