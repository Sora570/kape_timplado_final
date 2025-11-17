<?php
// db/product_save.php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

$productID   = $_POST['productID'] ?? '';
$productName = $_POST['productName'] ?? '';
$categoryID  = $_POST['categoryID'] ?? '';
$isActive    = isset($_POST['isActive']) ? 1 : 0;
$sizesJson   = $_POST['sizes'] ?? '[]';
$sizes       = json_decode($sizesJson, true);

if (!$productName || !$categoryID) {
    echo json_encode(['status'=>'error','message'=>'Missing product name or category']);
    exit;
}

$conn->begin_transaction();

try {
    if ($productID) {
        // ---- UPDATE existing product ----
        $stmt = $conn->prepare("UPDATE products SET productName=?, categoryID=?, isActive=? WHERE productID=?");
        $stmt->bind_param('siii', $productName, $categoryID, $isActive, $productID);
        $stmt->execute();

        // Remove old prices
        $del = $conn->prepare("DELETE FROM product_prices WHERE productID=?");
        $del->bind_param('i', $productID);
        $del->execute();
    } else {
        // ---- INSERT new product ----
        $stmt = $conn->prepare("INSERT INTO products (productName, categoryID, isActive) VALUES (?,?,?)");
        $stmt->bind_param('sii', $productName, $categoryID, $isActive);
        $stmt->execute();
        $productID = $conn->insert_id;
    }

    // ---- Insert new prices ----
    if (is_array($sizes) && count($sizes) > 0) {
        // If sizes were provided by frontend
        $pstmt = $conn->prepare("INSERT INTO product_prices (productID, sizeID, price) VALUES (?,?,?)");
        foreach ($sizes as $sp) {
            $sid = intval($sp['sizeID']);
            $price = floatval($sp['price']);
            $pstmt->bind_param('iid', $productID, $sid, $price);
            $pstmt->execute();
        }
    } else {
        // If no sizes came from frontend, insert default rows for all active sizes
        $res = $conn->query("SELECT sizeID FROM sizes WHERE isActive=1");
        $pstmt = $conn->prepare("INSERT INTO product_prices (productID, sizeID, price) VALUES (?,?,?)");
        while ($row = $res->fetch_assoc()) {
            $sid = intval($row['sizeID']);
            $defaultPrice = 0.00;
            $pstmt->bind_param('iid', $productID, $sid, $defaultPrice);
            $pstmt->execute();
        }
    }

    $conn->commit();
    echo json_encode(['status'=>'success','productID'=>$productID]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}