// Inventory Management JavaScript

let inventoryData = [];
let filteredData = [];
let productCostingData = [];
let inventoryTabsInitialized = false;
let profitTrackerData = [];
let profitTrackerTotals = {
    revenue: 0,
    ingredient_cost: 0,
    other_expenses: 0,
    profit: 0
};
let profitTrackerWeeks = [];
let profitTrackerLoaded = false;
const formatCurrency = value => `₱${Number(value || 0).toFixed(2)}`;
const formatPricePerUnit = value => `₱${Number(value || 0).toFixed(3)}`;
const formatNumber = value => {
    if (value === null || value === undefined || value === '') return '-';
    const num = Number(value);
    if (Number.isNaN(num)) return value;
    return Number.isInteger(num) ? num.toString() : num.toFixed(2);
};

const normalizeWhitespace = (value) => (value || '').toString().replace(/\s+/g, ' ').trim();
const normalizeCategoryValue = (value) => normalizeWhitespace(value).toLowerCase();
const getCategoryLabel = (value) => normalizeWhitespace(value) || 'Uncategorized';

// Initialize inventory when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeInventory();
    
    // Setup production cost listeners with multiple approaches for reliability
    setupProductionCostListeners();
    
    // Also try direct attachment after a delay
    setTimeout(function() {
        const addIngredientBtn = document.getElementById('addIngredientBtn');
        if (addIngredientBtn) {
            // Remove any existing listeners first
            const newBtn = addIngredientBtn.cloneNode(true);
            addIngredientBtn.parentNode.replaceChild(newBtn, addIngredientBtn);
            
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Add Ingredient clicked (direct)');
                if (typeof showAddIngredientModal === 'function') {
                    showAddIngredientModal();
                } else {
                    console.error('showAddIngredientModal not defined');
                    alert('Error: Function not loaded. Please refresh the page.');
                }
                return false;
            });
            console.log('Direct listener attached to Add Ingredient button');
        } else {
            console.warn('addIngredientBtn still not found after delay');
        }
    }, 1000);
});

function initializeInventory() {
    loadInventoryData();
    setupEventListeners();
    setupInventoryTabs();
}

function setupEventListeners() {
    // Search and Filter
    const inventorySearch = document.getElementById('inventorySearch');
    if (inventorySearch) {
        inventorySearch.addEventListener('input', filterInventory);
    }

    const stockFilter = document.getElementById('stockFilter');
    if (stockFilter) {
        stockFilter.addEventListener('change', filterInventory);
    }

    const costingCategoryFilter = document.getElementById('costingCategoryFilter');
    if (costingCategoryFilter) {
        costingCategoryFilter.addEventListener('change', renderCostingTable);
    }
    const profitRefreshBtn = document.getElementById('profitTrackerRefresh');
    if (profitRefreshBtn) {
        profitRefreshBtn.addEventListener('click', () => loadProfitTracker(true));
    }
    const exportProfitBtn = document.getElementById('exportProfitCsvBtn');
    if (exportProfitBtn) {
        exportProfitBtn.addEventListener('click', exportProfitTracker);
    }
    
    // Production Cost tab - use event delegation (more reliable)
    // Already set up in DOMContentLoaded with delay, but also use delegation as backup
    setupProductionCostListeners();
}

async function loadInventoryData() {
    await loadInventoryCategories();
    Promise.all([
        fetch('db/inventory_get.php'),
        fetch('db/inventory_costing.php')
    ])
        .then(async ([inventoryRes, costingRes]) => {
            if (!inventoryRes.ok) {
                throw new Error('Failed to load inventory data: ' + inventoryRes.status);
            }
            
            // Check content type before parsing JSON
            const inventoryContentType = inventoryRes.headers.get('content-type') || '';
            
            // Parse inventory data with error handling
            let inventoryPayload;
            try {
                const inventoryText = await inventoryRes.text();
                
                // Check if response looks like HTML (error page)
                if (inventoryText.trim().startsWith('<') || !inventoryContentType.includes('application/json')) {
                    console.error('Non-JSON response from inventory_get.php:', inventoryText.substring(0, 200));
                    throw new Error('Server returned invalid response. Expected JSON but got HTML or non-JSON content.');
                }
                
                inventoryPayload = JSON.parse(inventoryText);
                
                // Check if response is an error object
                if (inventoryPayload && inventoryPayload.status === 'error') {
                    throw new Error(inventoryPayload.message || 'Error loading inventory data');
                }
            } catch (parseError) {
                console.error('JSON parse error for inventory data:', parseError);
                throw new Error('Failed to parse inventory data: ' + parseError.message);
            }
            
            // Handle costing data
            const costingPromise = costingRes.ok ? costingRes.json().catch(() => []) : Promise.resolve([]);
            const costingPayload = await costingPromise;
            
            return [inventoryPayload, costingPayload];
        })
        .then(([inventoryPayload, costingPayload]) => {
            inventoryData = Array.isArray(inventoryPayload) ? inventoryPayload : [];
            filteredData = [...inventoryData];
            productCostingData = Array.isArray(costingPayload) ? costingPayload : [];
            populateCostingCategories();
            renderLowStockTable();
            renderCostingTable();
            updateSummaryCards();
        })
        .catch(error => {
            console.error('Error loading inventory:', error);
            showToast('Error loading inventory data: ' + (error.message || 'Unknown error'), 'error');
        });
}

async function reloadCostingData() {
    try {
        // Add cache-busting parameter to ensure fresh data
        const cacheBuster = new Date().getTime();
        // Force no-cache to ensure we get the latest data
        const response = await fetch(`db/inventory_costing.php?t=${cacheBuster}`, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        });
        if (response.ok) {
            const costingPayload = await response.json();
            productCostingData = Array.isArray(costingPayload) ? costingPayload : [];
            renderCostingTable();
        } else {
            console.error('Failed to reload costing data:', response.status, response.statusText);
        }
    } catch (error) {
        console.error('Error reloading costing data:', error);
    }
}

// Make reloadCostingData globally accessible so it can be called from other pages
if (typeof window !== 'undefined') {
    window.reloadCostingData = reloadCostingData;
}

