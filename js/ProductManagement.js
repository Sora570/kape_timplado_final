// Modern Product Management System
let categories = [];
let sizes = [];
let products = [];
window.units = window.units || [];

// Initialize product management
document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    loadSizes();
    loadProducts();
    setupEventListeners();
});

// Event Listeners
function setupEventListeners() {
    // Category management
    document.getElementById('addCategory')?.addEventListener('click', addCategory);
    document.getElementById('newCategory')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') addCategory();
    });

    // Size management
    document.getElementById('addSizeBtn')?.addEventListener('click', addSize);
    document.getElementById('newSizeName')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') addSize();
    });

    // Product filtering
    document.getElementById('categoryFilter')?.addEventListener('change', filterProducts);

    // Select all checkbox
    const selectAllCheckbox = document.getElementById('selectAllProducts');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAllProducts);
    }

    // Price editing event listeners
    document.addEventListener('change', handlePriceChange);
    document.addEventListener('change', handleSizeUnitChange);

    // Checkbox event listeners
    document.addEventListener('change', handleProductCheckboxChange);
}

// Load Categories
async function loadCategories(options) {
    try {
        let data;
        if (window.DataService) {
            data = await DataService.fetchCategories(options);
        } else {
            const response = await fetch('db/categories_getAll.php');
            data = await response.json();
        }
        categories = data;
        renderCategories();
        updateCategoryFilter();
    } catch (error) {
        console.error('Error loading categories:', error);
        showToast('Failed to load categories', 'error');
    }
}

// Load Sizes
async function loadSizes(options) {
    try {
        let data;
        if (window.DataService) {
            data = await DataService.fetchActiveSizes(options);
        } else {
            const response = await fetch('db/sizes_get.php');
            data = await response.json();
        }
        sizes = data;
        renderSizes();
    } catch (error) {
        console.error('Error loading sizes:', error);
        showToast('Failed to load sizes', 'error');
    }
}

// Load Products
async function loadProducts(options) {
    try {
        const requestOptions = Object.assign({ includeInactive: true }, options);
        let productsData;
        if (window.ProductService) {
            productsData = await ProductService.fetchProducts(requestOptions);
        } else {
            const response = await fetch('db/products_getAll.php?includeInactive=1&format=payload');
            productsData = await response.json();
        }

        const unitsData = await (window.DataService
            ? DataService.fetchUnits()
            : fetch('db/product_units_get.php').then(r => r.json()));

        products = Array.isArray(productsData?.products)
            ? productsData.products
            : Array.isArray(productsData)
                ? productsData
                : [];
        window.units = unitsData; // Store units globally
        renderProducts();
    } catch (error) {
        console.error('Error loading products:', error);
        showToast('Failed to load products', 'error');
    }
}

// Render Categories
function renderCategories() {
    const container = document.getElementById('categoryList');
    if (!container) return;

    if (categories.length === 0) {
        container.innerHTML = '<div class="empty-state">No categories found</div>';
        return;
    }

    container.innerHTML = categories.map(cat => `
        <div class="list-item">
            <span class="item-name">${cat.categoryName}</span>
            <div class="item-actions">
                <button class="btn-small btn-primary" onclick="editCategory(${cat.categoryID})">Edit</button>
                <button class="btn-small btn-danger" onclick="deleteCategory(${cat.categoryID})">Delete</button>
            </div>
        </div>
    `).join('');
}

// Render Sizes
function renderSizes() {
    const container = document.getElementById('sizeList');
    if (!container) return;

    if (sizes.length === 0) {
        container.innerHTML = '<div class="empty-state">No sizes found</div>';
        return;
    }

    container.innerHTML = sizes.map(size => `
        <div class="list-item">
            <span class="item-name">${size.sizeName.replace('oz', '').trim()}</span>
            <div class="item-actions">
                <button class="btn-small btn-primary" onclick="editSize(${size.sizeID})">Edit</button>
                <button class="btn-small btn-danger" onclick="deleteSize(${size.sizeID})">Delete</button>
            </div>
        </div>
    `).join('');
}

