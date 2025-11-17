<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$stmt = $conn->prepare("SELECT unit_id, unit_name, unit_symbol FROM product_units ORDER BY unit_name");
$stmt->execute();
$result = $stmt->get_result();

$units = [];
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}

echo json_encode($units);
?>
