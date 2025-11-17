<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../lib/RecipeHelper.php';
require_once __DIR__ . '/inventory_usage_helpers.php';

$recipeHelper = new RecipeHelper($conn);

try {
    if (!isset($_SESSION)) {
        session_start();
    }
    if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access. Admin role required.');
    }

    $statusClause = "(o.status IS NULL OR LOWER(o.status) IN ('completed','delivered','paid'))";

    $currentMonth = new DateTimeImmutable('first day of this month 00:00:00');
    $monthBuckets = [];

    for ($i = 11; $i >= 0; $i--) {
        $month = $currentMonth->modify("-{$i} months");
        $key = $month->format('Y-m');
        $monthBuckets[$key] = [
            'key' => $key,
            'label' => $month->format('M Y'),
            'revenue' => 0.0,
            'ingredient_cost' => 0.0,
            'other_expenses' => 0.0,
            'profit' => 0.0
        ];
    }

    $currentWeekStart = new DateTimeImmutable('monday this week');
    $weekBuckets = [];

    for ($i = 3; $i >= 0; $i--) {
        $start = $currentWeekStart->modify("-{$i} weeks");
        $end = $start->modify('sunday this week 23:59:59');
        $key = $start->format('Y-m-d');
        $weekBuckets[$key] = [
            'key' => $key,
            'label' => $start->format('M d') . ' - ' . $end->format('M d'),
            'start_date' => $start->format('Y-m-d 00:00:00'),
            'end_date' => $end->format('Y-m-d H:i:s'),
            'revenue' => 0.0,
            'ingredient_cost' => 0.0,
            'other_expenses' => 0.0,
            'profit' => 0.0
        ];
    }

    $rangeStart = array_key_first($monthBuckets) . '-01 00:00:00';
    $rangeEnd = $currentMonth->modify('last day of this month 23:59:59')->format('Y-m-d H:i:s');

    $sql = "
        SELECT 
            o.orderID,
            o.totalAmount,
            o.ingredientCost,
            o.orderSummary,
            o.createdAt
        FROM orders o
        WHERE o.createdAt BETWEEN ? AND ?
          AND {$statusClause}
        ORDER BY o.createdAt ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare profit tracker query: ' . $conn->error);
    }
    $stmt->bind_param('ss', $rangeStart, $rangeEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    $usageByMonth = [];
    $usageByWeek = [];

    while ($row = $result->fetch_assoc()) {
        $createdAt = $row['createdAt'] ?? null;
        if (!$createdAt) {
            continue;
        }
        $monthKey = date('Y-m', strtotime($createdAt));
        if (!isset($monthBuckets[$monthKey])) {
            continue;
        }

        $monthBuckets[$monthKey]['revenue'] += floatval($row['totalAmount'] ?? 0);
        $orderRevenue = floatval($row['totalAmount'] ?? 0);

        $ingredientCost = floatval($row['ingredientCost'] ?? 0);
        if ($ingredientCost > 0) {
            // Use saved ingredientCost from database
            $monthBuckets[$monthKey]['ingredient_cost'] += $ingredientCost;
            foreach ($weekBuckets as $weekKey => &$week) {
                if ($createdAt >= $week['start_date'] && $createdAt <= $week['end_date']) {
                    $week['revenue'] += $orderRevenue;
                    $week['ingredient_cost'] += $ingredientCost;
                    break;
                }
            }
            unset($week);
            continue;
        }

        // If ingredientCost is 0 or missing, recalculate with ice overrides (same logic as dashboard_analytics)
        $summaryItems = json_decode($row['orderSummary'] ?? '[]', true);
        if (!is_array($summaryItems) || empty($summaryItems)) {
            continue;
        }

        // Recalculate cost with ice overrides
        $usage = $recipeHelper->aggregateUsage($summaryItems);
        if (empty($usage)) {
            continue;
        }

        $inventoryRows = $recipeHelper->fetchInventoryRows($conn, array_keys($usage), true);
        $mapped = $recipeHelper->mapUsageToInventory($usage, $inventoryRows);
        
        // Calculate base cost from recipes
        $calculatedCost = 0.0;
        if (!empty($mapped)) {
            $calculatedCost = $recipeHelper->calculateCost($mapped);
        }
        
        // Load production cost overrides to apply ice costs
        $allOverrides = load_production_cost_overrides_from_db($conn);
        $ICE_INVENTORY_ID = 59;
        
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
        foreach ($summaryItems as $item) {
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
        
        // Apply adjustment: use override ice cost instead of calculated
        $iceCostAdjustment = $totalIceCostFromOverrides - $calculatedIceCost;
        
        // Round to 2 decimal places for accuracy
        $recalculatedCost = round($calculatedCost + $iceCostAdjustment, 2);
        
        // Add the recalculated cost to buckets
        $monthBuckets[$monthKey]['ingredient_cost'] += $recalculatedCost;
        foreach ($weekBuckets as $weekKey => &$week) {
            if ($createdAt >= $week['start_date'] && $createdAt <= $week['end_date']) {
                $week['revenue'] += $orderRevenue;
                $week['ingredient_cost'] += $recalculatedCost;
                break;
            }
        }
        unset($week);
    }

    $stmt->close();

    // Note: Costs are now calculated immediately per order (with ice overrides) 
    // instead of aggregating and calculating later, which ensures accuracy

    $totals = [
        'revenue' => 0.0,
        'ingredient_cost' => 0.0,
        'other_expenses' => 0.0,
        'profit' => 0.0
    ];

    foreach ($monthBuckets as &$bucket) {
        $bucket['revenue'] = round($bucket['revenue'], 2);
        $bucket['ingredient_cost'] = round($bucket['ingredient_cost'], 2);
        $bucket['other_expenses'] = round($bucket['other_expenses'], 2);
        $bucket['profit'] = round(
            $bucket['revenue'] - $bucket['ingredient_cost'] - $bucket['other_expenses'],
            2
        );

        $totals['revenue'] += $bucket['revenue'];
        $totals['ingredient_cost'] += $bucket['ingredient_cost'];
        $totals['other_expenses'] += $bucket['other_expenses'];
        $totals['profit'] += $bucket['profit'];
    }
    unset($bucket);

    foreach ($weekBuckets as &$week) {
        $week['revenue'] = round($week['revenue'], 2);
        $week['ingredient_cost'] = round($week['ingredient_cost'], 2);
        $week['other_expenses'] = round($week['other_expenses'], 2);
        $week['profit'] = round(
            $week['revenue'] - $week['ingredient_cost'] - $week['other_expenses'],
            2
        );
    }
    unset($week);

    foreach ($totals as $key => $value) {
        $totals[$key] = round($value, 2);
    }

    echo json_encode([
        'months' => array_values($monthBuckets),
        'weeks' => array_values($weekBuckets),
        'totals' => $totals,
        'generated_at' => date(DATE_ATOM)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