// Render Products
function renderProducts() {
    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;

    const selectAllCheckbox = document.getElementById('selectAllProducts');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }

    const sortedProducts = [...products].sort((a, b) => {
        const categoryA = (a.categoryName || '').toLowerCase();
        const categoryB = (b.categoryName || '').toLowerCase();
        if (categoryA !== categoryB) {
            return categoryA.localeCompare(categoryB);
        }
        return Number(a.productID) - Number(b.productID);
    });

    if (sortedProducts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">
                    <div class="empty-state">
                        <div style="font-size: 48px; margin-bottom: 12px;">📦</div>
                        No products found
                    </div>
                </td>
            </tr>
        `;
        updateMassDeleteButtonVisibility();
        return;
    }

    const defaultSizeEntry = sizes.find(size => /16/.test(size.sizeName));
    const defaultSizeId = defaultSizeEntry ? String(defaultSizeEntry.sizeID) : '';
    const defaultUnitEntry = window.units?.find(unit => (unit.unit_symbol || '').toLowerCase() === 'oz');
    const defaultUnitId = defaultUnitEntry ? String(defaultUnitEntry.unit_id) : '';

    tbody.innerHTML = sortedProducts.map(product => {
        // Restore saved selections from localStorage
        const savedSize = localStorage.getItem(`product_${product.productID}_size`) || '';
        const savedUnit = localStorage.getItem(`product_${product.productID}_unit`) || '';
        const selectedSize = savedSize || defaultSizeId;
        const selectedUnit = savedUnit || defaultUnitId;

        // Format created_at to military time
        const formatToMilitaryTime = (dateStr) => {
            if (!dateStr || dateStr === 'N/A') return 'N/A';
            const date = new Date(dateStr);
            if (isNaN(date)) return dateStr;
            return date.toISOString().slice(0, 19).replace('T', ' ');
        };

        return `
            <tr data-product-id="${product.productID}" class="product-row">
                <td>
                    <div class="product-info">
                        <strong>${product.productName}</strong>
                        <small class="text-muted">ID: ${product.productID}</small>
                    </div>
                </td>
                <td>${product.categoryName || 'Unknown'}</td>
                <td>
                    <select class="size-dropdown" data-product-id="${product.productID}" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select Size</option>
                        ${sizes.map(size => `<option value="${size.sizeID}" ${selectedSize == size.sizeID ? 'selected' : ''}>${size.sizeName.replace('oz', '').trim()}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="unit-dropdown" data-product-id="${product.productID}" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select Unit</option>
                        ${[...new Map(window.units.filter(unit => unit.unit_symbol === 'oz' || unit.unit_symbol === 'pc').map(unit => [unit.unit_symbol, unit])).values()].map(unit => `<option value="${unit.unit_id}" ${selectedUnit == unit.unit_id ? 'selected' : ''}>${unit.unit_name} (${unit.unit_symbol})</option>`).join('')}
                    </select>
                </td>
                <td>
                    <div style="position: relative; display: inline-block; width: 100%;">
                        <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #666; font-weight: bold; z-index: 1;">₱</span>
                        <input type="number" class="price-input" data-product-id="${product.productID}" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 4px 4px 4px 20px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                </td>
                <td>
                    <span class="status-badge ${product.isActive ? 'active' : 'inactive'}">
                        ${product.isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${formatToMilitaryTime(product.created_at)}</td>
                <td>
                    <input type="checkbox" class="product-checkbox" data-product-id="${product.productID}" style="text-align: center;">
                </td>
            </tr>
        `;
    }).join('');

    const rows = tbody.querySelectorAll('tr[data-product-id]');
    rows.forEach(row => {
        row.addEventListener('click', (event) => {
            if (
                event.target.closest('input') ||
                event.target.closest('select') ||
                event.target.closest('button') ||
                event.target.closest('a') ||
                event.target.closest('textarea') ||
                event.target.closest('label')
            ) {
                return;
            }
            editProduct(row.dataset.productId);
        });
    });

    // After rendering, trigger change events for selections (saved or defaults) to fetch prices
    sortedProducts.forEach(product => {
        const row = document.querySelector(`tr[data-product-id="${product.productID}"]`);
        if (!row) return;
        const sizeSelect = row.querySelector('.size-dropdown');
        const unitSelect = row.querySelector('.unit-dropdown');
        if (sizeSelect?.value && unitSelect?.value) {
            handleSizeUnitChange({ target: sizeSelect });
        }
    });

    updateMassDeleteButtonVisibility();
}

// Add Category
async function addCategory() {
    const input = document.getElementById('newCategory');
    const name = input.value.trim();
    
    if (!name) {
        showToast('Please enter a category name', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('categoryName', name);

        const response = await fetch('db/categories_add.php', {
            method: 'POST',
            body: formData
        });

        // Get response text first (can only read once)
        const responseText = await response.text();
        
        // Check if response is OK
        if (!response.ok) {
            console.error('Server error response:', responseText);
            showToast('Server error: ' + response.status, 'error');
            return;
        }

        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            // If JSON parsing fails, show the actual response
            console.error('Invalid JSON response:', responseText);
            showToast('Server returned invalid response. Please check console for details.', 'error');
            return;
        }
        
        if (result.status === 'success') {
            showToast('Category added successfully', 'success');
            input.value = ''; // Clear input
            closeAddCategoryModal();
            if (window.DataService) {
                DataService.invalidateCategories();
            }
            loadCategories({ force: true });
        } else {
            showToast(result.message || 'Failed to add category', 'error');
        }
    } catch (error) {
        console.error('Error adding category:', error);
        showToast('Failed to add category: ' + error.message, 'error');
    }
}