function renderLowStockTable() {
    const tbody = document.getElementById('inventory-lowstock-list');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!filteredData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align:center; padding:30px; color:#6b7280;">
                    No inventory items found.
                </td>
            </tr>
        `;
        return;
    }

    filteredData.forEach(item => {
        const row = document.createElement('tr');
        row.dataset.inventoryId = item.inventoryID;

        const currentStock = Number(item['Current Stock'] ?? 0);
        const reorderPoint = Number(item['Reorder Point'] ?? item.reorder_point ?? 0);
        const stockBucket = deriveStockBucket(item);
        if (stockBucket === 'low-stock' || stockBucket === 'out-of-stock') {
            row.classList.add('low-stock-warning');
        }

        const sizeText = item['Size'] ?? '-';
        const qtyPerOrder = item['Qty Per Order'] ?? item['Usage per Product'] ?? '-';
        const packCost = Number(item['Cost Price'] ?? item.cost_price ?? 0);
        
        // Calculate Price Per Unit: Cost of Pack / Size (numeric value)
        let pricePerUnit = '-';
        let pricePerUnitValue = 0;
        if (packCost > 0 && sizeText && sizeText !== '-') {
            // Extract numeric value from Size (e.g., "500" from "500g" or "1" from "1L")
            const sizeMatch = sizeText.toString().match(/(\d+\.?\d*)/);
            if (sizeMatch) {
                const sizeValue = Number(sizeMatch[1]);
                if (sizeValue > 0) {
                    pricePerUnitValue = packCost / sizeValue;
                    pricePerUnit = formatPricePerUnit(pricePerUnitValue);
                }
            }
        }

        // Calculate Cost / Order: Qty / Order * Price Per Unit
        let costPerOrder = '-';
        if (pricePerUnitValue > 0 && qtyPerOrder && qtyPerOrder !== '-') {
            // Extract numeric value from Qty / Order (e.g., "30" from "30 mL" or "17.31" from "17.31g")
            const qtyMatch = qtyPerOrder.toString().match(/(\d+\.?\d*)/);
            if (qtyMatch) {
                const qtyValue = Number(qtyMatch[1]);
                if (qtyValue > 0) {
                    costPerOrder = formatCurrency(qtyValue * pricePerUnitValue);
                }
            }
        }

        row.innerHTML = `
            <td class="font-medium text-gray-800">${item['InventoryName']}</td>
            <td class="text-gray-600">${item['Unit']}</td>
            <td class="text-gray-700">${sizeText}</td>
            <td class="text-center font-semibold text-gray-800">${formatNumber(currentStock)}</td>
            <td class="text-center text-gray-700">${formatNumber(reorderPoint)}</td>
            <td class="text-center text-gray-800">${formatCurrency(packCost)}</td>
            <td class="text-center text-gray-800">${pricePerUnit}</td>
            <td class="text-center text-gray-700">${qtyPerOrder || '-'}</td>
            <td class="text-center text-gray-800">${costPerOrder}</td>
        `;

        row.addEventListener('click', (event) => {
            if (
                event.target.closest('button') ||
                event.target.closest('a') ||
                event.target.closest('input') ||
                event.target.closest('select') ||
                event.target.closest('label')
            ) {
                return;
            }
            showUpdateInventoryModal(item.inventoryID);
        });

        tbody.appendChild(row);
    });
}

function renderCostingTable() {
    const tbody = document.getElementById('inventory-costing-list');
    if (!tbody) return;
    tbody.innerHTML = '';
    const selectedCategory = (document.getElementById('costingCategoryFilter')?.value || '').toLowerCase();

    if (!productCostingData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:30px; color:#6b7280;">
                    No costing data available.
                </td>
            </tr>
        `;
        return;
    }

    productCostingData.forEach(entry => {
        const entryCategoryLabel = getCategoryLabel(entry['Category']);
        const entryCategoryKey = normalizeCategoryValue(entryCategoryLabel);
        if (selectedCategory && entryCategoryKey !== selectedCategory) {
            return;
        }
        const actualPrice = Number(entry['Menu Price'] ?? entry.menuPrice ?? 0);
        const totalCost = Number(entry['Cost'] ?? entry.cost ?? 0);
        const profit = Number(entry['Profit'] ?? entry.profit ?? (actualPrice - totalCost));
        const margin = Number(entry['Margin'] ?? entry.margin ?? 0);

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${entryCategoryLabel}</td>
            <td class="font-medium text-gray-800">${entry['Product'] || '-'}</td>
            <td class="text-gray-600">${entry['Size'] || '-'}</td>
            <td class="text-center">${formatCurrency(actualPrice)}</td>
            <td class="text-center">${formatCurrency(totalCost)}</td>
            <td class="text-center">${formatCurrency(profit)}</td>
            <td class="text-center">${margin.toFixed(2)}%</td>
        `;
        tbody.appendChild(row);
    });
}

function populateCostingCategories() {
    const filter = document.getElementById('costingCategoryFilter');
    if (!filter) return;

    const categoryMap = new Map();
    productCostingData.forEach(entry => {
        const label = getCategoryLabel(entry['Category']);
        const normalized = normalizeCategoryValue(label);
        if (!normalized) return;
        if (!categoryMap.has(normalized)) {
            categoryMap.set(normalized, label);
        }
    });

    const categories = Array.from(categoryMap.entries())
        .sort(([, labelA], [, labelB]) => labelA.localeCompare(labelB));

    const previousValue = normalizeCategoryValue(filter.value || '');
    filter.innerHTML = '<option value="">All Categories</option>';

    categories.forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        filter.appendChild(option);
    });

    if (previousValue && categoryMap.has(previousValue)) {
        filter.value = previousValue;
    } else {
        filter.value = '';
    }
}

function updateSummaryCards() {
    const totalItemsEl = document.getElementById('totalItems');
    const lowStockEl = document.getElementById('lowStockItems');
    const inStockEl = document.getElementById('inStockItems');
    const outOfStockEl = document.getElementById('outOfStockItems');

    if (!totalItemsEl || !lowStockEl || !inStockEl || !outOfStockEl) {
        return;
    }

    const summary = {
        total: inventoryData.length,
        inStock: 0,
        lowStock: 0,
        outOfStock: 0
    };

    inventoryData.forEach(item => {
        const bucket = deriveStockBucket(item);
        if (bucket === 'out-of-stock') {
            summary.outOfStock += 1;
        } else if (bucket === 'low-stock') {
            summary.lowStock += 1;
        } else {
            summary.inStock += 1;
        }
    });

    totalItemsEl.textContent = summary.total;
    lowStockEl.textContent = summary.lowStock;
    inStockEl.textContent = summary.inStock;
    outOfStockEl.textContent = summary.outOfStock;
}

function filterInventory() {
    const searchTerm = (document.getElementById('inventorySearch')?.value || '').toLowerCase();
    const stockFilter = document.getElementById('stockFilter')?.value || '';

    filteredData = inventoryData.filter(item => {
        const targets = [
            item['InventoryName'],
            item['Size']
        ];
        const matchesSearch = !searchTerm || targets.some(value =>
            (value || '').toString().toLowerCase().includes(searchTerm)
        );

        let matchesStock = true;
        if (stockFilter) {
            matchesStock = deriveStockBucket(item) === stockFilter;
        }
        return matchesSearch && matchesStock;
    });

    renderLowStockTable();
}

function deriveStockBucket(item) {
    const currentStock = Number(item['Current Stock'] ?? item.current_stock ?? 0);
    const reorderPoint = Number(item['Reorder Point'] ?? item.reorder_point ?? 0);
    const normalizedStatus = (item['Status'] ?? '').toString().toLowerCase();

    if (currentStock <= 0 || normalizedStatus === 'out_of_stock' || normalizedStatus === 'out-of-stock') {
        return 'out-of-stock';
    }

    if (
        (reorderPoint > 0 && currentStock <= reorderPoint) ||
        normalizedStatus === 'low_stock' ||
        normalizedStatus === 'low-stock'
    ) {
        return 'low-stock';
    }

    return 'in-stock';
}

function loadInventoryCategories() {
    return Promise.resolve();
}

function updateStock(inventoryID, newStock) {
    const stock = parseInt(newStock);
    if (isNaN(stock) || stock < 0) {
        showToast('Invalid stock quantity', 'error');
        return;
    }
    
    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&currentStock=${stock}&action=update_stock`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Stock updated successfully', 'success');
            loadInventoryData(); // Refresh data
        } else {
            showToast(data.message || 'Error updating stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating stock:', error);
        showToast('Error updating stock', 'error');
    });
}

function updateMinStock(inventoryID, newMinStock) {
    const minStock = parseInt(newMinStock);
    if (isNaN(minStock) || minStock < 0) {
        showToast('Invalid minimum stock quantity', 'error');
        return;
    }
    
    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&minStock=${minStock}&action=update_min_stock`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Minimum stock updated successfully', 'success');
            loadInventoryData();
        } else {
            showToast(data.message || 'Error updating minimum stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating minimum stock:', error);
        showToast('Error updating minimum stock', 'error');
    });
}

function updateMaxStock(inventoryID, newMaxStock) {
    const maxStock = parseInt(newMaxStock);
    if (isNaN(maxStock) || maxStock < 0) {
        showToast('Invalid maximum stock quantity', 'error');
        return;
    }
    
    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&maxStock=${maxStock}&action=update_max_stock`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Maximum stock updated successfully', 'success');
            loadInventoryData();
        } else {
            showToast(data.message || 'Error updating maximum stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating maximum stock:', error);
        showToast('Error updating maximum stock', 'error');
    });
}

