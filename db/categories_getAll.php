<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$res = $conn->query("SELECT categoryID, categoryName, isActive FROM categories ORDER BY categoryName");
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode($out);
