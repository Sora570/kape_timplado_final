<?php
header('Content-Type: application/json');

// Include database connections 
require_once 'db_connect.php';
require_once __DIR__ . '/../lib/RecipeHelper.php';
require_once __DIR__ . '/inventory_usage_helpers.php';

$recipeHelper = new RecipeHelper($conn);

function get_top_5_products(){
    try{
        global $conn;
        $statusClause = "(o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))";

        // Preload product names for lookup when order summaries only contain IDs
        $productNameMap = [];
        if ($nameResult = $conn->query("SELECT productID, productName FROM products")) {
            while ($nameRow = $nameResult->fetch_assoc()) {
                $productNameMap[(int)$nameRow['productID']] = $nameRow['productName'];
            }
            $nameResult->close();
        }

        $query = "
            SELECT 
                o.orderID,
                o.orderSummary
            FROM orders o
            WHERE DATE(o.createdAt) = CURDATE()
              AND {$statusClause}
        ";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $productStats = [];

        while ($row = $result->fetch_assoc()) {
            $orderId = (int)($row['orderID'] ?? 0);
            $orderSummary = json_decode($row['orderSummary'] ?? '[]', true);

            if (!is_array($orderSummary)) {
                continue;
            }

            foreach ($orderSummary as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $productId = isset($item['productID'])
                    ? (int)$item['productID']
                    : (isset($item['productId'])
                        ? (int)$item['productId']
                        : (isset($item['id']) ? (int)$item['id'] : 0));

                $name = trim((string)($item['name'] ?? $item['productName'] ?? ''));
                if ($name === '' && $productId > 0 && isset($productNameMap[$productId])) {
                    $name = $productNameMap[$productId];
                }
                if ($name === '') {
                    $name = 'Unknown Product';
                }

                $quantity = isset($item['quantity'])
                    ? (int)$item['quantity']
                    : (isset($item['qty']) ? (int)$item['qty'] : 0);

                if ($quantity <= 0) {
                    $quantity = 1;
                }

                $key = $productId > 0 ? 'id_'.$productId : 'name_'.strtolower($name);

                if (!isset($productStats[$key])) {
                    $productStats[$key] = [
                        'name' => $name,
                        'quantity' => 0,
                        'order_ids' => [],
                        'product_id' => $productId
                    ];
                }

                $productStats[$key]['quantity'] += $quantity;
                $productStats[$key]['order_ids'][$orderId] = true;
            }
        }

        $stmt->close();

        $products = array_map(function ($stat) {
            return [
                'name' => $stat['name'],
                'quantity' => (int)$stat['quantity'],
                'count' => count($stat['order_ids'])
            ];
        }, $productStats);

        usort($products, function ($a, $b) {
            if ($a['quantity'] === $b['quantity']) {
                return $b['count'] <=> $a['count'];
            }
            return $b['quantity'] <=> $a['quantity'];
        });

        return array_slice($products, 0, 5);

    } catch(Exception $e){
        error_log("Analytics Error: ".$e->getMessage());
        return [];
    }
}

function get_today_sales_total(){
    try{
        global $conn;

        $query = "
            SELECT 
                COALESCE(SUM(o.totalAmount), 0) AS today_total
            FROM orders o
            WHERE DATE(o.createdAt) = CURDATE()
              AND (o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))
        ";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return round(floatval($data['today_total'] ?? 0), 2);

    } catch(Exception $e){
        error_log("Analytics Error: ".$e->getMessage());
        return 0;
    }
}

function get_today_orders_count(){
    try{
        global $conn;
        $query = "
            SELECT COUNT(*) AS orders_count
            FROM orders o
            WHERE DATE(o.createdAt) = CURDATE()
              AND (o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))
        ";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return intval($data['orders_count'] ?? 0);

    } catch(Exception $e){
        error_log("Analytics Error: ".$e->getMessage());
        return 0;
    }
}

