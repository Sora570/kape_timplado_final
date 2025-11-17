<?php
// Prevent caching to ensure fresh data
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../lib/RecipeHelper.php';

$recipeHelper = new RecipeHelper($conn);

if (!function_exists('normalize_size_label')) {
    function normalize_size_label($label) {
        $value = strtolower(trim((string)$label));
        if ($value === '') {
            return '';
        }
        if (strpos($value, 'oz') === false) {
            $value .= 'oz';
        }
        return $value;
    }
}

// Manual cost overrides are no longer used - production costs are calculated from recipes
$manualCostMap = [];

// Load inventory cost info - fetch fresh data each time to ensure we have latest prices
$inventoryRows = [];
// Use a fresh query to ensure we get the latest Cost_Price values
$invResult = $conn->query("SELECT inventoryID, InventoryName, `Size`, `Unit`, `Cost_Price` FROM inventory");
if ($invResult) {
    while ($row = $invResult->fetch_assoc()) {
        $inventoryRows[(int)$row['inventoryID']] = $row;
    }
    $invResult->free();
}

if (empty($inventoryRows)) {
    echo json_encode([]);
    exit;
}

// Get production costs from database (user-editable)
// Use the same calculation as production_cost_get.php to ensure consistency
$productionCosts = [];
require_once __DIR__ . '/inventory_usage_helpers.php';
$allOverrides = load_production_cost_overrides_from_db($conn);

