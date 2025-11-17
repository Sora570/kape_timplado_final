<?php
// Start output buffering to catch any errors
ob_start();

// Suppress error output to prevent breaking JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db_connect.php';
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$productID = intval($_POST['productID'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$productID) {
    echo json_encode(['status' => 'error', 'message' => 'ProductID is required']);
    exit;
}

require_once __DIR__ . '/inventory_usage_helpers.php';

/**
 * Normalize unit to match recipe format (ml, g, or pc)
 */
function normalizeUnitForRecipe(string $unit): string {
    $unit = strtolower(trim($unit));
    return match ($unit) {
        'milliliter', 'millilitre', 'ml', 'l', 'liter', 'litre' => 'ml',
        'gram', 'grams', 'g', 'kg', 'kilogram' => 'g',
        'piece', 'pieces', 'pc', 'pcs', 'unit', 'units', 'ounce', 'ounces', 'oz' => 'pc',
        default => $unit ?: 'ml',
    };
}

/**
 * Update or insert recipe in recipes table
 */
function updateRecipeInTable(mysqli $conn, int $productID, int $inventoryID, float $amount, string $unit): void {
    $normalizedUnit = normalizeUnitForRecipe($unit);
    
    // Check if recipe already exists
    $checkStmt = $conn->prepare("SELECT recipeID, display_order FROM recipes WHERE productID = ? AND inventoryID = ?");
    $checkStmt->bind_param('ii', $productID, $inventoryID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        // Update existing recipe (preserve display_order)
        $updateStmt = $conn->prepare("
            UPDATE recipes 
            SET amount = ?, unit = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE recipeID = ?
        ");
        $updateStmt->bind_param('dsi', $amount, $normalizedUnit, $existing['recipeID']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Get current max display_order for this product (only for new recipes)
        $orderStmt = $conn->prepare("SELECT MAX(display_order) as max_order FROM recipes WHERE productID = ?");
        $orderStmt->bind_param('i', $productID);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        $orderRow = $orderResult->fetch_assoc();
        $displayOrder = ($orderRow['max_order'] ?? -1) + 1;
        $orderStmt->close();
        
        // Insert new recipe
        $insertStmt = $conn->prepare("
            INSERT INTO recipes (productID, inventoryID, amount, unit, display_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param('iidsi', $productID, $inventoryID, $amount, $normalizedUnit, $displayOrder);
        $insertStmt->execute();
        $insertStmt->close();
    }
}

/**
 * Remove recipe from recipes table
 */
function removeRecipeFromTable(mysqli $conn, int $productID, int $inventoryID): void {
    $deleteStmt = $conn->prepare("DELETE FROM recipes WHERE productID = ? AND inventoryID = ?");
    $deleteStmt->bind_param('ii', $productID, $inventoryID);
    $deleteStmt->execute();
    $deleteStmt->close();
}

try {
    $conn->begin_transaction();
    
    switch ($action) {
        case 'update_ingredient_cost':
            // Update ingredient cost (for wildcards or overrides)
            $inventoryID = intval($_POST['inventoryID'] ?? 0);
            $ingredientCost = floatval($_POST['ingredientCost'] ?? 0);
            
            if (!$inventoryID || $ingredientCost < 0) {
                throw new Exception('Invalid parameters');
            }
            
            // Check if override exists and is removed
            $existingOverrides = get_production_cost_overrides_for_product($conn, $productID);
            $isRemoved = false;
            foreach ($existingOverrides as $ov) {
                if ($ov['inventoryID'] === $inventoryID && $ov['removed']) {
                    // Remove the "removed" marker first
                    remove_production_cost_override_from_db($conn, $productID, $inventoryID);
                    break;
                }
            }
            
            // Save or update override
            persist_production_cost_override_to_db($conn, $productID, $inventoryID, null, $ingredientCost, false);
            break;
            
        case 'add_ingredient':
            // Add a new ingredient to the product
            $inventoryID = intval($_POST['inventoryID'] ?? 0);
            $neededPerCup = floatval($_POST['neededPerCup'] ?? 0);
            $ingredientCost = floatval($_POST['ingredientCost'] ?? 0);
            
            if (!$inventoryID || $neededPerCup < 0 || $ingredientCost < 0) {
                throw new Exception('Invalid parameters');
            }
            
            // Get inventory item to get unit
            $invStmt = $conn->prepare("SELECT Unit FROM inventory WHERE inventoryID = ?");
            $invStmt->bind_param('i', $inventoryID);
            $invStmt->execute();
            $invResult = $invStmt->get_result();
            $inv = $invResult->fetch_assoc();
            $invStmt->close();
            
            if (!$inv) {
                throw new Exception('Inventory item not found');
            }
            
            $unit = $inv['Unit'] ?? 'ml';
            
            // Check if already exists (but allow re-adding if it was previously removed)
            $existingOverrides = get_production_cost_overrides_for_product($conn, $productID);
            $exists = false;
            $isRemoved = false;
            foreach ($existingOverrides as $ov) {
                if ($ov['inventoryID'] === $inventoryID) {
                    if ($ov['removed']) {
                        // It's a removed marker - we can re-add it
                        $isRemoved = true;
                    } else {
                        // It's an active ingredient - cannot add duplicate
                        $exists = true;
                    }
                    break;
                }
            }
            
            // Check if it exists in recipes table
            $recipeCheckStmt = $conn->prepare("SELECT recipeID FROM recipes WHERE productID = ? AND inventoryID = ?");
            $recipeCheckStmt->bind_param('ii', $productID, $inventoryID);
            $recipeCheckStmt->execute();
            $recipeCheckResult = $recipeCheckStmt->get_result();
            $recipeExists = $recipeCheckResult->fetch_assoc() !== null;
            $recipeCheckStmt->close();
            
            if ($exists || $recipeExists) {
                throw new Exception('Ingredient already exists in this product');
            }
            
            // If it was previously removed, remove the "removed" marker first
            if ($isRemoved) {
                remove_production_cost_override_from_db($conn, $productID, $inventoryID);
            }
            
            // Add to recipes table
            updateRecipeInTable($conn, $productID, $inventoryID, $neededPerCup, $unit);
            
            // Add new override
            persist_production_cost_override_to_db($conn, $productID, $inventoryID, $neededPerCup, $ingredientCost, false);
            break;
            
        case 'update_needed_per_cup':
            // Update needed per cup and recalculate ingredient cost
            $inventoryID = intval($_POST['inventoryID'] ?? 0);
            $neededPerCup = floatval($_POST['neededPerCup'] ?? 0);
            
            if (!$inventoryID || $neededPerCup < 0) {
                throw new Exception('Invalid parameters');
            }
            
            // Get inventory item to calculate price per unit and get unit
            $invStmt = $conn->prepare("SELECT Size, Unit, Cost_Price FROM inventory WHERE inventoryID = ?");
            $invStmt->bind_param('i', $inventoryID);
            $invStmt->execute();
            $invResult = $invStmt->get_result();
            $inv = $invResult->fetch_assoc();
            $invStmt->close();
            
            if (!$inv) {
                throw new Exception('Inventory item not found');
            }
            
            $unit = $inv['Unit'] ?? 'ml';
            
            // Calculate price per unit
            $packSize = $inv['Size'] ?? '';
            $packPrice = (float)($inv['Cost_Price'] ?? 0);
            $sizeValue = floatval(preg_replace('/[^0-9.]/', '', $packSize)) ?: 1;
            $pricePerUnit = $sizeValue > 0 ? ($packPrice / $sizeValue) : 0;
            $ingredientCost = $pricePerUnit * $neededPerCup;
            
            // Update recipes table
            updateRecipeInTable($conn, $productID, $inventoryID, $neededPerCup, $unit);
            
            // Update or create override
            persist_production_cost_override_to_db($conn, $productID, $inventoryID, $neededPerCup, $ingredientCost, false);
            break;
            
        case 'remove_ingredient':
            // Remove an ingredient - can now remove base recipe ingredients too
            $inventoryID = intval($_POST['inventoryID'] ?? 0);
            
            if (!$inventoryID) {
                throw new Exception('Invalid inventory ID');
            }
            
            // Remove from recipes table
            removeRecipeFromTable($conn, $productID, $inventoryID);
            
            // Remove existing override if it exists
            remove_production_cost_override_from_db($conn, $productID, $inventoryID);
            
            // Add a "removed" marker for base recipe ingredients (in case it was in base recipe)
            // This tells production_cost_get.php to skip this ingredient even if it's in base recipe
            persist_production_cost_override_to_db($conn, $productID, $inventoryID, null, null, true);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Clear output buffer and send JSON
    ob_end_clean();
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Error $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
}