function get_today_expenses_total(){
    try{
        global $conn, $recipeHelper;
        $statusClause = "(o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))";

        $query = "
            SELECT o.orderSummary, o.ingredientCost
            FROM orders o
            WHERE DATE(o.createdAt) = CURDATE()
              AND {$statusClause}
        ";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $total = 0.0;
        $usageAggregate = [];
        $ordersNeedingRecalculation = []; // Store orders that need recalculation with their items

        while ($row = $result->fetch_assoc()) {
            $cost = floatval($row['ingredientCost'] ?? 0);
            if ($cost > 0) {
                $total += $cost;
                continue;
            }

            // If ingredientCost is 0 or missing, we need to recalculate with ice overrides
            $items = json_decode($row['orderSummary'] ?? '[]', true);
            if (is_array($items) && !empty($items)) {
                $ordersNeedingRecalculation[] = $items;
            }
        }

        $stmt->close();

        // Recalculate costs for orders missing ingredientCost, applying ice overrides
        if (!empty($ordersNeedingRecalculation)) {
            // Load production cost overrides
            $allOverrides = load_production_cost_overrides_from_db($conn);
            $ICE_INVENTORY_ID = 59;
            
            // Aggregate usage from all orders
            foreach ($ordersNeedingRecalculation as $items) {
                $usage = $recipeHelper->aggregateUsage($items);
                $recipeHelper->mergeUsage($usageAggregate, $usage);
            }
            
            if (!empty($usageAggregate)) {
                $inventoryRows = $recipeHelper->fetchInventoryRows($conn, array_keys($usageAggregate));
                $mapped = $recipeHelper->mapUsageToInventory($usageAggregate, $inventoryRows);
                
                // Calculate base cost from recipes
                $calculatedCost = 0.0;
                if (!empty($mapped)) {
                    $calculatedCost = $recipeHelper->calculateCost($mapped);
                }
                
                // Calculate what ice cost was included in the base calculation (if any)
                $calculatedIceCost = 0.0;
                if (isset($mapped[$ICE_INVENTORY_ID])) {
                    $calculatedIceCost = $mapped[$ICE_INVENTORY_ID]['fraction'] * $mapped[$ICE_INVENTORY_ID]['cost_price'];
                }
                
                // Load all recipes once to check which products use ice
                $productsWithIce = [];
                $recipeCheckQuery = $conn->query("SELECT DISTINCT productID FROM recipes WHERE inventoryID = $ICE_INVENTORY_ID");
                if ($recipeCheckQuery) {
                    while ($recipeRow = $recipeCheckQuery->fetch_assoc()) {
                        $productsWithIce[(int)$recipeRow['productID']] = true;
                    }
                    $recipeCheckQuery->free();
                }
                
                // Calculate total ice cost from overrides (per product quantity)
                $totalIceCostFromOverrides = 0.0;
                foreach ($ordersNeedingRecalculation as $items) {
                    foreach ($items as $item) {
                        $productID = (int)($item['productID'] ?? 0);
                        $quantity = (int)($item['quantity'] ?? 1);
                        
                        if ($productID <= 0) {
                            continue;
                        }
                        
                        $iceCostPerProduct = 0.0;
                        $productUsesIce = false;
                        
                        // Check if product uses ice (in recipe or override)
                        if (isset($productsWithIce[$productID])) {
                            $productUsesIce = true;
                        }
                        
                        // Check if product has ice override
                        if (isset($allOverrides[$productID])) {
                            $overrides = $allOverrides[$productID];
                            foreach ($overrides as $override) {
                                $overrideInventoryID = (int)($override['inventoryID'] ?? 0);
                                // Check if this is ice (inventoryID 59)
                                if ($overrideInventoryID === $ICE_INVENTORY_ID) {
                                    // If ice is marked as removed, don't use it
                                    if (isset($override['removed']) && $override['removed']) {
                                        $productUsesIce = false;
                                        break;
                                    }
                                    // Product uses ice (override exists)
                                    $productUsesIce = true;
                                    // Use override cost if set and > 0, otherwise default to 2.00
                                    $iceCostPerProduct = (isset($override['ingredientCost']) && $override['ingredientCost'] !== null && (float)$override['ingredientCost'] > 0)
                                        ? (float)$override['ingredientCost'] 
                                        : 2.00;
                                    break; // Found ice override for this product
                                }
                            }
                        }
                        
                        // If product uses ice but no override cost was set, use default 2.00
                        if ($productUsesIce && $iceCostPerProduct == 0.0) {
                            $iceCostPerProduct = 2.00;
                        }
                        
                        // Apply ice cost for this product if it uses ice
                        if ($productUsesIce) {
                            $totalIceCostFromOverrides += $iceCostPerProduct * $quantity;
                        }
                    }
                }
                
                // Apply adjustment: use override ice cost instead of calculated
                $iceCostAdjustment = $totalIceCostFromOverrides - $calculatedIceCost;
                
                // Round to 2 decimal places for accuracy
                $recalculatedCost = round($calculatedCost + $iceCostAdjustment, 2);
                $total += $recalculatedCost;
            }
        }

        return round($total, 2);
    } catch(Exception $e){
        error_log("Analytics Expense Error: ".$e->getMessage());
        return 0.0;
    }
}