// Add Size
async function addSize() {
    const nameInput = document.getElementById('newSizeName');
    const name = nameInput.value.trim();

    if (!name) {
        showToast('Please enter a size value', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('sizeName', name);
        formData.append('defaultPrice', 0); // Default price is 0

        const response = await fetch('db/sizes_add.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            showToast('Size added successfully', 'success');
            if (window.DataService) {
                DataService.invalidateSizes();
            }
            loadSizes({ force: true }); // Reload sizes in main page
        } else {
            showToast(result.message || 'Failed to add size', 'error');
        }
    } catch (error) {
        console.error('Error adding size:', error);
        showToast('Failed to add size', 'error');
    }
}

// Update Category Filter
function updateCategoryFilter() {
    const filter = document.getElementById('categoryFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">All Categories</option>' +
        categories.map(cat => `<option value="${cat.categoryID}">${cat.categoryName}</option>`).join('');
}

// Filter Products
function filterProducts() {
    const filter = document.getElementById('categoryFilter');
    const categoryId = filter ? filter.value : '';
    const selectAllCheckbox = document.getElementById('selectAllProducts');

    if (!categoryId) {
        renderProducts();
        return;
    }

    const filteredProducts = products.filter(product => product.categoryID == categoryId);
    const sortedFilteredProducts = [...filteredProducts].sort((a, b) => {
        const categoryA = (a.categoryName || '').toLowerCase();
        const categoryB = (b.categoryName || '').toLowerCase();
        if (categoryA !== categoryB) {
            return categoryA.localeCompare(categoryB);
        }
        return Number(a.productID) - Number(b.productID);
    });
    const tbody = document.getElementById('productsTableBody');

    if (!tbody) return;

    if (sortedFilteredProducts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">
                    <div class="empty-state">No products in this category</div>
                </td>
            </tr>
        `;
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
        updateMassDeleteButtonVisibility();
        return;
    }

    // Render filtered products
    tbody.innerHTML = sortedFilteredProducts.map(product => {
        // Restore saved selections from localStorage
        const savedSize = localStorage.getItem(`product_${product.productID}_size`) || '';
        const savedUnit = localStorage.getItem(`product_${product.productID}_unit`) || '';

        // Format created_at to military time
        const formatToMilitaryTime = (dateStr) => {
            if (!dateStr || dateStr === 'N/A') return 'N/A';
            const date = new Date(dateStr);
            if (isNaN(date)) return dateStr;
            return date.toISOString().slice(0, 19).replace('T', ' ');
        };

        return `
            <tr data-product-id="${product.productID}" class="product-row">
                <td>
                    <div class="product-info">
                        <strong>${product.productName}</strong>
                        <small class="text-muted">ID: ${product.productID}</small>
                    </div>
                </td>
                <td>${product.categoryName || 'Unknown'}</td>
                <td>
                    <select class="size-dropdown" data-product-id="${product.productID}" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select Size</option>
                        ${sizes.map(size => `<option value="${size.sizeID}" ${savedSize == size.sizeID ? 'selected' : ''}>${size.sizeName.replace('oz', '').trim()}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="unit-dropdown" data-product-id="${product.productID}" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select Unit</option>
                        ${[...new Map(window.units.filter(unit => unit.unit_symbol === 'oz' || unit.unit_symbol === 'pc').map(unit => [unit.unit_symbol, unit])).values()].map(unit => `<option value="${unit.unit_id}" ${savedUnit == unit.unit_id ? 'selected' : ''}>${unit.unit_name} (${unit.unit_symbol})</option>`).join('')}
                    </select>
                </td>
                <td>
                    <div style="position: relative; display: inline-block; width: 100%;">
                        <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #666; font-weight: bold; z-index: 1;">₱</span>
                        <input type="number" class="price-input" data-product-id="${product.productID}" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 4px 4px 4px 20px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    </div>
                </td>
                <td>
                    <span class="status-badge ${product.isActive ? 'active' : 'inactive'}">
                        ${product.isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${formatToMilitaryTime(product.created_at)}</td>
                <td>
                    <input type="checkbox" class="product-checkbox" data-product-id="${product.productID}" style="text-align: center;">
                </td>
            </tr>
        `;
    }).join('');

    const rows = tbody.querySelectorAll('tr[data-product-id]');
    rows.forEach(row => {
        row.addEventListener('click', (event) => {
            if (
                event.target.closest('input') ||
                event.target.closest('select') ||
                event.target.closest('button') ||
                event.target.closest('a') ||
                event.target.closest('textarea') ||
                event.target.closest('label')
            ) {
                return;
            }
            editProduct(row.dataset.productId);
        });
    });

    // After rendering, trigger change events for selections (saved or defaults) to fetch prices
    sortedFilteredProducts.forEach(product => {
        const row = document.querySelector(`tr[data-product-id="${product.productID}"]`);
        if (!row) return;
        const sizeSelect = row.querySelector('.size-dropdown');
        const unitSelect = row.querySelector('.unit-dropdown');
        if (sizeSelect?.value && unitSelect?.value) {
            handleSizeUnitChange({ target: sizeSelect });
        }
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }
    updateMassDeleteButtonVisibility();
}

// Modal Functions
function createCategoryModal() {
    const modal = document.createElement('div');
    modal.id = 'addCategoryModal';
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;
    `;
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 400px; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Add New Category</h3>
                <span class="close" onclick="closeAddCategoryModal()" style="cursor: pointer; font-size: 24px; font-weight: bold;">&times;</span>
            </div>
            <form id="categoryForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="newCategory">Category Name:</label>
                    <input type="text" id="newCategory" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeAddCategoryModal()" class="btn-secondary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Add Category</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function showAddCategoryModal() {
    let modal = document.getElementById('addCategoryModal');
    if (!modal) {
        modal = createCategoryModal();
    }
    modal.style.display = 'flex';

    // Focus on category input
    const input = document.getElementById('newCategory');
    if (input) input.focus();

    // Add submit handler if not already added
    const form = modal.querySelector('#categoryForm');
    if (form && !form.dataset.submittedAdded) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            addCategory();
        });
        form.dataset.submittedAdded = 'true';
    }
}

function closeAddCategoryModal() {
    const modal = document.getElementById('addCategoryModal');
    if (modal) {
        modal.style.display = 'none';
        // Clear input
        const input = document.getElementById('newCategory');
        if (input) input.value = '';
    }
}

function showAddSizeModal() {
    // Create modal if it doesn't exist
    if (!document.getElementById('addSizeModal')) {
        createSizeModal();
    }

    // Show modal
    document.getElementById('addSizeModal').style.display = 'flex';

    // Load sizes in modal
    loadSizesForModal();

    // Focus on size name input
    const input = document.getElementById('newSizeName');
    if (input) input.focus();
}

async function showAddProductModal() {
    if (window.USER_ROLE !== 'admin') {
        showToast('Only administrators can add products', 'warning');
        return;
    }

    try {
        // Fetch categories and units fresh for the modal
        const [categoriesResponse, unitsResponse] = await Promise.all([
            fetch('db/categories_getAll.php'),
            fetch('db/product_units_get.php')
        ]);
        const categories = await categoriesResponse.json();
        const units = await unitsResponse.json();

        if (categories.length === 0) {
            showToast('No categories available. Please add categories first.', 'warning');
            return;
        }

        // Check if modal already exists
        let modal = document.getElementById('addProductModal');
        if (!modal) {
            // Create modal
            modal = document.createElement('div');
            modal.id = 'addProductModal';
            modal.className = 'modal';
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;
            `;
            modal.innerHTML = `
                <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>Add New Product</h3>
                        <span class="close" onclick="closeProductModal()" style="cursor: pointer; font-size: 24px; font-weight: bold;">&times;</span>
                    </div>
                    <form id="productForm">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="productName">Product Name:</label>
                            <input type="text" id="productName" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="productCategory">Category:</label>
                            <select id="productCategory" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Select Category</option>
                                ${categories.map(cat => `<option value="${cat.categoryID}">${cat.categoryName}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-row" style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="productSize">Size (optional):</label>
                                <input type="text" id="productSize" placeholder="e.g., Medium" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="productUnit">Unit:</label>
                                <select id="productUnit" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">Select Unit</option>
                                    ${units.map(unit => `<option value="${unit.unit_id}">${unit.unit_name} (${unit.unit_symbol})</option>`).join('')}
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="productPrice">Price (optional):</label>
                            <input type="number" id="productPrice" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeProductModal()" class="btn-secondary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Add Product</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);

            // Add submit handler only once
            document.getElementById('productForm').addEventListener('submit', handleProductSubmitPM);
        } else {
            // Update dropdowns if modal already exists
            const categorySelect = modal.querySelector('#productCategory');
            const unitSelect = modal.querySelector('#productUnit');
            if (categorySelect) {
                categorySelect.innerHTML = '<option value="">Select Category</option>' +
                    categories.map(cat => `<option value="${cat.categoryID}">${cat.categoryName}</option>`).join('');
            }
            if (unitSelect) {
                unitSelect.innerHTML = '<option value="">Select Unit</option>' +
                    units.map(unit => `<option value="${unit.unit_id}">${unit.unit_name} (${unit.unit_symbol})</option>`).join('');
            }
        }

        modal.style.display = 'flex';
    } catch (err) {
        console.error('Error loading data for modal:', err);
        showToast('Failed to load categories', 'error');
    }
}

// Separate submit handler function for ProductManagement.js
async function handleProductSubmitPM(e) {
    e.preventDefault();
    const name = document.getElementById('productName').value.trim();
    const categoryID = document.getElementById('productCategory').value;
    const size = document.getElementById('productSize').value.trim();
    const unitID = document.getElementById('productUnit').value;
    const price = parseFloat(document.getElementById('productPrice').value) || 0;

    if (!name || !categoryID || !unitID) {
        showToast('Please enter name, select category, and unit', 'error');
        return;
    }

    const submitButton = e.target.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';
    }

    const success = await addAdminProduct(name, parseInt(categoryID, 10), size, parseInt(unitID, 10), price);

    if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Add Product';
    }

    if (success) {
        closeProductModal();
    }
}

