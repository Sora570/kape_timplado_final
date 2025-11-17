<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$sizeID = intval($_POST['sizeID'] ?? 0);
$sizeName = trim($_POST['sizeName'] ?? '');
$price = floatval($_POST['price'] ?? 0);

if (!$sizeID || !$sizeName || $price <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Size ID, name, and valid price are required']);
    exit;
}

$stmt = $conn->prepare("UPDATE sizes SET sizeName = ?, defaultPrice = ? WHERE sizeID = ?");
$stmt->bind_param("sdi", $sizeName, $price, $sizeID);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Size updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update size']);
}

$stmt->close();
$conn->close();
?>