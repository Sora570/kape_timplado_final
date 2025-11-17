<?php
// db/products_getOne.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$pid = intval($_POST['productID'] ?? 0);
if (!$pid) {
    echo json_encode(null);
    exit;
}

$stmt = $conn->prepare("SELECT productID, productName, categoryID, isActive FROM products WHERE productID=? LIMIT 1");
$stmt->bind_param("i", $pid);
$stmt->execute();
$res = $stmt->get_result();
$p = $res->fetch_assoc();

if (!$p) {
    echo json_encode(null);
    exit;
}

// Get sizes with fallback to default price
$ps = $conn->prepare("
    SELECT s.sizeID, s.sizeName, COALESCE(pp.price, s.defaultPrice) AS price
    FROM sizes s
    LEFT JOIN product_prices pp ON s.sizeID = pp.sizeID AND pp.productID=?
    ORDER BY s.sizeID
");
$ps->bind_param("i", $pid);
$ps->execute();
$prs = $ps->get_result();

$sizes = [];
while ($s = $prs->fetch_assoc()) {
    $sizes[] = $s;
}
$p['sizes'] = $sizes;

// Addons placeholder
$p['addons'] = [];

echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
