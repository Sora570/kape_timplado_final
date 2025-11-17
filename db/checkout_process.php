<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../lib/RecipeHelper.php';
require_once __DIR__ . '/inventory_usage_helpers.php';


// Check if user is logged in
if (!isset($_SESSION['userID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$cartItems = json_decode($_POST['cartItems'] ?? '[]', true);
$paymentMethod = $_POST['paymentMethod'] ?? 'cash';
$cashReceived = floatval($_POST['cashReceived'] ?? 0);

if (empty($cartItems)) {
    echo json_encode(['status' => 'error', 'message' => 'No items in cart']);
    exit;
}

$recipeHelper = new RecipeHelper();
$ingredientUsage = $recipeHelper->aggregateUsage($cartItems);
$orderIngredientCost = 0.0;
$totalProfit = 0.0;


$conn->begin_transaction();

try {
    // Calculate totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += ($item['unitPrice'] ?? 0) * ($item['quantity'] ?? 0);
    }

    $totalAmount = $subtotal;

    // Ingredient cost will be calculated from recipes after ingredient usage is mapped (see below)
    $orderIngredientCost = 0.0;

    // Generate reference number
    $referenceNumber = 'ORD' . str_pad($orderID ?? 1, 6, '0', STR_PAD_LEFT); // Will update after insert

    // Create order
    $orderQuery = "INSERT INTO orders (userID, paymentMethod, totalAmount, orderSummary, referenceNumber, status, createdAt)
                   VALUES (?, ?, ?, ?, ?, 'completed', NOW())";

$stmt = $conn->prepare($orderQuery);
$orderSummaryJson = json_encode($cartItems);
$currentUserId = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 1;
$stmt->bind_param('isdss', $currentUserId, $paymentMethod, $totalAmount, $orderSummaryJson, $referenceNumber);
    $stmt->execute();
    $orderID = $conn->insert_id;

    // Update reference number with actual orderID
    $actualRefNumber = 'ORD' . str_pad($orderID, 6, '0', STR_PAD_LEFT);
    $updateRefQuery = "UPDATE orders SET referenceNumber = ? WHERE orderID = ?";
    $updateStmt = $conn->prepare($updateRefQuery);
    $updateStmt->bind_param('si', $actualRefNumber, $orderID);
    $updateStmt->execute();

    // Inventory deduction
    $sizeGroups = [];
    foreach ($cartItems as $item) {
        if (isset($item['sizeID']) && $item['sizeID']) {
            $sizeID = $item['sizeID'];
            if (!isset($sizeGroups[$sizeID])) {
                $sizeGroups[$sizeID] = ['qty' => 0, 'sizeName' => ''];
            }
            $sizeGroups[$sizeID]['qty'] += $item['quantity'];
        }
    }

    foreach ($sizeGroups as $sizeID => $group) {
        // Get size name
        $sizeStmt = $conn->prepare("SELECT sizeName FROM sizes WHERE sizeID = ?");
        $sizeStmt->bind_param('i', $sizeID);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result()->fetch_assoc();
        if ($sizeResult) {
            $sizeName = $sizeResult['sizeName'];
            $cupName = "{$sizeName}oz Cup";
            // Find cup inventory
            $invStmt = $conn->prepare("SELECT inventoryID, `Current_Stock`, `Cost_Price` FROM inventory WHERE `InventoryName` = ? FOR UPDATE");
            $invStmt->bind_param('s', $cupName);
            $invStmt->execute();
            $invResult = $invStmt->get_result()->fetch_assoc();
            if ($invResult) {
                $inventoryID = $invResult['inventoryID'];
                $currentStock = (float)$invResult['Current_Stock'];
                if ($currentStock < $group['qty']) {
                    throw new Exception("Insufficient inventory for Cup {$sizeName}oz: need {$group['qty']}, have {$currentStock}");
                }
                // Deduct stock
                $newStock = $currentStock - $group['qty'];
                $newValue = $newStock * (float)$invResult['Cost_Price'];
                $updateStmt = $conn->prepare("UPDATE inventory SET `Current_Stock` = ?, `Total_Value` = ? WHERE inventoryID = ?");
                $updateStmt->bind_param('ddi', $newStock, $newValue, $inventoryID);
                $updateStmt->execute();
            } else {
                // For new systems: Log missing cup inventory but don't fail checkout
                error_log("Warning: Cup inventory '{$cupName}' not found in database for size ID {$sizeID}");
            }
        }
    }

    // Ingredient deductions and cost calculation
    if (!empty($ingredientUsage)) {
        $inventoryRows = $recipeHelper->fetchInventoryRows($conn, array_keys($ingredientUsage), true);
        $mappedUsage = $recipeHelper->mapUsageToInventory($ingredientUsage, $inventoryRows);

        // Calculate base ingredient cost from recipes
        $calculatedCost = 0.0;
        if (!empty($mappedUsage)) {
            $calculatedCost = $recipeHelper->calculateCost($mappedUsage);
        }
        
        // Load production cost overrides to apply fixed costs (especially for ice)
        $allOverrides = load_production_cost_overrides_from_db($conn);
        
        // Ice inventory ID is known to be 59
        $ICE_INVENTORY_ID = 59;
        
        // Calculate what ice cost was included in the base calculation (if any)
        $calculatedIceCost = 0.0;
        if (isset($mappedUsage[$ICE_INVENTORY_ID])) {
            $calculatedIceCost = $mappedUsage[$ICE_INVENTORY_ID]['fraction'] * $mappedUsage[$ICE_INVENTORY_ID]['cost_price'];
        }
        
        // Load all recipes once to check which products use ice
        $productsWithIce = [];
        $recipeCheckQuery = $conn->query("SELECT DISTINCT productID FROM recipes WHERE inventoryID = $ICE_INVENTORY_ID");
        if ($recipeCheckQuery) {
            while ($row = $recipeCheckQuery->fetch_assoc()) {
                $productsWithIce[(int)$row['productID']] = true;
            }
            $recipeCheckQuery->free();
        }
        
        // Calculate total ice cost from overrides (per product quantity)
        // Check all products in cart for ice overrides
        $totalIceCostFromOverrides = 0.0;
        
        foreach ($cartItems as $item) {
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
        // If we found ice overrides, replace the calculated ice cost with override cost
        $iceCostAdjustment = $totalIceCostFromOverrides - $calculatedIceCost;
        
        // Debug logging (can be removed later)
        error_log("Checkout ice cost - Base cost: $calculatedCost, Ice override total: $totalIceCostFromOverrides, Ice calculated: $calculatedIceCost, Adjustment: $iceCostAdjustment, Final cost: " . ($calculatedCost + $iceCostAdjustment));
        if (!empty($cartItems)) {
            $firstProductID = (int)($cartItems[0]['productID'] ?? 0);
            error_log("First product ID: $firstProductID, Has override: " . (isset($allOverrides[$firstProductID]) ? 'yes' : 'no'));
            if (isset($allOverrides[$firstProductID])) {
                error_log("Overrides for product $firstProductID: " . json_encode($allOverrides[$firstProductID]));
            }
        }
        
        // Round to 2 decimal places for accuracy
        $orderIngredientCost = round($calculatedCost + $iceCostAdjustment, 2);

        // For new systems: Skip ingredients that don't exist in database instead of throwing error
        // Only log missing ingredients for debugging, but allow checkout to proceed
        $missingIngredients = [];
        foreach ($ingredientUsage as $inventoryID => $_) {
            if (!isset($mappedUsage[$inventoryID])) {
                $missingIngredients[] = $inventoryID;
            }
        }
        
        // Log missing ingredients if any (for debugging), but don't fail checkout
        if (!empty($missingIngredients)) {
            error_log("Warning: Some ingredients referenced in recipes don't exist in inventory: " . implode(', ', $missingIngredients));
        }

        // Only process ingredients that exist and can be mapped
        foreach ($mappedUsage as $inventoryID => $data) {
            $fractionNeeded = $data['fraction'] ?? 0;
            if ($fractionNeeded <= 0) {
                continue;
            }

            $currentStock = $data['current_stock'] ?? 0;
            if ($currentStock + 1e-6 < $fractionNeeded) {
                throw new Exception("Insufficient stock for inventory ID {$inventoryID} (needs {$fractionNeeded}, has {$currentStock})");
            }

            $newStock = $currentStock - $fractionNeeded;
            $costPrice = $data['cost_price'] ?? 0;
            $newValue = $newStock * $costPrice;

            $updateStmt = $conn->prepare("UPDATE inventory SET `Current_Stock` = ?, `Total_Value` = ? WHERE inventoryID = ?");
            $updateStmt->bind_param('ddi', $newStock, $newValue, $inventoryID);
            $updateStmt->execute();
        }
    }

    // Calculate profit from ingredient cost
    $totalProfit = $totalAmount - $orderIngredientCost;

    // Update order with computed ingredient cost
    $costStmt = $conn->prepare("UPDATE orders SET ingredientCost = ? WHERE orderID = ?");
    $roundedCost = round($orderIngredientCost, 2);
    $costStmt->bind_param('di', $roundedCost, $orderID);
    $costStmt->execute();

    // Log audit activity
    if (file_exists(__DIR__ . '/audit_log.php')) {
        require_once __DIR__ . '/audit_log.php';
        logOrderActivity($conn, $_SESSION['userID'], 'order_completed', "Order ID: $orderID, Total: $totalAmount");
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'orderID' => $orderID,
        'totalAmount' => $totalAmount,
        'totalProfit' => round($totalProfit, 2),
        'receipt' => [
            'orderID' => $orderID,
            'items' => $cartItems,
            'subtotal' => $subtotal,
            'totalAmount' => $totalAmount,
            'totalProfit' => round($totalProfit, 2),
            'paymentMethod' => $paymentMethod,
            'cashReceived' => $cashReceived,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>