function get_daily_sales_chart_data(){
    try{
        global $conn;

        $statusClause = "(o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))";
        $startDate = date('Y-m-d', strtotime('-6 days'));

        $query = "
            SELECT 
                DATE(o.createdAt) AS sale_date,
                DATE_FORMAT(o.createdAt, '%b %d') AS date_label,
                o.totalAmount AS order_total,
                o.orderSummary
            FROM orders o
            WHERE DATE(o.createdAt) >= ?
              AND {$statusClause}
            ORDER BY sale_date ASC, o.createdAt ASC
        ";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: ".$conn->error);
        }

        $stmt->bind_param('s', $startDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $aggregated = [];

        while ($row = $result->fetch_assoc()) {
            $dateKey = $row['sale_date'];
            if (!$dateKey) {
                continue;
            }

            if (!isset($aggregated[$dateKey])) {
                $aggregated[$dateKey] = [
                    'date' => $dateKey,
                    'date_label' => $row['date_label'] ?? date('M d', strtotime($dateKey)),
                    'orders' => 0,
                    'revenue' => 0.0,
                    'daily_items_sold' => 0
                ];
            }

            $aggregated[$dateKey]['orders'] += 1;
            $aggregated[$dateKey]['revenue'] += floatval($row['order_total'] ?? 0);

            $items = json_decode($row['orderSummary'] ?? '[]', true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $qty = isset($item['quantity'])
                        ? (int)$item['quantity']
                        : (isset($item['qty']) ? (int)$item['qty'] : 0);
                    if ($qty <= 0) {
                        $qty = 1;
                    }
                    $aggregated[$dateKey]['daily_items_sold'] += $qty;
                }
            }
        }

        $stmt->close();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            if (isset($aggregated[$date])) {
                $entry = $aggregated[$date];
                $entry['revenue'] = round($entry['revenue'], 2);
                $chartData[] = $entry;
            } else {
                $chartData[] = [
                    'date' => $date,
                    'date_label' => date('M d', strtotime($date)),
                    'orders' => 0,
                    'revenue' => 0.00,
                    'daily_items_sold' => 0
                ];
            }
        }

        return $chartData;

    } catch(Exception $e){
        error_log("Analytics Error: ".$e->getMessage());
        return array_map(function($i) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            return [
                'date' => $date,
                'date_label' => date('M d', strtotime($date)),
                'orders' => 0,
                'revenue' => 0.00,
                'daily_items_sold' => 0
            ];
        }, range(6,0));
    }
}

try {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    // Ensure user is logged in and admin
    if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("Unauthorized access. Admin role required.");
    }
    
    $action = $_POST['action'] ?? $_GET['action'] ?? 'all';
    
    switch($action) {
        case 'top_products':
            echo json_encode(get_top_5_products());
            break;
            
        case 'daily_sales':
            echo json_encode(['daily_sales' => get_today_sales_total()]);
            break;
            
        case 'all':
        default:
            $dailySales = get_today_sales_total();
            $dailyExpenses = get_today_expenses_total();
            echo json_encode([
                'top_products' => get_top_5_products(),
                'daily_sales' => $dailySales,
                'daily_expenses' => $dailyExpenses,
                'daily_profit' => round($dailySales - $dailyExpenses, 2),
                'today_orders' => get_today_orders_count(),
                'chart_data' => get_daily_sales_chart_data()
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