function updateCostPrice(inventoryID, newCostPrice) {
    const costPrice = parseFloat(newCostPrice);
    if (isNaN(costPrice) || costPrice < 0) {
        showToast('Invalid cost price', 'error');
        return;
    }
    
    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&costPrice=${costPrice}&action=update_cost_price`
    })
    .then(response => response.json())
    .then(async data => {
        if (data.status === 'success') {
            showToast('Cost price updated successfully', 'success');
            await loadInventoryData();
            // Small delay to ensure database update is committed
            await new Promise(resolve => setTimeout(resolve, 100));
            // Refresh production cost table if a product is currently selected
            await refreshProductionCostTableIfSelected();
            // Refresh costing table to reflect updated production cost
            await reloadCostingData();
        } else {
            showToast(data.message || 'Error updating cost price', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating cost price:', error);
        showToast('Error updating cost price', 'error');
    });
}

function updateSellingPrice(inventoryID, newSellingPrice) {
    const sellingPrice = parseFloat(newSellingPrice);
    if (isNaN(sellingPrice) || sellingPrice < 0) {
        showToast('Invalid selling price', 'error');
        return;
    }
    
    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&sellingPrice=${sellingPrice}&action=update_selling_price`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('Selling price updated successfully', 'success');
            loadInventoryData();
        } else {
            showToast(data.message || 'Error updating selling price', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating selling price:', error);
        showToast('Error updating selling price', 'error');
    });
}

function showAddStockModal() {
    // Create modal if it doesn't exist
    let modal = document.getElementById('addStockModal');
    if (!modal) {
        modal = createAddStockModal();
        document.body.appendChild(modal);
    }

    // Clear form fields to prevent prefilled inputs
    document.getElementById('inventoryName').value = '';
    document.getElementById('size').value = '';
    document.getElementById('unitSelect').value = '';
    document.getElementById('currentStock').value = '';
    document.getElementById('costPrice').value = '';
    const reorderInput = document.getElementById('reorderPoint');
    if (reorderInput) reorderInput.value = '';
    const qtyPerOrderInput = document.getElementById('qtyPerOrder');
    if (qtyPerOrderInput) qtyPerOrderInput.value = '';

    // Reset display property and show modal with animation
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    loadUnitsForModal();
}