// Load recipes from database
$recipes = [];
$recipeResult = $conn->query("
    SELECT productID, inventoryID, amount, unit, display_order 
    FROM recipes 
    ORDER BY productID, display_order
");
if ($recipeResult) {
    while ($row = $recipeResult->fetch_assoc()) {
        $productID = (int)$row['productID'];
        if (!isset($recipes[$productID])) {
            $recipes[$productID] = [];
        }
        $recipes[$productID][] = [
            'inventoryID' => (int)$row['inventoryID'],
            'amount' => (float)$row['amount'],
            'unit' => $row['unit']
        ];
    }
    $recipeResult->free();
}

// Refresh inventory data right before calculation to ensure we have the latest prices
// This is critical to ensure costs are calculated with the most recent inventory prices
$invResult = $conn->query("SELECT inventoryID, InventoryName, `Size`, `Unit`, `Cost_Price` FROM inventory");
if ($invResult) {
    $inventoryRows = [];
    while ($row = $invResult->fetch_assoc()) {
        $inventoryRows[(int)$row['inventoryID']] = $row;
    }
    $invResult->free();
}

// Calculate production cost for ALL products with recipes (not just those with overrides)
// This ensures products without overrides still show up in the costing table
foreach ($recipes as $productID => $recipeIngredients) {
    $productID = (int)$productID;
    if ($productID <= 0) continue;
    
    // Get overrides for this product (if any)
    $overrides = $allOverrides[$productID] ?? [];
    $totalCost = 0.0;
    $iceCost = 0.0; // Track ice cost separately
    
    // Process base recipe ingredients - same logic as production_cost_get.php
    foreach ($recipeIngredients as $ingredient) {
        $inventoryID = (int)($ingredient['inventoryID'] ?? 0);
        $amount = (float)($ingredient['amount'] ?? 0);
        
        if (!$inventoryID || !isset($inventoryRows[$inventoryID])) continue;
        
        // Check if removed
        $isRemoved = false;
        foreach ($overrides as $ov) {
            if ((int)($ov['inventoryID'] ?? 0) === $inventoryID && isset($ov['removed']) && $ov['removed']) {
                $isRemoved = true;
                break;
            }
        }
        if ($isRemoved) continue;
        
        $inv = $inventoryRows[$inventoryID];
        $invName = $inv['InventoryName'] ?? '';
        $isWildcard = stripos($invName, 'ice') !== false;
        
        // Find override
        $override = null;
        foreach ($overrides as $ov) {
            if ((int)($ov['inventoryID'] ?? 0) === $inventoryID && !isset($ov['removed'])) {
                $override = $ov;
                break;
            }
        }
        
        if ($isWildcard) {
            // Wildcard (Ice) - use fixed cost from override (same logic as production_cost_get.php)
            $ingredientCost = $override ? (float)($override['ingredientCost'] ?? 0) : 2.00;
            $iceCost += $ingredientCost; // Track ice separately
            $totalCost += $ingredientCost;
        } else {
            // Always recalculate from current inventory prices (same as production_cost_get.php)
            $packSize = $inv['Size'] ?? '';
            $packPrice = (float)($inv['Cost_Price'] ?? 0);
            $sizeValue = floatval(preg_replace('/[^0-9.]/', '', $packSize)) ?: 1;
            $pricePerUnit = $sizeValue > 0 ? ($packPrice / $sizeValue) : 0;
            $neededPerCup = $override ? (float)($override['neededPerCup'] ?? $amount) : $amount;
            $ingredientCost = $pricePerUnit * $neededPerCup;
            $totalCost += $ingredientCost;
        }
    }
    
    // Add manually added ingredients (from overrides) - same logic as production_cost_get.php
    foreach ($overrides as $ov) {
        if (isset($ov['removed']) && $ov['removed']) continue;
        $inventoryID = (int)($ov['inventoryID'] ?? 0);
        if (!$inventoryID || !isset($inventoryRows[$inventoryID])) continue;
        
        // Check if already processed in base recipe
        $found = false;
        foreach ($recipeIngredients as $ingredient) {
            if ((int)($ingredient['inventoryID'] ?? 0) === $inventoryID) {
                $found = true;
                break;
            }
        }
                
        // If not found in base recipe, it's a manually added ingredient
        if (!$found) {
            $inv = $inventoryRows[$inventoryID];
            $invName = $inv['InventoryName'] ?? '';
            $isWildcard = stripos($invName, 'ice') !== false;
            
            if ($isWildcard) {
                // Wildcard (Ice) - use fixed cost from override (same logic as production_cost_get.php)
                $ingredientCost = isset($ov['ingredientCost']) ? (float)$ov['ingredientCost'] : 2.00;
                $iceCost += $ingredientCost; // Track ice separately
                $totalCost += $ingredientCost;
            } else {
                // Always recalculate from current inventory prices (same as production_cost_get.php)
                $packSize = $inv['Size'] ?? '';
                $packPrice = (float)($inv['Cost_Price'] ?? 0);
                $sizeValue = floatval(preg_replace('/[^0-9.]/', '', $packSize)) ?: 1;
                $pricePerUnit = $sizeValue > 0 ? ($packPrice / $sizeValue) : 0;
                $neededPerCup = (float)($ov['neededPerCup'] ?? 0);
                $ingredientCost = $pricePerUnit * $neededPerCup;
                $totalCost += $ingredientCost;
            }
        }
    }
    
    // Round to 2 decimal places, same as production_cost_get.php
    // Store both total cost and ice cost separately for multiplier calculation
    $productionCosts[$productID] = [
        'total' => round($totalCost, 2),
        'ice' => round($iceCost, 2)
    ];
}

// Fall back to base recipe costs for products without production cost data
$baseCosts = $recipeHelper->calculateBaseProductCosts($inventoryRows);
$sizeMultipliers = $recipeHelper->getSizeMultipliers();

// Product metadata
$productMap = [];
$categoryMap = [];
$productResult = $conn->query("SELECT p.productID, p.productName, p.categoryID, c.categoryName FROM products p LEFT JOIN categories c ON c.categoryID = p.categoryID");
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $productMap[(int)$row['productID']] = $row['productName'] ?? ('Product #' . $row['productID']);
        if ($row['categoryID']) {
            $categoryMap[(int)$row['productID']] = $row['categoryName'] ?? 'Uncategorized';
        }
    }
    $productResult->free();
}

