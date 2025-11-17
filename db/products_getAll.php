<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

try {
    $includeInactive = !empty($_GET['includeInactive']);
    $includeUnits = !empty($_GET['includeUnits']);
    $format = isset($_GET['format']) ? $_GET['format'] : 'payload';

    $sql = "SELECT
                p.productID,
                p.productName,
                p.categoryID,
                c.categoryName,
                p.isActive,
                p.createdAt AS created_at
            FROM products p
            JOIN categories c ON p.categoryID = c.categoryID";

    if (!$includeInactive) {
        $sql .= " WHERE p.isActive = 1";
    }

    $sql .= " ORDER BY p.categoryID, p.productName";

    $result = $conn->query($sql);
    $products = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productID = (int) $row['productID'];

            $sizesSql = "
                SELECT s.sizeID, s.sizeName, COALESCE(pp.price, s.defaultPrice) AS price
                FROM sizes s
                LEFT JOIN product_prices pp ON pp.productID = ? AND pp.sizeID = s.sizeID
                ORDER BY s.sizeID
            ";

            $stmt = $conn->prepare($sizesSql);
            $stmt->bind_param('i', $productID);
            $stmt->execute();
            $sizesResult = $stmt->get_result();

            $normalizedSizes = [];
            while ($sizeRow = $sizesResult->fetch_assoc()) {
                if (!$sizeRow['sizeID'] || !$sizeRow['sizeName']) {
                    continue;
                }

                $normalizedSizes[] = [
                    'id' => (int) $sizeRow['sizeID'],
                    'name' => trim($sizeRow['sizeName']),
                    'price' => isset($sizeRow['price']) ? (float) $sizeRow['price'] : 0.0
                ];
            }
            $stmt->close();

            $product = [
                'id' => $productID,
                'name' => $row['productName'],
                'category' => [
                    'id' => (int) $row['categoryID'],
                    'name' => $row['categoryName']
                ],
                'image' => '',
                'isActive' => (bool) $row['isActive'],
                'createdAt' => $row['created_at'],
                'sizes' => $normalizedSizes
            ];

            if ($includeUnits) {
                $unitSql = "SELECT unit_id, unit_name, unit_symbol FROM product_units ORDER BY unit_name";
                $unitRes = $conn->query($unitSql);
                $units = [];
                if ($unitRes) {
                    while ($unitRow = $unitRes->fetch_assoc()) {
                        $units[] = [
                            'id' => (int) $unitRow['unit_id'],
                            'name' => $unitRow['unit_name'],
                            'symbol' => $unitRow['unit_symbol']
                        ];
                    }
                }
                $product['units'] = $units;
            }

            $products[] = $product;
        }
    }

    $payload = [
        'status' => 'success',
        'count' => count($products),
        'products' => $products
    ];

    if ($format === 'payload') {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    } else {
        echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