function createAddStockModal() {
    const modal = document.createElement('div');
    modal.id = 'addStockModal';
    modal.className = 'modal-overlay';

    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Stock</h3>
                <button class="modal-close" onclick="closeModal('addStockModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStockForm" onsubmit="event.preventDefault(); saveStock(); return false;">
                    <div class="form-group">
                        <label for="inventoryName" class="form-label">Item Name</label>
                        <input type="text" id="inventoryName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="size" class="form-label">Size</label>
                        <input type="text" id="size" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="unitSelect" class="form-label">Unit</label>
                        <select id="unitSelect" class="form-input" required>
                            <option value="">Select a unit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="currentStock" class="form-label">Current Stock</label>
                        <input type="number" id="currentStock" class="form-input" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="costPrice" class="form-label">Cost Price (₱)</label>
                        <input type="number" id="costPrice" class="form-input" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="reorderPoint" class="form-label">Reorder Point</label>
                        <input type="number" id="reorderPoint" class="form-input" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="qtyPerOrder" class="form-label">Qty / Order</label>
                        <input type="text" id="qtyPerOrder" class="form-input" placeholder="e.g., 30 mL">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('addStockModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveStock()">Save Stock</button>
            </div>
        </div>
    `;

    return modal;
}

function loadUnitsForModal() {
    fetch('db/product_units_get.php')
        .then(response => response.json())
        .then(units => {
            const unitSelect = document.getElementById('unitSelect');
            populateUnitSelect(unitSelect, units);
        })
        .catch(error => {
            console.error('Error loading units:', error);
            showToast('Error loading units', 'error');
        });
}



function calculateProfit() {
    const costPrice = parseFloat(document.getElementById('costPrice').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('sellingPrice').value) || 0;
    const currentStock = parseInt(document.getElementById('currentStock').value) || 0;
    
    const profitPerUnit = sellingPrice - costPrice;
    const profitMargin = sellingPrice > 0 ? (profitPerUnit / sellingPrice) * 100 : 0;
    const totalValue = costPrice * currentStock;
    
    // Update display
    document.getElementById('profitMarginDisplay').textContent = `${profitMargin.toFixed(2)}%`;
    document.getElementById('profitPerUnit').textContent = `₱${profitPerUnit.toFixed(2)}`;
    document.getElementById('totalValueDisplay').textContent = `₱${totalValue.toFixed(2)}`;
    
    // Color coding
    const profitMarginElement = document.getElementById('profitMarginDisplay');
    const profitPerUnitElement = document.getElementById('profitPerUnit');
    
    if (profitMargin > 0) {
        profitMarginElement.style.color = '#059669';
        profitPerUnitElement.style.color = '#059669';
    } else if (profitMargin < 0) {
        profitMarginElement.style.color = '#dc2626';
        profitPerUnitElement.style.color = '#dc2626';
    } else {
        profitMarginElement.style.color = '#6b7280';
        profitPerUnitElement.style.color = '#6b7280';
    }
}

function saveStock() {
    console.log('saveStock function called'); // Debug log
    
    const inventoryNameEl = document.getElementById('inventoryName');
    const sizeEl = document.getElementById('size');
    const unitEl = document.getElementById('unitSelect');
    const currentStockEl = document.getElementById('currentStock');
    const costPriceEl = document.getElementById('costPrice');
    const reorderPointEl = document.getElementById('reorderPoint');
    const qtyPerOrderEl = document.getElementById('qtyPerOrder');
    
    if (!inventoryNameEl || !sizeEl || !unitEl || !currentStockEl || !costPriceEl) {
        console.error('Required form elements not found');
        showToast('Form error: Required fields not found', 'error');
        return;
    }
    
    const inventoryName = inventoryNameEl.value.trim();
    const size = sizeEl.value.trim();
    const unit = unitEl.value;
    const currentStock = currentStockEl.value;
    const costPrice = costPriceEl.value;
    const reorderPoint = reorderPointEl ? reorderPointEl.value : '';
    const qtyPerOrder = qtyPerOrderEl ? qtyPerOrderEl.value.trim() : '';

    console.log('Form values:', { inventoryName, size, unit, currentStock, costPrice, reorderPoint, qtyPerOrder }); // Debug log

    if (!inventoryName || !size || !unit || !currentStock || !costPrice) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    // Calculate total value
    const totalValue = costPrice * currentStock;

    console.log('Sending fetch request...'); // Debug log
    
    fetch('db/inventory_add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `InventoryName=${encodeURIComponent(inventoryName)}&Size=${encodeURIComponent(size)}&Unit=${encodeURIComponent(unit)}&Current_Stock=${currentStock}&Cost_Price=${costPrice}&Total_Value=${totalValue}&reorder_point=${reorderPoint}&qty_per_order=${encodeURIComponent(qtyPerOrder)}`
    })
    .then(async response => {
        console.log('Response received, status:', response.status, response.statusText); // Debug log
        
        // Read response text first
        const responseText = await response.text();
        console.log('Response text:', responseText); // Debug log
        
        // Check if response is ok
        if (!response.ok) {
            console.error('Server error response:', responseText);
            let errorMessage = 'Server error';
            try {
                const errorData = JSON.parse(responseText);
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                errorMessage = responseText || 'Internal server error';
            }
            showToast('Error: ' + errorMessage, 'error');
            return;
        }
        
        // Try to parse JSON
        let data;
        try {
            if (!responseText || responseText.trim() === '') {
                throw new Error('Empty response from server');
            }
            data = JSON.parse(responseText);
            console.log('Parsed response data:', data); // Debug log
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            showToast('Error parsing server response. Please check console for details.', 'error');
            return;
        }
        
        if (data.status === 'success') {
            console.log('Success! Reloading inventory data...'); // Debug log
            showToast('Stock added successfully', 'success');
            closeModal('addStockModal');
            await loadInventoryData();
            console.log('Inventory data reloaded'); // Debug log
        } else {
            console.error('Server returned error status:', data); // Debug log
            showToast(data.message || 'Error adding stock', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding stock:', error);
        showToast('Error adding stock: ' + error.message, 'error');
    });
}

function populateUnitSelect(selectElement, units, selectedUnit = '') {
    if (!selectElement) return;
    selectElement.innerHTML = '<option value="">Select a unit</option>';

    const normalizedSelected = normalizeWhitespace(selectedUnit).toLowerCase();
    if (!Array.isArray(units) || units.length === 0) {
        const option = document.createElement('option');
        option.value = "";
        option.textContent = "No units available";
        option.disabled = true;
        selectElement.appendChild(option);
        return;
    }

    let hasMatch = false;
    units.forEach(unit => {
        const symbol = normalizeWhitespace(unit.unit_symbol);
        const name = normalizeWhitespace(unit.unit_name);
        const optionValue = symbol || name;
        if (!optionValue) {
            return;
        }

        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = name && symbol && name.toLowerCase() !== symbol.toLowerCase()
            ? `${name} (${symbol})`
            : (name || symbol);

        if (normalizedSelected) {
            const normalizedSymbol = symbol.toLowerCase();
            const normalizedName = name.toLowerCase();
            if (!hasMatch && (normalizedSelected === normalizedSymbol || normalizedSelected === normalizedName)) {
                option.selected = true;
                hasMatch = true;
            }
        }
        selectElement.appendChild(option);
    });

    if (!hasMatch && normalizedSelected) {
        const fallbackOption = document.createElement('option');
        fallbackOption.value = selectedUnit;
        fallbackOption.textContent = selectedUnit;
        fallbackOption.selected = true;
        selectElement.appendChild(fallbackOption);
    }
}

function showUpdateInventoryModal(inventoryID) {
    const item = inventoryData.find(item => item.inventoryID == inventoryID);
    if (!item) return;

    // Create modal if it doesn't exist
    let modal = document.getElementById('updateInventoryModal');
    if (!modal) {
        modal = createUpdateInventoryModal();
        document.body.appendChild(modal);
    }

    // Populate form fields with existing data
    document.getElementById('updateInventoryName').value = item['InventoryName'];
    document.getElementById('updateSize').value = item['Size'];
    document.getElementById('updateCurrentStock').value = item['Current Stock'];
    document.getElementById('updateCostPrice').value = item['Cost Price'];
    const reorderField = document.getElementById('updateReorderPoint');
    if (reorderField) reorderField.value = item['Reorder Point'] ?? item.reorder_point ?? '';
    const qtyPerOrderField = document.getElementById('updateQtyPerOrder');
    if (qtyPerOrderField) {
        qtyPerOrderField.value = item['Qty Per Order'] || '';
    }
    // Store inventoryID for update
    modal.setAttribute('data-inventory-id', inventoryID);

    // Reset display property and show modal with animation
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    loadUnitsForUpdateModal(item['Unit']);
}

function createUpdateInventoryModal() {
    const modal = document.createElement('div');
    modal.id = 'updateInventoryModal';
    modal.className = 'modal-overlay';

    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Inventory Item</h3>
                <button class="modal-close" onclick="closeModal('updateInventoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="updateInventoryForm">
                    <div class="form-group">
                        <label for="updateInventoryName" class="form-label">Item Name</label>
                        <input type="text" id="updateInventoryName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="updateSize" class="form-label">Size</label>
                        <input type="text" id="updateSize" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="updateUnitSelect" class="form-label">Unit</label>
                        <select id="updateUnitSelect" class="form-input" required>
                            <option value="">Select a unit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="updateCurrentStock" class="form-label">Current Stock</label>
                        <input type="number" id="updateCurrentStock" class="form-input" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="updateCostPrice" class="form-label">Cost Price (₱)</label>
                        <input type="number" id="updateCostPrice" class="form-input" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="updateReorderPoint" class="form-label">Reorder Point</label>
                        <input type="number" id="updateReorderPoint" class="form-input" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="updateQtyPerOrder" class="form-label">Qty / Order</label>
                        <input type="text" id="updateQtyPerOrder" class="form-input" placeholder="e.g., 30 mL">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('updateInventoryModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="updateInventory()">Update Item</button>
            </div>
        </div>
    `;

    return modal;
}

function loadUnitsForUpdateModal(selectedUnit) {
    fetch('db/product_units_get.php')
        .then(response => response.json())
        .then(units => {
            const unitSelect = document.getElementById('updateUnitSelect');
            populateUnitSelect(unitSelect, units, selectedUnit);
        })
        .catch(error => {
            console.error('Error loading units:', error);
            showToast('Error loading units', 'error');
        });
}

function updateInventory() {
    const modal = document.getElementById('updateInventoryModal');
    const inventoryID = modal.getAttribute('data-inventory-id');

    const inventoryName = document.getElementById('updateInventoryName').value.trim();
    const size = document.getElementById('updateSize').value.trim();
    const unit = document.getElementById('updateUnitSelect').value;
    const currentStock = document.getElementById('updateCurrentStock').value;
    const costPrice = document.getElementById('updateCostPrice').value;
    const reorderPoint = document.getElementById('updateReorderPoint')?.value ?? '';
    const qtyPerOrder = document.getElementById('updateQtyPerOrder')?.value.trim() ?? '';

    if (!inventoryName || !size || !unit || !currentStock || !costPrice) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    // Calculate total value
    const totalValue = costPrice * currentStock;

    fetch('db/inventory_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `inventoryID=${inventoryID}&InventoryName=${encodeURIComponent(inventoryName)}&Size=${encodeURIComponent(size)}&Unit=${encodeURIComponent(unit)}&Current_Stock=${currentStock}&Cost_Price=${costPrice}&Total_Value=${totalValue}&reorder_point=${reorderPoint}&qty_per_order=${encodeURIComponent(qtyPerOrder)}&action=update_inventory`
    })
    .then(response => response.json())
    .then(async data => {
        if (data.status === 'success') {
            showToast('Inventory item updated successfully', 'success');
            closeModal('updateInventoryModal');
            await loadInventoryData();
            // Refresh production cost table if a product is currently selected
            refreshProductionCostTableIfSelected();
        } else {
            showToast(data.message || 'Error updating inventory item', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating inventory:', error);
        showToast('Error updating inventory item', 'error');
    });
}

async function exportInventory() {
    const exportBtn = document.getElementById('exportInventoryBtn');
    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.classList.add('is-loading');
    }

    try {
        const response = await fetch(`db/inventory_export.php?_=${Date.now()}`, {
            cache: 'no-store',
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`Inventory export failed: ${response.status}`);
        }

        const blob = await response.blob();
        if (!blob || blob.size === 0) {
            showToast('Export failed: empty file received', 'error');
            return;
        }

        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `inventory_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showToast('Inventory data exported successfully', 'success');
    } catch (error) {
        console.error('Error exporting inventory:', error);
        showToast('Failed to export inventory data', 'error');
    } finally {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.classList.remove('is-loading');
        }
    }
}

function toCSVField(value) {
    if (value === null || value === undefined) {
        return '""';
    }
    const stringValue = String(value).replace(/"/g, '""');
    return `"${stringValue}"`;
}

function generateCSV(dataSet = productCostingData) {
    const headers = [
        'Category',
        'Product',
        'Size',
        'Actual Price',
        'Total Cost',
        'Profit',
        'Profit Margin (%)'
    ];

    const source = Array.isArray(dataSet) && dataSet.length ? dataSet : [];
    const rows = source.map(item => [
        item['Category'] || 'Uncategorized',
        item['Product'] || '—',
        item['Size'] || '—',
        Number(item['Menu Price'] ?? 0),
        Number(item['Cost'] ?? 0),
        Number(item['Profit'] ?? 0),
        Number(item['Margin'] ?? 0)
    ]);

    return [headers, ...rows]
        .map(row => row.map(toCSVField).join(','))
        .join('\n');
}

function exportProfitTracker() {
    const exportBtn = document.getElementById('exportProfitCsvBtn');
    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.classList.add('is-loading');
    }

    const finalizeExport = () => {
        if (!profitTrackerData.length && !profitTrackerWeeks.length) {
            showToast('No profit data to export', 'warning');
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.classList.remove('is-loading');
            }
            return;
        }
        try {
            const toRow = (entry) => {
                const revenue = Number(entry.revenue || 0);
                const ingredientCost = Number(entry.ingredient_cost || 0);
                const otherExpenses = Number(entry.other_expenses || 0);
                const profit = Number(entry.profit || (revenue - ingredientCost - otherExpenses));
                const margin = revenue > 0 ? (profit / revenue) * 100 : 0;
                return [
                    entry.label || entry.key || '',
                    Number(revenue.toFixed(2)),
                    Number(ingredientCost.toFixed(2)),
                    Number(profit.toFixed(2)),
                    Number(margin.toFixed(2))
                ];
            };

            const csvLines = [];
            if (profitTrackerData.length) {
                csvLines.push(['Monthly Overview']);
                csvLines.push(['Month', 'Sales', 'Ingredient Cost', 'Profit', 'Margin (%)']);
                profitTrackerData.forEach(entry => csvLines.push(toRow(entry)));
            }
            if (profitTrackerWeeks.length) {
                if (csvLines.length) csvLines.push(['']);
                csvLines.push(['Weekly Snapshot']);
                csvLines.push(['Week', 'Sales', 'Ingredient Cost', 'Profit', 'Margin (%)']);
                profitTrackerWeeks.forEach(entry => csvLines.push(toRow(entry)));
            }

            const csvContent = csvLines
                .map(row => row.map(toCSVField).join(','))
                .join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `profit_tracker_${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showToast('Profit data exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting profit tracker:', error);
            showToast('Failed to export profit data', 'error');
        } finally {
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.classList.remove('is-loading');
            }
        }
    };

    if (!profitTrackerLoaded) {
        loadProfitTracker(true)
            .then(finalizeExport)
            .catch(() => {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.classList.remove('is-loading');
                }
            });
    } else {
        finalizeExport();
    }
}
function setupInventoryTabs() {
    if (inventoryTabsInitialized) return;
    const tabs = document.querySelectorAll('.inventory-tab');
    const panels = document.querySelectorAll('.inventory-tab-panel');
    const costingFilter = document.getElementById('costingCategoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const inventoryCategoryFilter = document.getElementById('inventoryCategoryFilter');
    if (!tabs.length) return;

    const toggleFilterVisibility = (isCosting, isProductionCost) => {
        if (costingFilter) {
            costingFilter.hidden = !isCosting;
        }
        if (inventoryCategoryFilter) {
            inventoryCategoryFilter.style.display = (isCosting || isProductionCost) ? 'none' : '';
        }
        if (stockFilter) {
            stockFilter.style.display = (isCosting || isProductionCost) ? 'none' : '';
        }
        if (isCosting) {
            renderCostingTable();
        }
        if (isProductionCost) {
            loadProductionCostProducts();
        }
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(btn => btn.classList.remove('active'));
            tab.classList.add('active');

            panels.forEach(panel => panel.classList.remove('active'));
            const targetId = tab.getAttribute('data-target');
            const panel = document.getElementById(targetId);
            if (panel) panel.classList.add('active');

            const isProductionCost = targetId === 'inventoryProductionCostPanel';
            toggleFilterVisibility(targetId === 'inventoryCostingPanel', isProductionCost);
        });
    });

    const activeTab = document.querySelector('.inventory-tab.active');
    if (activeTab) {
        const targetId = activeTab.getAttribute('data-target');
        const isProductionCost = targetId === 'inventoryProductionCostPanel';
        toggleFilterVisibility(targetId === 'inventoryCostingPanel', isProductionCost);
    } else {
        toggleFilterVisibility(false, false);
    }

    inventoryTabsInitialized = true;
}