$sizeMap = [];
$sizeResult = $conn->query("SELECT sizeID, sizeName FROM sizes");
if ($sizeResult) {
    while ($row = $sizeResult->fetch_assoc()) {
        $sizeMap[(int)$row['sizeID']] = $row['sizeName'];
    }
    $sizeResult->free();
}

$output = [];
// Get prices - prefer unit_id = 2 (oz) but fall back to highest price if oz not available
// This ensures we get the correct price even if it was saved with a different unit_id
try {
    // First, try to get prices with unit_id = 2 (oz)
    $priceStmt = $conn->query("
        SELECT 
            productID, 
            sizeID, 
            price,
            unit_id
        FROM product_prices 
        WHERE unit_id = 2 AND price > 0
    ");
    
    if (!$priceStmt) {
        throw new Exception('Failed to query product prices: ' . $conn->error);
    }
    
    $priceMap = [];
    // First pass: get all prices with unit_id = 2
    while ($row = $priceStmt->fetch_assoc()) {
        $productID = (int)$row['productID'];
        $sizeID = (int)$row['sizeID'];
        $price = (float)$row['price'];
        $key = "{$productID}_{$sizeID}";
        $priceMap[$key] = [
            'productID' => $productID,
            'sizeID' => $sizeID,
            'price' => $price,
            'unit_id' => 2
        ];
    }
    $priceStmt->free();
    
    // Second pass: get prices with other unit_ids for products/sizes not already found
    $priceStmt2 = $conn->query("
        SELECT 
            productID, 
            sizeID, 
            price,
            unit_id
        FROM product_prices 
        WHERE price > 0
        ORDER BY productID, sizeID, price DESC
    ");
    
    if ($priceStmt2) {
        while ($row = $priceStmt2->fetch_assoc()) {
            $productID = (int)$row['productID'];
            $sizeID = (int)$row['sizeID'];
            $price = (float)$row['price'];
            $unit_id = (int)$row['unit_id'];
            $key = "{$productID}_{$sizeID}";
            
            // Only add if we don't already have a price for this product/size (from unit_id = 2)
            if (!isset($priceMap[$key])) {
                $priceMap[$key] = [
                    'productID' => $productID,
                    'sizeID' => $sizeID,
                    'price' => $price,
                    'unit_id' => $unit_id
                ];
            }
        }
        $priceStmt2->free();
    }
    
    // Process the price map
    foreach ($priceMap as $entry) {
        $productID = $entry['productID'];
        $sizeID = $entry['sizeID'];
        $menuPrice = $entry['price'];

        $sizeLabel = $sizeMap[$sizeID] ?? ('Size #' . $sizeID);
        $manualKey = strtolower($productMap[$productID] ?? ('Product #' . $productID)) . '|' . normalize_size_label($sizeLabel);
        $manual = $manualCostMap[$manualKey] ?? null;

        $hasBaseCost = isset($baseCosts[$productID]);
        if (!$hasBaseCost && !$manual) {
            if ($menuPrice <= 0) {
                continue;
            }
        } elseif ($menuPrice <= 0 && (!isset($manual['SellingPrice']) || $manual['SellingPrice'] <= 0)) {
            continue;
        }

        if ($menuPrice <= 0 && isset($manual['SellingPrice'])) {
            $menuPrice = (float)$manual['SellingPrice'];
        }

        // Ensure sizeID is an integer for proper array lookup
        $sizeID = (int)$sizeID;
        $multiplier = isset($sizeMultipliers[$sizeID]) ? (float)$sizeMultipliers[$sizeID] : 1.0;
        $cost = 0.0;
        $profit = 0.0;
        $margin = 0.0;

        // Priority: Production cost > Manual cost > Base cost
        // Use production cost if available (this is the most accurate)
        if (isset($productionCosts[$productID])) {
            // Handle both old format (single value) and new format (array with total and ice)
            if (is_array($productionCosts[$productID])) {
                $baseCost = $productionCosts[$productID]['total'];
                $iceCost = $productionCosts[$productID]['ice'];
                $nonIceCost = $baseCost - $iceCost;
                // Apply multiplier to non-ice cost, then add ice cost separately (ice cost doesn't scale with size)
                $cost = round(($nonIceCost * $multiplier) + $iceCost, 2);
            } else {
                // Backward compatibility: if it's a single value, use old calculation
                $cost = round($productionCosts[$productID] * $multiplier, 2);
            }
            $profit = round($menuPrice - $cost, 2);
            $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
        } else if ($manual && isset($manual['TotalCost'])) {
            // Use manual cost only if production cost is not available
            // Only use manual SellingPrice if database price is missing or invalid
            if (isset($manual['SellingPrice']) && $menuPrice <= 0) {
                $menuPrice = (float)$manual['SellingPrice'];
            }
            // Apply size multiplier to manual cost (manual costs are typically for base 16oz size)
            // If manual cost is for a different size, it should be adjusted accordingly
            $cost = round((float)$manual['TotalCost'] * $multiplier, 2);
            $profit = round($menuPrice - $cost, 2);
            if (isset($manual['Profit'])) {
                $profit = round((float)$manual['Profit'], 2);
            }
            if (isset($manual['Margin'])) {
                $margin = round((float)$manual['Margin'], 2);
            } else {
                $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
            }
        } else if ($hasBaseCost) {
            // Fall back to base cost calculation
            $cost = round($baseCosts[$productID] * $multiplier, 2);
            $profit = round($menuPrice - $cost, 2);
            $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
        } else {
            // No cost calculation available - still show product but with cost = 0
            // This allows products without recipes to still appear in the table
            $profit = round($menuPrice - $cost, 2);
            $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
        }

        // Apply manual overrides for menu price, profit, and margin if manual exists
        // But don't override cost if production cost was used
        // Only use manual SellingPrice if database price is missing or invalid
        if ($manual) {
            if (isset($manual['SellingPrice']) && $menuPrice <= 0) {
                $menuPrice = (float)$manual['SellingPrice'];
                // Recalculate profit and margin if menu price changed
                if (isset($productionCosts[$productID])) {
                    // Recalculate based on production cost (cost already calculated above with multiplier logic)
                    $profit = round($menuPrice - $cost, 2);
                    $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
                }
            }
            // Only override profit/margin if not using production cost
            if (!isset($productionCosts[$productID])) {
                if (isset($manual['Profit'])) {
                    $profit = round((float)$manual['Profit'], 2);
                }
                if (isset($manual['Margin'])) {
                    $margin = round((float)$manual['Margin'], 2);
                } else if ($menuPrice > 0) {
                    $margin = round(($profit / $menuPrice) * 100, 2);
                }
            }
        }

        $output[] = [
            'Category' => $categoryMap[$productID] ?? 'Uncategorized',
            'Product' => $productMap[$productID] ?? ('Product #' . $productID),
            'Size' => $sizeLabel,
            'Menu Price' => $menuPrice,
            'Cost' => $cost,
            'Profit' => $profit,
            'Margin' => $margin
        ];
    }
} catch (Exception $e) {
    // If there's an error, fall back to the original query with unit_id = 2
    error_log('Error in inventory_costing.php price query: ' . $e->getMessage());
    $priceStmt = $conn->query("SELECT productID, sizeID, price FROM product_prices WHERE unit_id = 2 AND price >= 0");
    if ($priceStmt) {
        while ($row = $priceStmt->fetch_assoc()) {
            $productID = (int)$row['productID'];
            $sizeID = (int)$row['sizeID'];
            $menuPrice = (float)$row['price'];
            
            $sizeLabel = $sizeMap[$sizeID] ?? ('Size #' . $sizeID);
            $manualKey = strtolower($productMap[$productID] ?? ('Product #' . $productID)) . '|' . normalize_size_label($sizeLabel);
            $manual = $manualCostMap[$manualKey] ?? null;

            $hasBaseCost = isset($baseCosts[$productID]);
            if (!$hasBaseCost && !$manual) {
                if ($menuPrice <= 0) {
                    continue;
                }
            } elseif ($menuPrice <= 0 && (!isset($manual['SellingPrice']) || $manual['SellingPrice'] <= 0)) {
                continue;
            }

            if ($menuPrice <= 0 && isset($manual['SellingPrice'])) {
                $menuPrice = (float)$manual['SellingPrice'];
            }

            // Ensure sizeID is an integer for proper array lookup
            $sizeID = (int)$sizeID;
            $multiplier = isset($sizeMultipliers[$sizeID]) ? (float)$sizeMultipliers[$sizeID] : 1.0;
            $cost = 0.0;
            $profit = 0.0;
            $margin = 0.0;

            // Priority: Production cost > Manual cost > Base cost
            if (isset($productionCosts[$productID])) {
                if (is_array($productionCosts[$productID])) {
                    $baseCost = $productionCosts[$productID]['total'];
                    $iceCost = $productionCosts[$productID]['ice'];
                    $nonIceCost = $baseCost - $iceCost;
                    $cost = round(($nonIceCost * $multiplier) + $iceCost, 2);
                } else {
                    $cost = round($productionCosts[$productID] * $multiplier, 2);
                }
                $profit = round($menuPrice - $cost, 2);
                $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
            } else if ($manual && isset($manual['TotalCost'])) {
                // Only use manual SellingPrice if database price is missing or invalid
                if (isset($manual['SellingPrice']) && $menuPrice <= 0) {
                    $menuPrice = (float)$manual['SellingPrice'];
                }
                // Apply size multiplier to manual cost (manual costs are typically for base 16oz size)
                $cost = round((float)$manual['TotalCost'] * $multiplier, 2);
                $profit = round($menuPrice - $cost, 2);
                if (isset($manual['Profit'])) {
                    $profit = round((float)$manual['Profit'], 2);
                }
                if (isset($manual['Margin'])) {
                    $margin = round((float)$manual['Margin'], 2);
                } else {
                    $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
                }
            } else if ($hasBaseCost) {
                $cost = round($baseCosts[$productID] * $multiplier, 2);
                $profit = round($menuPrice - $cost, 2);
                $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
            }

            if ($manual) {
                // Only use manual SellingPrice if database price is missing or invalid
                if (isset($manual['SellingPrice']) && $menuPrice <= 0) {
                    $menuPrice = (float)$manual['SellingPrice'];
                    if (isset($productionCosts[$productID])) {
                        $profit = round($menuPrice - $cost, 2);
                        $margin = $menuPrice > 0 ? round(($profit / $menuPrice) * 100, 2) : 0;
                    }
                }
                if (!isset($productionCosts[$productID])) {
                    if (isset($manual['Profit'])) {
                        $profit = round((float)$manual['Profit'], 2);
                    }
                    if (isset($manual['Margin'])) {
                        $margin = round((float)$manual['Margin'], 2);
                    } else if ($menuPrice > 0) {
                        $margin = round(($profit / $menuPrice) * 100, 2);
                    }
                }
            }

            $output[] = [
                'Category' => $categoryMap[$productID] ?? 'Uncategorized',
                'Product' => $productMap[$productID] ?? ('Product #' . $productID),
                'Size' => $sizeLabel,
                'Menu Price' => $menuPrice,
                'Cost' => $cost,
                'Profit' => $profit,
                'Margin' => $margin
            ];
        }
        $priceStmt->free();
    }
}

usort($output, function ($a, $b) {
    return strcmp($a['Category'] . $a['Product'], $b['Category'] . $b['Product']);
});

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>
