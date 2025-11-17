<?php
// db/inventory_summary.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$sql = "SELECT 
            COUNT(*) as totalItems,
            SUM(CASE WHEN i.currentStock = 0 THEN 1 ELSE 0 END) as outOfStockItems,
            SUM(CASE WHEN i.currentStock > 0 AND i.currentStock <= i.minStock THEN 1 ELSE 0 END) as lowStockItems,
            SUM(CASE WHEN i.currentStock > i.minStock THEN 1 ELSE 0 END) as inStockItems,
            SUM(i.currentStock) as totalStockValue
        FROM inventory i
        JOIN products p ON i.productID = p.productID
        WHERE p.isActive = 1";

$result = $conn->query($sql);
$summary = $result->fetch_assoc();

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>