function setupProductionCostListeners() {
    // Use event delegation for production cost buttons (works even if button loads late)
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'addIngredientBtn' || e.target.closest('#addIngredientBtn'))) {
            const btn = e.target.id === 'addIngredientBtn' ? e.target : e.target.closest('#addIngredientBtn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Add Ingredient button clicked (delegation)');
                if (typeof showAddIngredientModal === 'function') {
                    showAddIngredientModal();
                } else {
                    console.error('showAddIngredientModal function not found');
                }
                return false;
            }
        }
        
        // Handle Create New Product button
        if (e.target && (e.target.id === 'createProductBtn' || e.target.closest('#createProductBtn'))) {
            const btn = e.target.id === 'createProductBtn' ? e.target : e.target.closest('#createProductBtn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Create Product button clicked (delegation)');
                if (typeof showCreateProductModal === 'function') {
                    showCreateProductModal();
                } else {
                    console.error('showCreateProductModal function not found');
                }
                return false;
            }
        }
    });
    
    // Handle product select change with delegation
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'productionCostProductSelect') {
            if (typeof handleProductionCostProductChange === 'function') {
                handleProductionCostProductChange();
            }
        }
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
}


function loadProfitTracker(showToastOnError = false) {
    const refreshBtn = document.getElementById('profitTrackerRefresh');
    const statusLabel = document.getElementById('profitTrackerUpdated');
    const tableBody = document.getElementById('profit-tracker-body');
    if (!tableBody) {
        return Promise.resolve();
    }

    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.classList.add('is-loading');
    }
    profitTrackerLoaded = false;

    return fetch('db/profit_tracker.php', {
        cache: 'no-store',
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Profit tracker request failed: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            profitTrackerData = Array.isArray(data.months) ? data.months : [];
            profitTrackerWeeks = Array.isArray(data.weeks) ? data.weeks : [];
            profitTrackerTotals = typeof data.totals === 'object' && data.totals !== null
                ? data.totals
                : profitTrackerTotals;
            renderProfitTracker();
            if (statusLabel && data.generated_at) {
                const timestamp = new Date(data.generated_at);
                statusLabel.textContent = `Updated ${timestamp.toLocaleDateString()} ${timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
            }
            profitTrackerLoaded = true;
        })
        .catch(error => {
            console.error('Error loading profit tracker:', error);
            if (showToastOnError && typeof showToast === 'function') {
                showToast('Failed to load profit tracker data', 'error');
            }
            if (tableBody) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                            Unable to load profit data at the moment.
                        </td>
                    </tr>
                `;
            }
        })
        .finally(() => {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.classList.remove('is-loading');
            }
        });
}

