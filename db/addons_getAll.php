<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$sql = "SELECT addonID, addonName, isActive
        FROM addons
        ORDER BY addonID ASC";

$res = $conn->query($sql);
$out = [];

while ($row = $res->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out);
