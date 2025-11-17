<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db_connect.php';

try {
    // Temporarily disabled admin check for debugging
    /*
    // Check admin access - for sync, return empty if not admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(array(
            'status' => 'ok',
            'transactions' => [],
            'count' => 0,
            'total_revenue' => 0.0
        ));
        exit;
    }
    */
    // Preload lookup data for products, sizes, and units
    $productNames = [];
    if ($productRes = $conn->query("SELECT productID, productName FROM products")) {
        while ($productRow = $productRes->fetch_assoc()) {
            $productNames[(int)$productRow['productID']] = $productRow['productName'];
        }
        $productRes->close();
    }

    $sizeLabels = [];
    if ($sizeRes = $conn->query("SELECT sizeID, sizeName FROM sizes")) {
        while ($sizeRow = $sizeRes->fetch_assoc()) {
            $sizeLabels[(int)$sizeRow['sizeID']] = $sizeRow['sizeName'];
        }
        $sizeRes->close();
    }

    $unitInfo = [];
    if ($unitRes = $conn->query("SELECT unit_id, unit_name, unit_symbol FROM product_units")) {
        while ($unitRow = $unitRes->fetch_assoc()) {
            $unitInfo[(int)$unitRow['unit_id']] = [
                'name' => $unitRow['unit_name'],
                'symbol' => $unitRow['unit_symbol']
            ];
        }
        $unitRes->close();
    }

    $productUnitMap = [];
    if ($ppuRes = $conn->query("SELECT productID, sizeID, unit_id FROM product_prices ORDER BY unit_id ASC")) {
        while ($ppuRow = $ppuRes->fetch_assoc()) {
            $key = intval($ppuRow['productID']) . '-' . intval($ppuRow['sizeID']);
            $unitId = (int)$ppuRow['unit_id'];
            if (!isset($productUnitMap[$key])) {
                $productUnitMap[$key] = $unitId;
                continue;
            }
            $existingUnitId = $productUnitMap[$key];
            $existingSymbol = $unitInfo[$existingUnitId]['symbol'] ?? '';
            $newSymbol = $unitInfo[$unitId]['symbol'] ?? '';
            if ($existingSymbol === 'pc' && $newSymbol !== 'pc') {
                $productUnitMap[$key] = $unitId;
            }
        }
        $ppuRes->close();
    }

    // FILTER/SORT parameters from query
    $filterType = isset($_GET['type']) ? trim($_GET['type']) : '';
    $filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $limit = intval($_GET['limit'] ?? 50);

    // // Order handling logic 
    $where = [];
    $params = [];
    
    if ($filterType !== '' && strtolower($filterType) !== 'all') {
        $where[] = 'o.status = ?';
        $params[] = $filterType;
    }
    
    // Date range filter optionally applied
    $date_field = 'o.createdAt';
    if ($startDate || $endDate) {
        if ($startDate && $endDate) {
            $where[] = "DATE($date_field) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } else {
            $where_cond = ($startDate) ?
                "DATE($date_field) >= ?" :
                "DATE($date_field) <= ?";
            $where[] = $where_cond;
            $params[] = ($startDate) ?? $endDate;
        }
    } else if (empty($startDate) && empty($endDate) && $filterDate !== '') {
        $where[] = 'DATE(o.createdAt) = ?';
        $params[] = $filterDate;
    }

    $where_clause = implode(' AND ', $where);

    $query_sql = "
        SELECT
            o.orderID,
            o.totalAmount,
            o.paymentMethod AS payment_method,
            o.status,
            o.createdAt as order_date,
            u.employee_id as cashier_id,
            o.referenceNumber,
            o.orderSummary
        FROM orders o
        LEFT JOIN users u ON u.userID = o.userID
        " . ($where_clause ? "WHERE $where_clause" : '') . "
        ORDER BY o.createdAt DESC
        LIMIT ?
    ";
    $params[] = $limit;
    
    $stmt = $conn->prepare($query_sql);
    if ($stmt === false) throw new Exception("Prepare failed: ".$conn->error);
    
    $types = str_repeat('s', count($params)-1) . 'i'; // last param is LIMIT
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result=$stmt->get_result();
    $transactions = [];
    
    while($row = $result->fetch_assoc()){
        $itemLines = [];
        if (!empty($row['orderSummary'])) {
            $summary = json_decode($row['orderSummary'], true);
            if (is_array($summary)) {
                foreach ($summary as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $qty = (int)($entry['quantity'] ?? 1);
                    if ($qty <= 0) {
                        $qty = 1;
                    }
                    $productId = (int)($entry['productID'] ?? 0);
                    $sizeId = isset($entry['sizeID']) ? (int)$entry['sizeID'] : null;

                    $productName = $productNames[$productId] ?? ($productId ? 'Product #' . $productId : 'Unknown Product');

                    $sizeLabel = '';
                    if ($sizeId) {
                        $rawSize = $sizeLabels[$sizeId] ?? '';
                        if ($rawSize !== '') {
                            $sizeLabel = trim($rawSize);
                        } else {
                            $sizeLabel = 'Size ' . $sizeId;
                        }
                    }

                    $unitSymbol = '';
                    if ($sizeId) {
                        $productSizeKey = $productId . '-' . $sizeId;
                        if (isset($productUnitMap[$productSizeKey])) {
                            $unitId = $productUnitMap[$productSizeKey];
                            if (isset($unitInfo[$unitId]['symbol']) && $unitInfo[$unitId]['symbol'] !== '') {
                                $unitSymbol = $unitInfo[$unitId]['symbol'];
                            }
                        }
                    }

                    if (!$unitSymbol && isset($entry['unitSymbol'])) {
                        $unitSymbol = trim((string)$entry['unitSymbol']);
                    } elseif (!$unitSymbol && isset($entry['unit'])) {
                        $unitSymbol = trim((string)$entry['unit']);
                    }

                    if ($unitSymbol === '' && $sizeLabel !== '' && is_numeric($sizeLabel)) {
                        $unitSymbol = 'oz';
                    }

                    $sizeUnit = '';
                    if ($sizeLabel !== '') {
                        $sizeUnit = $sizeLabel;
                        if ($unitSymbol !== '' && stripos($sizeUnit, $unitSymbol) === false) {
                            $sizeUnit = trim($sizeUnit . ' ' . $unitSymbol);
                        }
                    } elseif ($unitSymbol !== '') {
                        $sizeUnit = strtoupper($unitSymbol);
                    }

                    $line = $qty . 'x ' . $productName;
                    if ($sizeUnit !== '') {
                        $line .= ' (' . $sizeUnit . ')';
                    }

                    if (!empty($entry['addons']) && is_array($entry['addons'])) {
                        $addonNames = [];
                        foreach ($entry['addons'] as $addon) {
                            if (is_array($addon)) {
                                $addonName = $addon['name'] ?? ($addon['addonName'] ?? '');
                                if ($addonName !== '') {
                                    $addonNames[] = $addonName;
                                }
                            } elseif (is_string($addon) && $addon !== '') {
                                $addonNames[] = $addon;
                            }
                        }
                        if (!empty($addonNames)) {
                            $line .= ' [+ ' . implode(', ', $addonNames) . ']';
                        }
                    }

                    $itemLines[] = $line;
                }
            }
        }

        if (empty($itemLines)) {
            $itemLines[] = 'No items';
        }

        $transactions[] = array(
            'orderID'       => (int)($row['orderID'] ?? 0),
            'totalAmount'   => floatval($row['totalAmount'] ?? 0),
            'order_date'    => substr($row['order_date'] ?? '',0,19),
            'status'        => $row['status'] ?? '',
            'payment_method' => $row['payment_method'] ?? '',
            'cashier_id'    => $row['cashier_id'] ?? 'manual',
            'referenceNumber' => $row['referenceNumber'] ?? '',
            'items'         => implode("\n", $itemLines),
            'items_list'    => $itemLines
        );
    }
    $stmt->close();

    // Summary data
    $summary_q = "
        SELECT
         COUNT(o.orderID) as transaction_count,
         COALESCE(SUM(o.totalAmount),0) as total_revenue
         FROM orders o
         " . ($where_clause ? "WHERE $where_clause" : '') . "
    ";
    $summary_stmt = $conn->prepare($summary_q);
    if ($summary_stmt === false) throw new Exception("Summary prepare failed: ".$conn->error);
    $summary_params = array_slice($params, 0, -1); // Remove LIMIT param
    if (!empty($summary_params)) {
        $param_types = str_repeat('s', count($summary_params));
        $summary_stmt->bind_param($param_types, ...$summary_params);
    }
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_array();
    $summary_stmt->close();
    
    echo json_encode(array(
        'status' => 'ok',
        'transactions' => $transactions,
        'count' => intval($summary[0] ?? 0),
        'total_revenue' => floatval($summary[1] ?? 0)
    ));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}  ?>