async function addAdminProduct(name, categoryID, sizeLabel, unitID, price) {
    const formData = new FormData();
    formData.append('productName', name);
    formData.append('categoryID', categoryID);
    if (sizeLabel) {
        formData.append('size', sizeLabel);
    }
    formData.append('unit_id', unitID);
    formData.append('price', price);
    formData.append('isActive', '1');

    try {
        const response = await fetch('db/products_add.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        if (!response.ok) {
            console.error('Add product failed:', text);
            showToast('Server error while adding product', 'error');
            return false;
        }

        let result;
        try {
            result = JSON.parse(text);
        } catch (err) {
            console.error('Invalid JSON from products_add:', text);
            showToast('Invalid response from server', 'error');
            return false;
        }

        if (result.status === 'success') {
            showToast('Product added successfully', 'success');
            await loadProducts({ force: true });
            return true;
        }

        showToast(result.message || 'Failed to add product', 'error');
        return false;
    } catch (error) {
        console.error('Error adding product:', error);
        showToast('Failed to add product: ' + error.message, 'error');
        return false;
    }
}

function createProductModal() {
    const modal = document.createElement('div');
    modal.id = 'addProductModal';
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;
    `;
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 400px; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Add New Product</h3>
                <span class="close" onclick="closeProductModal()" style="cursor: pointer; font-size: 24px; font-weight: bold;">&times;</span>
            </div>
            <form id="productForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="productName">Product Name:</label>
                    <input type="text" id="productName" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="productCategory">Category:</label>
                    <select id="productCategory" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Select Category</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="productImage">Image URL (optional):</label>
                    <input type="url" id="productImage" placeholder="https://example.com/image.jpg" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeProductModal()" class="btn-secondary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Add Product</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function closeProductModal() {
    const modal = document.getElementById('addProductModal');
    if (modal) modal.style.display = 'none';
}

// Placeholder functions for future implementation
function editCategory(id) {
    showToast('Edit category functionality coming soon', 'info');
}

function deleteCategory(id) {
    if (confirm('Are you sure you want to delete this category?')) {
        showToast('Delete category functionality coming soon', 'info');
    }
}

function editSize(id) {
    showToast('Edit size functionality coming soon', 'info');
}

function deleteSize(id) {
    if (confirm('Are you sure you want to delete this size?')) {
        showToast('Delete size functionality coming soon', 'info');
    }
}

async function editProduct(productID) {
    if (window.USER_ROLE !== 'admin') {
        showToast('Only administrators can edit products', 'warning');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('productID', productID);
        const response = await fetch('db/products_getOne.php', {
            method: 'POST',
            body: formData
        });
        const product = await response.json();
        if (!product || !product.productID) {
            showToast('Product not found', 'error');
            return;
        }
        showEditProductModal(product);
    } catch (error) {
        console.error('Error loading product details:', error);
        showToast('Failed to load product details', 'error');
    }
}

function showEditProductModal(product) {
    let modal = document.getElementById('editProductModal');
    if (modal) {
        modal.remove();
    }

    modal = document.createElement('div');
    modal.id = 'editProductModal';
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 1000; display: flex;
        align-items: center; justify-content: center; padding: 20px;
    `;

    // Try to honor the user’s last selection in the main table
    const savedSizeId = localStorage.getItem(`product_${product.productID}_size`) || '';
    const savedUnitId = localStorage.getItem(`product_${product.productID}_unit`) || '';

    // Fallback: first size with a numeric price, or first size, or empty
    const pricedSize = Array.isArray(product.sizes)
        ? product.sizes.find(s => typeof s.price === 'number')
        : null;
    const firstSize = Array.isArray(product.sizes) && product.sizes.length ? product.sizes[0] : null;

    const selectedSizeId = savedSizeId || (pricedSize ? String(pricedSize.sizeID) : (firstSize ? String(firstSize.sizeID) : ''));
    const defaultPrice = (() => {
        if (!Array.isArray(product.sizes)) return '';
        const match = product.sizes.find(s => String(s.sizeID) === selectedSizeId) || pricedSize || firstSize;
        return match && typeof match.price === 'number' ? match.price : '';
    })();

    const sizeOptions = sizes.map(size => `
        <option value="${size.sizeID}" ${String(size.sizeID) === selectedSizeId ? 'selected' : ''}>
            ${size.sizeName.replace('oz', '').trim()}
        </option>
    `).join('');

    // For unit, prefer the last used unit for this product, otherwise keep existing dropdown default
    const unitOptions = (window.units || []).map(unit => `
        <option value="${unit.unit_id}" ${savedUnitId && String(unit.unit_id) === savedUnitId ? 'selected' : ''}>
            ${unit.unit_name} (${unit.unit_symbol})
        </option>
    `).join('');

    modal.innerHTML = `
        <div class="modal-content" style="background:white;padding:24px;border-radius:8px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3>Edit Product</h3>
                <span class="close" style="cursor:pointer;font-size:24px;font-weight:bold;" onclick="closeEditProductModal()">&times;</span>
            </div>
            <form id="editProductForm" data-product-id="${product.productID}">
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="editProductName" style="display:block;margin-bottom:4px;font-weight:600;">Product Name:</label>
                    <input type="text" id="editProductName" value="${product.productName || ''}" required style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="editProductCategory" style="display:block;margin-bottom:4px;font-weight:600;">Category:</label>
                    <select id="editProductCategory" required style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                        <option value="">Select Category</option>
                        ${categories.map(cat => `<option value="${cat.categoryID}" ${cat.categoryID === product.categoryID ? 'selected' : ''}>${cat.categoryName}</option>`).join('')}
                    </select>
                </div>
                <div class="form-row" style="display:flex;gap:12px;margin-bottom:16px;">
                    <div class="form-group" style="flex:1;">
                        <label for="editProductSize" style="display:block;margin-bottom:4px;font-weight:600;">Size:</label>
                        <select id="editProductSize" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                            <option value="">Select Size</option>
                            ${sizeOptions}
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label for="editProductUnit" style="display:block;margin-bottom:4px;font-weight:600;">Unit:</label>
                        <select id="editProductUnit" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                            <option value="">Select Unit</option>
                            ${unitOptions}
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="editProductPrice" style="display:block;margin-bottom:4px;font-weight:600;">Price (₱):</label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#666;font-weight:600;">₱</span>
                        <input type="number" id="editProductPrice" min="0" step="0.01"
                               value="${defaultPrice}"
                               style="width:100%;padding:10px 12px 10px 28px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label for="editProductStatus" style="display:block;margin-bottom:4px;font-weight:600;">Status:</label>
                    <select id="editProductStatus" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                        <option value="1" ${product.isActive ? 'selected' : ''}>Active</option>
                        <option value="0" ${!product.isActive ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
                    <button type="button" class="btn-secondary" onclick="closeEditProductModal()" style="padding:8px 18px;border-radius:6px;border:1px solid #ddd;background:#fff;font-weight:500;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding:8px 20px;border-radius:6px;border:none;background:#0069ff;color:#fff;font-weight:600;">Update Product</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(modal);
    const form = modal.querySelector('#editProductForm');
    form.addEventListener('submit', handleEditProductSubmit);
}

async function handleEditProductSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const productID = form.dataset.productId;
    const name = document.getElementById('editProductName').value.trim();
    const categoryID = document.getElementById('editProductCategory').value;
    const statusSelect = document.getElementById('editProductStatus');
    const isActive = statusSelect ? statusSelect.value === '1' : true;

    const priceInput = document.getElementById('editProductPrice');
    const sizeSelect = document.getElementById('editProductSize');
    const sizeIdForPrice = sizeSelect?.value || '';
    const priceValue = priceInput && priceInput.value !== '' ? priceInput.value : null;

    if (!name || !categoryID) {
        showToast('Please provide product name and category', 'error');
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';
    }

    const formData = new FormData();
    formData.append('productID', productID);
    formData.append('productName', name);
    formData.append('categoryID', categoryID);
    if (isActive) {
        formData.append('isActive', '1');
    }
    if (sizeIdForPrice && priceValue !== null) {
        formData.append(`size_${sizeIdForPrice}`, priceValue);
    }

    try {
        const response = await fetch('db/products_update.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast('Product updated successfully', 'success');
            closeEditProductModal();
            await loadProducts({ force: true });
        } else {
            showToast(result.message || 'Failed to update product', 'error');
        }
    } catch (error) {
        console.error('Error updating product:', error);
        showToast('Failed to update product', 'error');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Save Changes';
        }
    }
}

function closeEditProductModal() {
    const modal = document.getElementById('editProductModal');
    if (modal) {
        modal.remove();
    }
}

async function deleteProduct(id) {
    if (!id) return;
    if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('productID', id);
        const response = await fetch('db/products_delete.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showToast('Product deleted successfully', 'success');
            // Remove from local state for immediate feedback
            products = products.filter(p => String(p.productID) !== String(id));
            renderProducts();
            // Refresh from server to stay in sync with other clients
            await loadProducts({ force: true });
        } else {
            showToast(result.message || 'Failed to delete product', 'error');
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        showToast('Failed to delete product', 'error');
    }
}

// Handle size/unit change to fetch existing price
async function handleSizeUnitChange(e) {
    if (!e.target.classList.contains('size-dropdown') && !e.target.classList.contains('unit-dropdown')) return;

    const productId = e.target.dataset.productId;
    const row = e.target.closest('tr');
    const sizeSelect = row.querySelector('.size-dropdown');
    const unitSelect = row.querySelector('.unit-dropdown');
    const priceInput = row.querySelector('.price-input');

    const sizeId = sizeSelect.value;
    const unitId = unitSelect.value;

    // Save selections to localStorage
    localStorage.setItem(`product_${productId}_size`, sizeId);
    localStorage.setItem(`product_${productId}_unit`, unitId);

    if (!sizeId || !unitId) {
        priceInput.value = '';
        return;
    }

    try {
        const response = await fetch(`db/product_prices_get.php?productID=${productId}&sizeID=${sizeId}&unit_id=${unitId}`);
        const data = await response.json();
        priceInput.value = data.price !== null ? data.price : '';
    } catch (error) {
        console.error('Error fetching price:', error);
        showToast('Failed to fetch price', 'error');
    }
}

// Handle price input change to save
async function handlePriceChange(e) {
    if (!e.target.classList.contains('price-input')) return;

    const productId = e.target.dataset.productId;
    const row = e.target.closest('tr');
    const sizeSelect = row.querySelector('.size-dropdown');
    const unitSelect = row.querySelector('.unit-dropdown');
    const price = parseFloat(e.target.value) || 0;

    const sizeId = sizeSelect.value;
    const unitId = unitSelect.value;

    if (!sizeId || !unitId) {
        showToast('Please select size and unit first', 'warning');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('productID', productId);
        formData.append(`price_${sizeId}_${unitId}`, price);

        const response = await fetch('db/product_prices_set.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.status === 'success') {
            showToast('Price saved successfully', 'success');
            // Refresh costing table if it exists on the page
            await refreshCostingTableIfExists();
        } else {
            showToast(result.message || 'Failed to save price', 'error');
        }
    } catch (error) {
        console.error('Error saving price:', error);
        showToast('Failed to save price', 'error');
    }
}

// Helper function to refresh costing table if it exists on the current page
async function refreshCostingTableIfExists() {
    // Small delay to ensure database update is committed
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // Check if we're on the inventory page with costing table
    const costingTable = document.getElementById('inventory-costing-list');
    if (!costingTable) {
        // Costing table doesn't exist on this page, nothing to refresh
        return;
    }
    
    // Try to use reloadCostingData from Inventory.js if available
    if (typeof reloadCostingData === 'function') {
        await reloadCostingData();
        return;
    }
    
    // Fallback: fetch and update costing data directly
    try {
        const cacheBuster = new Date().getTime();
        const response = await fetch(`db/inventory_costing.php?t=${cacheBuster}`, {
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        });
        if (response.ok) {
            const costingPayload = await response.json();
            // Update global productCostingData if it exists
            if (typeof window !== 'undefined' && window.productCostingData !== undefined) {
                window.productCostingData = Array.isArray(costingPayload) ? costingPayload : [];
            }
            // Call renderCostingTable if available
            if (typeof renderCostingTable === 'function') {
                renderCostingTable();
            }
        }
    } catch (error) {
        console.error('Error refreshing costing data:', error);
    }
}

// Handle select all checkbox
function handleSelectAllProducts(e) {
    if (e.target.id !== 'selectAllProducts') return;
    const shouldCheck = e.target.checked;
    e.target.indeterminate = false;
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = shouldCheck;
    });
    updateMassDeleteButtonVisibility();
}

// Handle product checkbox changes
function handleProductCheckboxChange(e) {
    if (!e.target.classList.contains('product-checkbox')) return;
    updateMassDeleteButtonVisibility();
}

// Create mass delete button
function createMassDeleteButton() {
    const anchorBtn = document.getElementById('addProductBtn');
    if (!anchorBtn || document.getElementById('massDeleteBtn')) return;

    const massDeleteBtn = document.createElement('button');
    massDeleteBtn.id = 'massDeleteBtn';
    massDeleteBtn.className = 'btn-danger';
    massDeleteBtn.style.cssText = `
        margin-left: 10px;
        padding: 8px 16px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: inline-block;
    `;
    massDeleteBtn.type = 'button';
    massDeleteBtn.innerHTML = '<ion-icon name="trash-outline"></ion-icon> Delete Selected';
    massDeleteBtn.onclick = massDeleteProducts;

    anchorBtn.parentNode.insertBefore(massDeleteBtn, anchorBtn.nextSibling);
}

// Update select-all state and mass delete visibility
function updateMassDeleteButtonVisibility() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    const massDeleteBtn = document.getElementById('massDeleteBtn');

    const selectAllCheckbox = document.getElementById('selectAllProducts');
    if (selectAllCheckbox) {
        if (checkboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else {
            const allChecked = checkedBoxes.length === checkboxes.length && checkedBoxes.length > 0;
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
        }
    }

    if (checkedBoxes.length > 0) {
        if (!massDeleteBtn) {
            createMassDeleteButton();
        } else {
            massDeleteBtn.style.display = 'inline-block';
        }
    } else if (massDeleteBtn) {
        massDeleteBtn.style.display = 'none';
    }
}

// Mass delete selected products
async function massDeleteProducts() {
    const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
    if (checkedBoxes.length === 0) {
        showToast('No products selected', 'warning');
        return;
    }

    const productIds = Array.from(checkedBoxes).map(cb => cb.dataset.productId);
    const productNames = productIds.map(id => {
        const product = products.find(p => p.productID == id);
        return product ? product.productName : 'Unknown';
    });

    const confirmMessage = `Are you sure you want to delete ${productIds.length} selected product(s)?\n\n${productNames.join('\n')}`;
    if (!confirm(confirmMessage)) return;

    let successCount = 0;
    let errorCount = 0;

    for (const productId of productIds) {
        try {
            const formData = new FormData();
            formData.append('productID', productId);

            const response = await fetch('db/products_delete.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                successCount++;
            } else {
                errorCount++;
                console.error('Error deleting product', productId, data.message);
            }
        } catch (error) {
            errorCount++;
            console.error('Error deleting product', productId, error);
        }
    }

    // Reload products from server so UI reflects deletions without manual refresh
    await loadProducts({ force: true });

    // Hide mass delete button
    const massDeleteBtn = document.getElementById('massDeleteBtn');
    if (massDeleteBtn) {
        massDeleteBtn.style.display = 'none';
    }
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateMassDeleteButtonVisibility();

    // Show result message
    if (errorCount === 0) {
        showToast(`Successfully deleted ${successCount} product(s)`, 'success');
    } else if (successCount === 0) {
        showToast('Failed to delete selected products', 'error');
    } else {
        showToast(`Deleted ${successCount} product(s), ${errorCount} failed`, 'warning');
    }
}

// Create Size Modal
function createSizeModal() {
    const modal = document.createElement('div');
    modal.id = 'addSizeModal';
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center;
    `;
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Add Size</h3>
                <span class="close" onclick="closeAddSizeModal()" style="cursor: pointer; font-size: 24px; font-weight: bold;">&times;</span>
            </div>

            <!-- Add Size Form -->
            <form id="sizeForm" style="margin-bottom: 20px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="newSizeName">Size Value:</label>
                    <input type="text" id="newSizeName" placeholder="Enter size" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Add Size</button>
                </div>
            </form>

            <!-- Sizes List -->
            <div style="border-top: 1px solid #eee; padding-top: 20px;">
                <h4>Added Sizes:</h4>
                <div id="modalSizeList" style="max-height: 200px; overflow-y: auto;">
                    <!-- Sizes will be loaded here -->
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Add submit handler
    const form = modal.querySelector('#sizeForm');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await addSize();
        // Clear input after adding
        document.getElementById('newSizeName').value = '';
        // Reload sizes in modal
        loadSizesForModal();
    });

    return modal;
}