function renderProfitTracker() {
    const tbody = document.getElementById('profit-tracker-body');
    if (!tbody) return;

    const weeklyBody = document.getElementById('profit-tracker-weekly-body');
    const revenueEl = document.getElementById('profitTrackerTotalRevenue');
    const expenseEl = document.getElementById('profitTrackerTotalExpenses');
    const profitEl = document.getElementById('profitTrackerTotalProfit');

    const totalRevenue = Number(profitTrackerTotals.revenue || 0);
    const totalIngredient = Number(profitTrackerTotals.ingredient_cost || 0);
    const totalOther = Number(profitTrackerTotals.other_expenses || 0);
    const totalProfit = Number(profitTrackerTotals.profit || 0);

    if (revenueEl) revenueEl.textContent = formatCurrency(totalRevenue);
    if (expenseEl) expenseEl.textContent = formatCurrency(totalIngredient + totalOther);
    if (profitEl) profitEl.textContent = formatCurrency(totalProfit);

    if (!profitTrackerData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                    No profit or expense data found for the past 12 months.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = '';
    profitTrackerData.forEach(entry => {
        const revenue = Number(entry.revenue || 0);
        const ingredientCost = Number(entry.ingredient_cost || 0);
        const otherExpenses = Number(entry.other_expenses || 0);
        const profit = Number(entry.profit || (revenue - ingredientCost - otherExpenses));
        const margin = revenue > 0 ? ((profit / revenue) * 100) : 0;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${entry.label || entry.key}</td>
            <td class="text-center">${formatCurrency(revenue)}</td>
            <td class="text-center">${formatCurrency(ingredientCost)}</td>
            <td class="text-center">${formatCurrency(profit)}</td>
            <td class="text-center">${margin.toFixed(2)}%</td>
        `;
        tbody.appendChild(row);
    });

    if (weeklyBody) {
        if (!profitTrackerWeeks.length) {
            weeklyBody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                        No weekly data found.
                    </td>
                </tr>
            `;
        } else {
            weeklyBody.innerHTML = '';
            profitTrackerWeeks.forEach(entry => {
                const revenue = Number(entry.revenue || 0);
                const ingredientCost = Number(entry.ingredient_cost || 0);
                const otherExpenses = Number(entry.other_expenses || 0);
                const profit = Number(entry.profit || (revenue - ingredientCost - otherExpenses));
                const margin = revenue > 0 ? ((profit / revenue) * 100) : 0;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${entry.label || entry.key}</td>
                    <td class="text-center">${formatCurrency(revenue)}</td>
                    <td class="text-center">${formatCurrency(ingredientCost)}</td>
                    <td class="text-center">${formatCurrency(profit)}</td>
                    <td class="text-center">${margin.toFixed(2)}%</td>
                `;
                weeklyBody.appendChild(row);
            });
        }
    }
}

// Production Cost Functions
let productionCostData = [];
let productionCostProducts = [];

async function loadProductionCostProducts() {
    const select = document.getElementById('productionCostProductSelect');
    if (!select) return;
    
    try {
        const response = await fetch('db/products_getAll.php?includeInactive=1&format=payload');
        const data = await response.json();
        
        const products = Array.isArray(data?.products) ? data.products : Array.isArray(data) ? data : [];
        productionCostProducts = products;
        
        select.innerHTML = '<option value="">Select a product...</option>';
        
        if (products.length === 0) {
            // Show helpful message when no products exist
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No products available - Click "Create New Product" to add one';
            option.disabled = true;
            option.style.color = '#999';
            select.appendChild(option);
        } else {
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id || product.productID;
                option.textContent = product.name || product.productName || `Product #${product.id || product.productID}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading products:', error);
        select.innerHTML = '<option value="">Error loading products</option>';
    }
}

async function handleProductionCostProductChange() {
    const select = document.getElementById('productionCostProductSelect');
    const productID = select ? parseInt(select.value) : 0;
    
    if (!productID) {
        document.getElementById('production-cost-list').innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:30px; color:#6b7280;">
                    Please select a product to view production cost.
                </td>
            </tr>
        `;
        document.getElementById('production-cost-total').textContent = '₱0.00';
        return;
    }
    
    await loadProductionCostData(productID);
}

// Helper function to refresh production cost table if a product is currently selected
async function refreshProductionCostTableIfSelected() {
    const select = document.getElementById('productionCostProductSelect');
    if (!select) return;
    
    const productID = parseInt(select.value);
    if (productID) {
        // Reload production cost data to reflect updated inventory prices
        await loadProductionCostData(productID);
    }
}

async function loadProductionCostData(productID) {
    const tbody = document.getElementById('production-cost-list');
    const totalEl = document.getElementById('production-cost-total');
    
    if (!tbody || !productID) return;
    
    try {
        const response = await fetch(`db/production_cost_get.php?productID=${productID}`);
        
        // Check content type before parsing JSON
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response received:', text.substring(0, 200));
            throw new Error('Server returned invalid response. Please check server configuration.');
        }
        
        const data = await response.json();
        
        if (data.status === 'error') {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align:center; padding:30px; color:#dc2626;">
                        ${data.message || 'Error loading production cost data'}
                    </td>
                </tr>
            `;
            if (totalEl) totalEl.textContent = '₱0.00';
            return;
        }
        
        productionCostData = Array.isArray(data.data) ? data.data : [];
        renderProductionCostTable();
        
    } catch (error) {
        console.error('Error loading production cost:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:30px; color:#dc2626;">
                    Failed to load production cost data. ${error.message || ''}
                </td>
            </tr>
        `;
        if (totalEl) totalEl.textContent = '₱0.00';
    }
}

function renderProductionCostTable() {
    const tbody = document.getElementById('production-cost-list');
    const totalEl = document.getElementById('production-cost-total');
    
    if (!tbody) return;
    tbody.innerHTML = '';
    
    if (!productionCostData.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:30px; color:#6b7280;">
                    No ingredients found for this product. Click "Add Ingredient" to add one.
                </td>
            </tr>
        `;
        if (totalEl) totalEl.textContent = '₱0.00';
        return;
    }
    
    let totalCost = 0;
    
    productionCostData.forEach((item, index) => {
        const row = document.createElement('tr');
        const isWildcard = item.isWildcard || false;
        // Default to true only if undefined (for backward compatibility)
        // But manually added ingredients should have isFromBaseRecipe: false
        // Handle both boolean and string values
        let isFromBaseRecipe = true; // default
        if (item.isFromBaseRecipe !== undefined) {
            // Convert string "false" to boolean false
            if (item.isFromBaseRecipe === false || item.isFromBaseRecipe === 'false' || item.isFromBaseRecipe === 0) {
                isFromBaseRecipe = false;
            } else {
                isFromBaseRecipe = true;
            }
        }
        
        
        // Calculate ingredient cost if not wildcard
        let ingredientCost = item.ingredientCost || 0;
        if (!isWildcard && item.pricePerUnit && item.neededPerCup) {
            ingredientCost = item.pricePerUnit * item.neededPerCup;
        }
        
        totalCost += ingredientCost;
        
        const packSize = item.packSize || '';
        const unit = item.unit || '';
        const packPrice = item.packPrice || 0;
        const neededPerCup = item.neededPerCup !== undefined && item.neededPerCup !== '' ? item.neededPerCup : '';
        const pricePerUnit = item.pricePerUnit || 0;
        
        // Make needed per cup editable for non-wildcard items
        const neededPerCupCell = isWildcard ? 
            `<td class="text-center">-</td>` :
            `<td class="text-center">
                <input type="number" 
                       class="form-input" 
                       style="width: 80px; padding: 4px 8px; text-align: center;"
                       value="${neededPerCup}"
                       step="0.01"
                       min="0"
                       data-inventory-id="${item.inventoryID}"
                       data-product-id="${item.productID}"
                       data-price-per-unit="${pricePerUnit}"
                       onchange="updateNeededPerCup(this)">
            </td>`;
        
        row.innerHTML = `
            <td>${item.productID}</td>
            <td>${item.inventoryID || ''}</td>
            <td class="font-medium text-gray-800">${item.InventoryName || ''}</td>
            <td class="text-center">${packSize || '-'}</td>
            <td class="text-center">${unit || '-'}</td>
            <td class="text-center">${packPrice ? formatCurrency(packPrice) : '-'}</td>
            ${neededPerCupCell}
            <td class="text-center">${pricePerUnit ? formatPricePerUnit(pricePerUnit) : '-'}</td>
            <td class="text-center">
                ${isWildcard ? `
                    <input type="number" 
                           class="form-input" 
                           style="width: 100px; padding: 4px 8px; text-align: center;"
                           value="${ingredientCost.toFixed(2)}"
                           step="0.01"
                           min="0"
                           data-inventory-id="${item.inventoryID}"
                           data-product-id="${item.productID}"
                           onchange="updateWildcardIngredientCost(this)">
                ` : `
                    <span class="text-gray-800" id="ingredient-cost-${item.inventoryID}">${formatCurrency(ingredientCost)}</span>
                `}
            </td>
            <td class="text-center">
                ${!isFromBaseRecipe ? `
                    <button class="btn-secondary" 
                            style="padding: 4px 8px; font-size: 12px;"
                            onclick="removeProductionCostIngredient(${item.productID}, ${item.inventoryID})">
                        Remove
                    </button>
                ` : `
                    <!-- Base recipe ingredient - cannot be removed -->
                `}
            </td>
        `;
        
        tbody.appendChild(row);
    });
    
    if (totalEl) {
        totalEl.textContent = formatCurrency(totalCost);
    }
}

async function updateNeededPerCup(input) {
    const productID = parseInt(input.getAttribute('data-product-id'));
    const inventoryID = parseInt(input.getAttribute('data-inventory-id'));
    const neededPerCup = parseFloat(input.value);
    const pricePerUnit = parseFloat(input.getAttribute('data-price-per-unit') || 0);
    
    if (isNaN(neededPerCup) || neededPerCup < 0) {
        showToast('Invalid value for needed per cup', 'error');
        return;
    }
    
    try {
        const formData = new URLSearchParams();
        formData.append('productID', productID);
        formData.append('inventoryID', inventoryID);
        formData.append('neededPerCup', neededPerCup);
        formData.append('action', 'update_needed_per_cup');
        
        const response = await fetch('db/production_cost_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Update ingredient cost display dynamically
            const newIngredientCost = pricePerUnit * neededPerCup;
            const costElement = document.getElementById(`ingredient-cost-${inventoryID}`);
            if (costElement) {
                costElement.textContent = formatCurrency(newIngredientCost);
            }
            
            // Recalculate and update total
            await loadProductionCostData(productID);
            // Refresh costing table to reflect updated production cost
            await reloadCostingData();
            showToast('Needed per cup updated successfully', 'success');
        } else {
            showToast(data.message || 'Error updating needed per cup', 'error');
            await loadProductionCostData(productID); // Reload to revert
        }
    } catch (error) {
        console.error('Error updating needed per cup:', error);
        showToast('Error updating needed per cup', 'error');
        await loadProductionCostData(productID); // Reload to revert
    }
}

async function updateWildcardIngredientCost(input) {
    const productID = parseInt(input.getAttribute('data-product-id'));
    const inventoryID = parseInt(input.getAttribute('data-inventory-id'));
    const ingredientCost = parseFloat(input.value);
    
    if (isNaN(ingredientCost) || ingredientCost < 0) {
        showToast('Invalid ingredient cost', 'error');
        input.value = '0.00';
        return;
    }
    
    try {
        const formData = new URLSearchParams();
        formData.append('productID', productID);
        formData.append('inventoryID', inventoryID);
        formData.append('ingredientCost', ingredientCost);
        formData.append('action', 'update_ingredient_cost');
        
        const response = await fetch('db/production_cost_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // Small delay to ensure file is written before reading
            await new Promise(resolve => setTimeout(resolve, 150));
            await loadProductionCostData(productID);
            // Small additional delay to ensure production cost data is refreshed
            await new Promise(resolve => setTimeout(resolve, 50));
            // Refresh costing table to reflect updated production cost
            await reloadCostingData();
            showToast('Ingredient cost updated successfully', 'success');
        } else {
            showToast(data.message || 'Error updating ingredient cost', 'error');
            await loadProductionCostData(productID); // Reload to revert
        }
    } catch (error) {
        console.error('Error updating ingredient cost:', error);
        showToast('Error updating ingredient cost', 'error');
        await loadProductionCostData(productID); // Reload to revert
    }
}

async function removeProductionCostIngredient(productID, inventoryID) {
    if (!confirm('Are you sure you want to remove this ingredient from the production cost table?')) {
        return;
    }
    
    try {
        const formData = new URLSearchParams();
        formData.append('productID', productID);
        formData.append('inventoryID', inventoryID);
        formData.append('action', 'remove_ingredient');
        
        const response = await fetch('db/production_cost_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            await loadProductionCostData(productID);
            // Refresh costing table to reflect updated production cost
            await reloadCostingData();
            showToast('Ingredient removed successfully', 'success');
        } else {
            showToast(data.message || 'Error removing ingredient', 'error');
        }
    } catch (error) {
        console.error('Error removing ingredient:', error);
        showToast('Error removing ingredient', 'error');
    }
}

function showAddIngredientModal() {
    console.log('showAddIngredientModal called'); // Debug
    
    const select = document.getElementById('productionCostProductSelect');
    const productID = select ? parseInt(select.value) : 0;
    
    console.log('Product ID:', productID); // Debug
    
    if (!productID) {
        const message = 'Please select a product first';
        console.log('No product selected, showing message'); // Debug
        if (typeof showToast === 'function') {
            showToast(message, 'warning');
        } else {
            alert(message);
        }
        return;
    }
    
    // Create modal for adding ingredient
    let modal = document.getElementById('addIngredientModal');
    if (!modal) {
        console.log('Creating new modal'); // Debug
        modal = createAddIngredientModal();
        document.body.appendChild(modal);
    }
    
    // Populate inventory dropdown
    populateInventoryDropdown();
    
    // Store current product ID
    modal.setAttribute('data-product-id', productID);
    
    // Show modal
    console.log('Showing modal'); // Debug
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    
    setTimeout(() => {
        modal.classList.add('show');
        console.log('Modal show class added'); // Debug
    }, 10);
}

function createAddIngredientModal() {
    const modal = document.createElement('div');
    modal.id = 'addIngredientModal';
    modal.className = 'modal-overlay';
    modal.style.display = 'none';
    modal.style.visibility = 'hidden';
    modal.style.opacity = '0';
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Ingredient</h3>
                <button class="modal-close" onclick="closeModal('addIngredientModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addIngredientForm">
                    <div class="form-group">
                        <label for="addIngredientInventory" class="form-label">Select Ingredient</label>
                        <select id="addIngredientInventory" class="form-input" required>
                            <option value="">Loading ingredients...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="addIngredientNeeded" class="form-label">Needed per cup</label>
                        <input type="number" id="addIngredientNeeded" class="form-input" min="0" step="0.01" placeholder="e.g., 30" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('addIngredientModal')">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveAddIngredient()">Add Ingredient</button>
            </div>
        </div>
    `;
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal('addIngredientModal');
        }
    });
    
    return modal;
}

async function populateInventoryDropdown() {
    const select = document.getElementById('addIngredientInventory');
    if (!select) return;
    
    try {
        const response = await fetch('db/inventory_get.php');
        const data = await response.json();
        
        const inventory = Array.isArray(data) ? data : [];
        
        select.innerHTML = '<option value="">Select an ingredient...</option>';
        inventory.forEach(item => {
            const option = document.createElement('option');
            option.value = item.inventoryID;
            option.textContent = item.InventoryName || `Item #${item.inventoryID}`;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading inventory:', error);
        select.innerHTML = '<option value="">Error loading ingredients</option>';
    }
}

async function saveAddIngredient() {
    const modal = document.getElementById('addIngredientModal');
    const productID = modal ? parseInt(modal.getAttribute('data-product-id')) : 0;
    const inventoryID = parseInt(document.getElementById('addIngredientInventory')?.value || 0);
    const neededPerCup = parseFloat(document.getElementById('addIngredientNeeded')?.value || 0);
    
    if (!productID || !inventoryID || neededPerCup <= 0) {
        showToast('Please fill in all fields correctly', 'error');
        return;
    }
    
    try {
        // Get inventory item to calculate ingredient cost
        const invResponse = await fetch('db/inventory_get.php');
        const invData = await invResponse.json();
        const inventory = Array.isArray(invData) ? invData : [];
        const invItem = inventory.find(item => item.inventoryID == inventoryID);
        
        if (!invItem) {
            showToast('Inventory item not found', 'error');
            return;
        }
        
        const packPrice = Number(invItem['Cost Price'] || invItem.cost_price || 0);
        const packSize = invItem['Size'] || '';
        const sizeMatch = packSize.toString().match(/(\d+\.?\d*)/);
        const sizeValue = sizeMatch ? parseFloat(sizeMatch[1]) : 1;
        const pricePerUnit = sizeValue > 0 ? (packPrice / sizeValue) : 0;
        const ingredientCost = pricePerUnit * neededPerCup;
        
        const formData = new URLSearchParams();
        formData.append('productID', productID);
        formData.append('inventoryID', inventoryID);
        formData.append('neededPerCup', neededPerCup);
        formData.append('ingredientCost', ingredientCost);
        formData.append('action', 'add_ingredient');
        
        const response = await fetch('db/production_cost_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            showToast('Ingredient added successfully', 'success');
            closeModal('addIngredientModal');
            await loadProductionCostData(productID);
            // Refresh costing table to reflect updated production cost
            await reloadCostingData();
        } else {
            showToast(data.message || 'Error adding ingredient', 'error');
        }
    } catch (error) {
        console.error('Error adding ingredient:', error);
        showToast('Error adding ingredient', 'error');
    }
}

// Create New Product Modal for Production Cost Context
async function showCreateProductModal() {
    console.log('showCreateProductModal called');
    
    try {
        // Load categories and units
        const [categoriesResponse, unitsResponse] = await Promise.all([
            fetch('db/categories_getAll.php'),
            fetch('db/product_units_get.php')
        ]);
        
        const categories = await categoriesResponse.json();
        const units = await unitsResponse.json();
        
        if (!Array.isArray(categories) || categories.length === 0) {
            showToast('No categories available. Please add categories first.', 'warning');
            return;
        }
        
        if (!Array.isArray(units) || units.length === 0) {
            showToast('No units available. Please add units first.', 'warning');
            return;
        }
        
        // Check if modal already exists
        let modal = document.getElementById('createProductModal');
        if (!modal) {
            modal = createProductModalForProductionCost();
            document.body.appendChild(modal);
        }
        
        // Populate categories dropdown
        const categorySelect = modal.querySelector('#createProductCategory');
        if (categorySelect) {
            categorySelect.innerHTML = '<option value="">Select Category</option>' +
                categories.map(cat => `<option value="${cat.categoryID}">${cat.categoryName}</option>`).join('');
        }
        
        // Populate units dropdown - only show Ounce (oz) and Piece (pc)
        const unitSelect = modal.querySelector('#createProductUnit');
        if (unitSelect) {
            const filteredUnits = units.filter(unit => unit.unit_symbol === 'oz' || unit.unit_symbol === 'pc');
            unitSelect.innerHTML = '<option value="">Select Unit</option>' +
                filteredUnits.map(unit => `<option value="${unit.unit_id}" ${unit.unit_id === 2 ? 'selected' : ''}>${unit.unit_name} (${unit.unit_symbol || ''})</option>`).join('');
        }
        
        // Reset form (but preserve the default unit selection)
        const form = modal.querySelector('#createProductForm');
        if (form) {
            form.reset();
            // Set default unit to Ounce (oz) - unit_id 2
            if (unitSelect) {
                unitSelect.value = '2';
            }
        }
        
        // Show modal
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // Focus on product name input
        const nameInput = modal.querySelector('#createProductName');
        if (nameInput) {
            nameInput.focus();
        }
        
    } catch (error) {
        console.error('Error loading data for create product modal:', error);
        showToast('Failed to load categories or units', 'error');
    }
}

function createProductModalForProductionCost() {
    const modal = document.createElement('div');
    modal.id = 'createProductModal';
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 1000; display: none; 
        align-items: center; justify-content: center; visibility: hidden; opacity: 0;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333; font-size: 20px;">Create New Product</h3>
                <span class="close" onclick="closeCreateProductModal()" style="cursor: pointer; font-size: 28px; font-weight: bold; color: #999; line-height: 1; transition: color 0.2s;">&times;</span>
            </div>
            <form id="createProductForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="createProductName" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Product Name: <span style="color: red;">*</span></label>
                    <input type="text" id="createProductName" required 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;"
                           placeholder="Enter product name">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="createProductCategory" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Category: <span style="color: red;">*</span></label>
                    <select id="createProductCategory" required 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select Category</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="createProductUnit" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Unit: <span style="color: red;">*</span></label>
                    <select id="createProductUnit" required 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select Unit</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="createProductStatus" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Status:</label>
                    <select id="createProductStatus" 
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" onclick="closeCreateProductModal()" 
                            class="btn-secondary" 
                            style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; background: #f5f5f5; color: #333; transition: background 0.2s;">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary" 
                            style="padding: 10px 20px; background: #7f5539; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: background 0.2s;">
                        Create Product
                    </button>
                </div>
            </form>
        </div>
    `;
    
    // Add form submission handler
    const form = modal.querySelector('#createProductForm');
    if (form) {
        form.addEventListener('submit', handleCreateProductSubmit);
    }
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCreateProductModal();
        }
    });
    
    // Prevent modal content clicks from closing the modal
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    return modal;
}

function closeCreateProductModal() {
    const modal = document.getElementById('createProductModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.visibility = 'hidden';
            modal.style.opacity = '0';
        }, 300);
    }
}

async function handleCreateProductSubmit(e) {
    e.preventDefault();
    
    const productName = document.getElementById('createProductName')?.value.trim();
    const categoryID = parseInt(document.getElementById('createProductCategory')?.value || 0);
    const unitID = parseInt(document.getElementById('createProductUnit')?.value || 0);
    const isActive = parseInt(document.getElementById('createProductStatus')?.value || 1);
    
    if (!productName || !categoryID || !unitID) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    try {
        // Use products_add.php which handles units properly
        // Size and price are optional - can be added later
        const formData = new FormData();
        formData.append('productName', productName);
        formData.append('categoryID', categoryID);
        formData.append('unit_id', unitID);
        formData.append('isActive', isActive);
        formData.append('size', ''); // Empty size - can be added later
        formData.append('price', '0'); // Price 0 - can be added later
        
        const response = await fetch('db/products_add.php', {
            method: 'POST',
            body: formData
        });
        
        // Read response text once
        const responseText = await response.text();
        
        // Check if response is ok before parsing JSON
        if (!response.ok) {
            console.error('Server error response:', responseText);
            
            // Try to parse as JSON to get error message
            let errorMessage = 'Internal server error';
            try {
                const errorData = JSON.parse(responseText);
                errorMessage = errorData.message || errorData.error || errorMessage;
            } catch (e) {
                // If not JSON, use the text or a generic message
                errorMessage = responseText || 'Internal server error. Please check server logs.';
            }
            
            showToast('Error: ' + errorMessage, 'error');
            return;
        }
        
        // Parse JSON response
        let data;
        try {
            if (!responseText || responseText.trim() === '') {
                throw new Error('Empty response from server');
            }
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            showToast('Error parsing server response. Please check console for details.', 'error');
            return;
        }
        
        if (data.status === 'success' && data.productID) {
            showToast('Product created successfully!', 'success');
            closeCreateProductModal();
            
            // Reload products dropdown
            await loadProductionCostProducts();
            
            // Auto-select the newly created product
            const select = document.getElementById('productionCostProductSelect');
            if (select) {
                select.value = data.productID;
                // Trigger change event to load production cost data
                await handleProductionCostProductChange();
            }
            
            // Refresh costing table
            await reloadCostingData();
        } else {
            showToast(data.message || 'Error creating product', 'error');
        }
    } catch (error) {
        console.error('Error creating product:', error);
        showToast('Error creating product', 'error');
    }
}
