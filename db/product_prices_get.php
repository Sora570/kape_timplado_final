<?php
// db/product_prices_get.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$pid = intval($_GET['productID'] ?? 0);
$sid = intval($_GET['sizeID'] ?? 0);
$uid = intval($_GET['unit_id'] ?? 0);

if (!$pid || !$sid || !$uid) {
    echo json_encode(['price' => null]);
    exit;
}

$stmt = $conn->prepare("
    SELECT price
    FROM product_prices
    WHERE productID = ? AND sizeID = ? AND unit_id = ?
");
$stmt->bind_param('iii', $pid, $sid, $uid);
$stmt->execute();
$res = $stmt->get_result();

$price = null;
if ($r = $res->fetch_assoc()) {
    $price = floatval($r['price']);
}

echo json_encode(['price' => $price], JSON_NUMERIC_CHECK);