function closeAddSizeModal() {
    const modal = document.getElementById('addSizeModal');
    if (modal) {
        modal.style.display = 'none';
        // Clear input
        const nameInput = document.getElementById('newSizeName');
        if (nameInput) nameInput.value = '';
    }
}

// Load sizes for modal display
async function loadSizesForModal(options) {
    try {
        let modalSizes;
        if (window.DataService) {
            modalSizes = await DataService.fetchActiveSizes(options);
        } else {
            const response = await fetch('db/sizes_get.php');
            modalSizes = await response.json();
        }

        const container = document.getElementById('modalSizeList');
        if (!container) return;

        if (!modalSizes.length) {
            container.innerHTML = '<div style="color: #666; font-style: italic;">No sizes added yet</div>';
            return;
        }

        container.innerHTML = modalSizes.map(size => `
            <div style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span>${size.sizeName.replace('oz', '').trim()}</span>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading sizes for modal:', error);
        const container = document.getElementById('modalSizeList');
        if (container) {
            container.innerHTML = '<div style="color: #e74c3c;">Failed to load sizes</div>';
        }
    }
}

// Expose functions globally
window.showAddCategoryModal = showAddCategoryModal;
window.closeAddCategoryModal = closeAddCategoryModal;
window.showAddSizeModal = showAddSizeModal;
window.closeAddSizeModal = closeAddSizeModal;
window.showAddProductModal = showAddProductModal;
window.closeProductModal = closeProductModal;
window.massDeleteProducts = massDeleteProducts;







