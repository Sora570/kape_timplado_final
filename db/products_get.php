<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

$sql = 'SELECT
    p.productID,
    p.productName,
    p.categoryID,
    s.sizeID,
    s.sizeName,
    CASE
        WHEN s.sizeName REGEXP \'^[0-9]+(\\.[0-9]+)?$\' THEN CONCAT(TRIM(s.sizeName), \'oz\')
        ELSE TRIM(s.sizeName)
    END AS sizeLabel,
    COALESCE(pp.price, s.defaultPrice) AS price
FROM products p
LEFT JOIN (
    SELECT productID, sizeID, MAX(price) AS price
    FROM product_prices
    GROUP BY productID, sizeID
) pp ON p.productID = pp.productID
LEFT JOIN sizes s ON pp.sizeID = s.sizeID
WHERE p.isActive = 1
ORDER BY p.productID, s.sizeID';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "SQL prepare failed: " . $conn->error]);
    exit;
}
$stmt->execute();
$res = $stmt->get_result();
 
$products = [];

while ($row = $res->fetch_assoc()) {
    $id = $row['productID'];
    
    if (!isset($products[$id])) {
        $products[$id] = [
            'productID' => $row['productID'],
            'name' => $row['productName'],
            'categoryID' => $row['categoryID'],
            'image_url' => 'assest/image/no-image.png',
            'sizes' => [],
            'sizes_map' => []
        ];
    }

    if ($row['sizeID'] !== null && $row['sizeName'] !== null && $row['price'] !== null) {
        $sizeID = (int)$row['sizeID'];
        $products[$id]['sizes_map'][$sizeID] = [
            'sizeID' => $sizeID,
            'size_label' => $row['sizeLabel'] ?? $row['sizeName'],
            'price' => floatval($row['price'])
        ];
    }
}

foreach ($products as &$product) {
    if (isset($product['sizes_map'])) {
        ksort($product['sizes_map']);
        $product['sizes'] = array_values($product['sizes_map']);
        unset($product['sizes_map']);
    }
}
unset($product);

$arr = array_values($products);

echo json_encode(["products" => $arr, "status" => "ok"]);
$conn->close(); 
?>
