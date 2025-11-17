<?php
// Start output buffering to catch any errors
ob_start();

// Suppress error output to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Check if db_connect.php exists and can be included
if (!file_exists(__DIR__ . '/db_connect.php')) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database configuration file not found']);
    exit;
}

// Include db_connect.php - capture any output it might produce
$dbConnectOutput = '';
ob_start();
require_once __DIR__ . '/db_connect.php';
$dbConnectOutput = ob_get_clean();

// Check if connection was established
if (!isset($conn) || !$conn) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . (!empty($dbConnectOutput) ? $dbConnectOutput : 'Connection variable not set')]);
    exit;
}

if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection error: ' . $conn->connect_error]);
    exit;
}

// Include RecipeHelper
if (!file_exists(__DIR__ . '/../lib/RecipeHelper.php')) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'RecipeHelper file not found']);
    exit;
}
require_once __DIR__ . '/../lib/RecipeHelper.php';

$productID = intval($_GET['productID'] ?? 0);

if (!$productID) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'ProductID is required']);
    exit;
}

try {
    // Clear any output that might have been generated
    ob_clean();
    
    $recipeHelper = new RecipeHelper();
    $recipes = $recipeHelper->getSizeMultipliers(); // Actually we need the recipes
    
    // Get product name first
    $productStmt = $conn->prepare("SELECT productName FROM products WHERE productID = ?");
    $productStmt->bind_param('i', $productID);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    $product = $productResult->fetch_assoc();
    $productName = $product ? $product['productName'] : "Product #$productID";
    
    // Get inventory data
    $inventoryStmt = $conn->query("SELECT inventoryID, InventoryName, Size, Unit, Cost_Price FROM inventory");
    $inventoryMap = [];
    if ($inventoryStmt) {
        while ($row = $inventoryStmt->fetch_assoc()) {
            $inventoryMap[(int)$row['inventoryID']] = $row;
        }
        $inventoryStmt->free();
    }
    
    // Load production cost overrides (for Ice and manual entries) from database
    require_once __DIR__ . '/inventory_usage_helpers.php';
    $overrides = get_production_cost_overrides_for_product($conn, $productID);
    
    // Load recipes from database
    $recipes = [];
    $recipeStmt = $conn->prepare("
        SELECT productID, inventoryID, amount, unit, display_order 
        FROM recipes 
        WHERE productID = ?
        ORDER BY display_order
    ");
    $recipeStmt->bind_param('i', $productID);
    $recipeStmt->execute();
    $recipeResult = $recipeStmt->get_result();
    if ($recipeResult) {
        while ($row = $recipeResult->fetch_assoc()) {
            if (!isset($recipes[$productID])) {
                $recipes[$productID] = [];
            }
            $recipes[$productID][] = [
                'inventoryID' => (int)$row['inventoryID'],
                'amount' => (float)$row['amount'],
                'unit' => $row['unit']
            ];
        }
        $recipeStmt->close();
    }
    $hasBaseRecipe = isset($recipes[$productID]);
    
    // Track which inventory IDs are in the base recipe (if it exists)
    $baseRecipeInventoryIDs = [];
    if ($hasBaseRecipe) {
        foreach ($recipes[$productID] as $ingredient) {
            $baseRecipeInventoryIDs[(int)($ingredient['inventoryID'] ?? 0)] = true;
        }
    }
    
    $result = [];
    $itemID = 1;
    
    // Process base recipe ingredients (if recipe exists)
    if ($hasBaseRecipe) {
        foreach ($recipes[$productID] as $ingredient) {
            $inventoryID = (int)($ingredient['inventoryID'] ?? 0);
            $amount = (float)($ingredient['amount'] ?? 0);
            $unit = $ingredient['unit'] ?? '';
            
            if (!$inventoryID || !isset($inventoryMap[$inventoryID])) {
                continue;
            }
            
            // Check if this ingredient was removed by user
            $isRemoved = false;
            foreach ($overrides as $ov) {
                if ((int)($ov['inventoryID'] ?? 0) === $inventoryID && isset($ov['removed']) && $ov['removed']) {
                    $isRemoved = true;
                    break;
                }
            }
            
            // Skip removed ingredients
            if ($isRemoved) {
                continue;
            }
            
            $inv = $inventoryMap[$inventoryID];
            $invName = $inv['InventoryName'] ?? '';
            
            // Check if this is a wildcard (Ice) or has an override
            $isWildcard = stripos($invName, 'ice') !== false;
            $override = null;
            foreach ($overrides as $ov) {
                if ((int)($ov['inventoryID'] ?? 0) === $inventoryID && !isset($ov['removed'])) {
                    $override = $ov;
                    break;
                }
            }
            
            // All ingredients are now removable - user wants full control without code changes
            // isFromBaseRecipe is set to false for all to allow removal
            
            if ($isWildcard) {
                // Wildcard (Ice) - use fixed ingredient cost, editable
                $ingredientCost = $override ? (float)($override['ingredientCost'] ?? 0) : 2.00;
                $result[] = [
                    'productID' => $productID,
                    'itemID' => $itemID++,
                    'inventoryID' => $inventoryID,
                    'InventoryName' => $invName,
                    'packSize' => '',
                    'unit' => '',
                    'packPrice' => 0,
                    'neededPerCup' => '',
                    'pricePerUnit' => 0,
                    'ingredientCost' => $ingredientCost,
                    'isWildcard' => true,
                    'isFromBaseRecipe' => false  // All ingredients are removable
                ];
            } else {
                // Always recalculate from current inventory prices (don't use fixed ingredientCost)
                // This ensures costs stay up-to-date when inventory prices change
                $packSize = $inv['Size'] ?? '';
                $packPrice = (float)($inv['Cost_Price'] ?? 0);
                $sizeValue = floatval(preg_replace('/[^0-9.]/', '', $packSize)) ?: 1;
                $pricePerUnit = $sizeValue > 0 ? ($packPrice / $sizeValue) : 0;
                $neededPerCup = $override ? (float)($override['neededPerCup'] ?? $amount) : $amount;
                $ingredientCost = $pricePerUnit * $neededPerCup;
                
                $result[] = [
                    'productID' => $productID,
                    'itemID' => $itemID++,
                    'inventoryID' => $inventoryID,
                    'InventoryName' => $invName,
                    'packSize' => $packSize,
                    'unit' => $inv['Unit'] ?? '',
                    'packPrice' => $packPrice,
                    'neededPerCup' => $neededPerCup,
                    'pricePerUnit' => $pricePerUnit,
                    'ingredientCost' => $ingredientCost,
                    'isWildcard' => false,
                    'isFromBaseRecipe' => false  // All ingredients are removable
                ];
            }
        }
    }
    
    // Add overrides that aren't in the recipe (manually added ingredients)
    // Skip removed ingredients
    foreach ($overrides as $ov) {
        // Skip removed markers
        if (isset($ov['removed']) && $ov['removed']) {
            continue;
        }
        
        $inventoryID = (int)($ov['inventoryID'] ?? 0);
        $found = false;
        foreach ($result as $r) {
            if ((int)($r['inventoryID'] ?? 0) === $inventoryID) {
                $found = true;
                break;
            }
        }
        // If not found in result, it means this ingredient is ONLY in overrides (manually added)
        // For products without base recipes, all ingredients come from overrides
        // These should always be removable, regardless of whether they exist in base recipe
        if (!$found && isset($inventoryMap[$inventoryID])) {
            $inv = $inventoryMap[$inventoryID];
            $invName = $inv['InventoryName'] ?? '';
            $isWildcard = stripos($invName, 'ice') !== false;
            // Manually added ingredients (only in overrides) are always removable
            $isFromBaseRecipe = false;
            
            if ($isWildcard) {
                // Wildcard (Ice) - use fixed ingredient cost
                $ingredientCost = (float)($ov['ingredientCost'] ?? 2.00);
                $result[] = [
                    'productID' => $productID,
                    'itemID' => $itemID++,
                    'inventoryID' => $inventoryID,
                    'InventoryName' => $invName,
                    'packSize' => '',
                    'unit' => '',
                    'packPrice' => 0,
                    'neededPerCup' => '',
                    'pricePerUnit' => 0,
                    'ingredientCost' => $ingredientCost,
                    'isWildcard' => true,
                    'isFromBaseRecipe' => $isFromBaseRecipe
                ];
            } else {
                // Always recalculate from current inventory prices (don't use fixed ingredientCost)
                // This ensures costs stay up-to-date when inventory prices change
                $packSize = $inv['Size'] ?? '';
                $packPrice = (float)($inv['Cost_Price'] ?? 0);
                $sizeValue = floatval(preg_replace('/[^0-9.]/', '', $packSize)) ?: 1;
                $pricePerUnit = $sizeValue > 0 ? ($packPrice / $sizeValue) : 0;
                $neededPerCup = (float)($ov['neededPerCup'] ?? 0);
                $ingredientCost = $pricePerUnit * $neededPerCup;
                
                $result[] = [
                    'productID' => $productID,
                    'itemID' => $itemID++,
                    'inventoryID' => $inventoryID,
                    'InventoryName' => $invName,
                    'packSize' => $packSize,
                    'unit' => $inv['Unit'] ?? '',
                    'packPrice' => $packPrice,
                    'neededPerCup' => $neededPerCup,
                    'pricePerUnit' => $pricePerUnit,
                    'ingredientCost' => $ingredientCost,
                    'isWildcard' => false,
                    'isFromBaseRecipe' => $isFromBaseRecipe
                ];
            }
        }
    }
    
    // Calculate total production cost
    $totalCost = 0.0;
    foreach ($result as $item) {
        $totalCost += (float)($item['ingredientCost'] ?? 0);
    }
    
    // Clear output buffer and send JSON
    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'data' => $result,
        'productName' => $productName,
        'totalCost' => round($totalCost, 2)
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
}

