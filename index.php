<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    header("Location: loginRegister.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kape Timplado's</title>
    <link rel="icon" href="assest/icon/icons8-coffee-shop-64.png">
    <link rel="stylesheet" href="css/Dashboard.css">
    <link rel="stylesheet" href="css/Products.css">
    <link rel="stylesheet" href="css/Inventory.css">

    <link rel="stylesheet" href="css/settings.css">
    <link rel="stylesheet" href="css/Transactions.css">
    <link rel="stylesheet" href="css/Employees.css">

</head>
<body class="<?php echo ($_SESSION['role'] === 'cashier') ? 'cashier-mode' : ''; ?>">
    <div id="container" class="container">
        <!-- ------------------------------------ Navgation Side Bar ------------------------------------ -->
        <div id="navigation" class="navigation">
            <ul>
                <li>
                    <a href="#">
                        <span class="icon"><img src="assest/image/logo.png" class="logo"></span>
                        <span class="title" style="font-size: 1.5em;font-weight: 500; margin-top: 15px;"><?php echo ($_SESSION['role'] === 'cashier') ? 'Cashier - Kape Timplado\'s' : 'Kape Timplado\'s'; ?></span>
                    </a>
                </li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li class="hovered">
					<a href="#" id="Dashboard-button">
						<span class="icon"><ion-icon name="home-outline"></ion-icon></span>
						<span class="title">Dashboard</span>
					</a>
				</li>
                <li>
                    <a href="#" id="ProfitTrackerForm-button">
                        <span class="icon"><ion-icon name="trending-up-outline"></ion-icon></span>
                        <span class="title">Profit & Expenses</span>
                    </a>
                </li>
                <li>
					<a href="#" id="ProductsForm-button">
						<span class="icon"><ion-icon name="fast-food-outline"></ion-icon></span>
						<span class="title">Products</span>
					</a>
				</li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'cashier'): ?>
                <li class="hovered">
                    <a href="#" id="ProductsForm-button">
                        <span class="icon"><ion-icon name="fast-food-outline"></ion-icon></span>
                        <span class="title">Products</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li>
					<a href="#" id="InventoryForm-button">
						<span class="icon"><ion-icon name="archive-outline"></ion-icon></span>
						<span class="title">Inventory</span>
					</a>
				</li>
                <li>
					<a href="#" id="TransactionsForm-button">
						<span class="icon"><ion-icon name="card-outline"></ion-icon></span>
						<span class="title">Transactions</span>
					</a>
				</li>
                <li>
					<a href="#" id="EmployeesForm-button">
						<span class="icon"><ion-icon name="people-outline"></ion-icon></span>
						<span class="title">Employees</span>
					</a>
				</li>
                <li>
                    <a href="#" id="SettingsForm-button">
                        <span class="icon"><ion-icon name="cog-outline"></ion-icon></span>
                        <span class="title">Settings</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
					<a href="#" id="SignOutForm-button">
						<span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
						<span class="title">Sign Out</span>
					</a>
				</li>
            </ul>
        </div>

        <div class="main">
            <!-- ------------------------------------ Dashboard Form ------------------------------------ -->
            <section id="DashboardForm" <?php echo ($_SESSION['role'] === 'admin') ? '' : 'style="display:none;"'; ?>>
                <div class="topbar">
                    <div class="toggle">
                        <ion-icon name="menu-outline"></ion-icon>
                    </div>
                    <!-- search -->
                    <div class="search">
                        <label>
                            <input type="text" placeholder="Search here">
                            <ion-icon name="search-outline"></ion-icon>
                        </label>
                    </div>
                    <!-- user -->
                    <div class="user-container">
                        <div class="user">
                            <img src="assest/image/User Image.jpg" alt="User">
                        </div>
                        <div id="userGreeting" class="user-greeting">
                            <?php echo "Hello, " . htmlspecialchars($_SESSION['username']) . " (" . ucfirst(htmlspecialchars($_SESSION['role'])) . ")!"; ?>
                        </div>
                    </div>
                </div>
    
                <div class="cardBox">
                    <div class="card" id="profitCard">
                        <div>
                            <div class="numbers" id="dailyProfitAmount">&#8369;0.00</div>
                            <div class="cardName">Daily Profit</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="trending-up-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card" id="transactions">
                        <div>
                            <div class="numbers" id="todayTransactions">-</div>
                            <div class="cardName">Today's Transactions</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="reader-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card" id="dailySales">
                        <div>
                            <div class="numbers" id="dailySalesAmount">&#8369;0.00</div>
                            <div class="cardName">Daily Sales</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="cash-outline"></ion-icon>
                        </div>
                    </div>
                    <div class="card" id="expenses">
                        <div>
                            <div class="numbers" id="dailyExpensesAmount">&#8369;0.00</div>
                            <div class="cardName">Daily Expenses</div>
                        </div>
                        <div class="iconBx">
                            <ion-icon name="wallet-outline"></ion-icon>
                        </div>
                    </div>    
                </div>
    
                <div class="charts">
                    <div class="charts-card">
                      <h2 class="chart-title">Top 5 Most Ordered Products</h2>
                      <div id="bar-chart">Loading...</div>
                      <div id="topProductsList" style="display:none;">
                        <div id="topProductsDisplay">
                            <div class="top-products-error" style="color: #6b7280; padding: 2rem; text-align: center;">Click retry to load top products.</div>
                            <div class="top-products-content">
                                <ul></ul>
                            </div>
                        </div>
                      </div>
                    </div>
          
                    <div class="charts-card">
                      <h2 class="chart-title">Daily Sales Chart</h2>
                      <div id="dailySalesChart">
                        <div id="salesChartContainer">
                          <div class="sales-chart-loading">Loading daily sales data...</div>
                          <div class="sales-chart-content" style="display:none;">
                            <div id="daily-sales-chart"></div>
                          </div>
                          <div class="sales-chart-summary">
                            <div class="sales-info">
                              <span class="sales-label">Total Orders Today:</span>
                              <span id="todayOrdersCount">0</span>
                            </div>
                            <div class="sales-info">
                              <span class="sales-label">Revenue Today:</span>
                              <span id="todayRevenue">&#8369;0.00</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                </div>
            </section>

            <!-- ------------------------------------ Products Form ------------------------------------ -->
            <section id="ProductsForm" style="display:none;">
                <div class="products-root">
                    <div class="products-header">
                        <h1 class="products-title">🍽️ Products <span class="products-subtitle">Product Management</span></h1>
                    </div>

                    <!-- Product Actions & Filters -->
                    <div class="products-filters">
                        <div class="filter-row">
                            <button class="btn-primary" onclick="showAddCategoryModal()">
                                <ion-icon name="add-outline"></ion-icon>
                                Add Category
                            </button>
                            <button class="btn-primary" onclick="showAddSizeModal()">
                                <ion-icon name="add-outline"></ion-icon>
                                Add Size
                            </button>
                            <button class="btn-primary" id="addProductBtn" onclick="showAddProductModal()">
                                <ion-icon name="add-outline"></ion-icon>
                                Add Product
                            </button>
                            <div class="search-group">
                                <input type="text" id="productSearch" placeholder="Search products..." class="filter-input">
                                <select id="categoryFilter" class="filter-select">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="products-table-container">
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Size</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th style="text-align: center;">
                                        Select<br>
                                        <input type="checkbox" id="selectAllProducts" style="margin-top: 4px;">
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                <!-- Products will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ------------------------------------ Inventory Form ------------------------------------ -->
            <section id="InventoryForm" style="display:none;">
                <div class="inventory-root">
                    <div class="inventory-header">
                        <h1 class="inventory-title">📦 Inventory <span class="inventory-subtitle">Stock Management</span></h1>
                    </div>

                    <!-- Summary Cards -->
                    <div class="inventory-summary">
                        <div class="summary-card">
                            <span class="summary-icon">📦</span>
                            <h3 class="summary-number" id="totalItems">0</h3>
                            <p class="summary-label">Total Items</p>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon">⚠️</span>
                            <h3 class="summary-number" id="lowStockItems">0</h3>
                            <p class="summary-label">Low Stock</p>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon">✅</span>
                            <h3 class="summary-number" id="inStockItems">0</h3>
                            <p class="summary-label">In Stock</p>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon">❌</span>
                            <h3 class="summary-number" id="outOfStockItems">0</h3>
                            <p class="summary-label">Out of Stock</p>
                        </div>
                    </div>

                    <!-- Inventory Filters -->
                    <div class="inventory-filters">
                        <div class="filter-row">
                            <button class="btn-primary" onclick="showAddStockModal()">
                                <ion-icon name="add-outline"></ion-icon>
                                Add Inventory Item
                            </button>
                            <button id="exportInventoryBtn" type="button" class="btn-secondary" onclick="exportInventory()">
                                <ion-icon name="download-outline"></ion-icon>
                                Export CSV
                            </button>
                            <input type="text" id="inventorySearch" placeholder="Search products..." class="filter-input">
                            <select id="stockFilter" class="filter-select" onchange="filterInventory()">
                                <option value="">All Stock Levels</option>
                                <option value="in-stock">In Stock</option>
                                <option value="low-stock">Low Stock</option>
                                <option value="out-of-stock">Out of Stock</option>
                            </select>
                            <select id="costingCategoryFilter" class="filter-select" hidden onchange="renderCostingTable()">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                    </div>

                    <div class="inventory-tabs">
                        <button class="inventory-tab active" data-target="inventoryLowStockPanel">
                            Supply & Low Stock
                        </button>
                        <button class="inventory-tab" data-target="inventoryCostingPanel">
                            Costing & Profit
                        </button>
                        <button class="inventory-tab" data-target="inventoryProductionCostPanel">
                            Production Cost
                        </button>
                    </div>

                    <div id="inventoryLowStockPanel" class="inventory-tab-panel active">
                        <div class="table-container">
                            <table class="inventory-table">
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Unit</th>
                                        <th>Size</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Point</th>
                                        <th>Cost of Pack</th>
                                        <th>Price Per Unit</th>
                                        <th>Qty / Order</th>
                                        <th>Cost / Order</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-lowstock-list"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="inventoryCostingPanel" class="inventory-tab-panel">
                        <div class="table-container">
                            <table class="inventory-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Product</th>
                                        <th>Size</th>
                                        <th>Actual Price</th>
                                        <th>Total Cost</th>
                                        <th>Profit</th>
                                        <th>Profit Margin</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-costing-list"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="inventoryProductionCostPanel" class="inventory-tab-panel">
                        <div style="margin-bottom: 15px;">
                            <label for="productionCostProductSelect" style="font-weight: 600; margin-right: 10px;">Select Product:</label>
                            <select id="productionCostProductSelect" class="filter-select" style="min-width: 250px;">
                                <option value="">Loading products...</option>
                            </select>
                            <button type="button" id="createProductBtn" class="btn-secondary" style="margin-left: 10px; padding: 10px 16px;">
                                + Create New Product
                            </button>
                            <button type="button" id="addIngredientBtn" class="btn-primary" style="margin-left: 10px; padding: 10px 16px;">
                                + Add Ingredient
                            </button>
                        </div>
                        <div class="table-container">
                            <table class="inventory-table" id="productionCostTable">
                                <thead>
                                    <tr>
                                        <th>ProductID</th>
                                        <th>inventoryID</th>
                                        <th>InventoryName</th>
                                        <th>packSize</th>
                                        <th>unit</th>
                                        <th>packPrice</th>
                                        <th>needed per cup</th>
                                        <th>price per unit</th>
                                        <th>ingredient cost</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="production-cost-list"></tbody>
                                <tfoot>
                                    <tr style="background-color: #f0f0f0; font-weight: 600;">
                                        <td colspan="8" style="text-align: right; padding-right: 20px;">Total:</td>
                                        <td id="production-cost-total" style="text-align: center; font-weight: 700; color: #7f5539;">₱0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </section>
<?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- ------------------------------------ Profit Tracker Form ------------------------------------ -->
            <section id="ProfitTrackerForm" style="display:none;">
                <div class="profit-tracker-root">
                    <div class="profit-header">
                        <div>
                            <h1 class="inventory-title">📊 Profit & Expenses</h1>
                            <p class="inventory-subtitle">Rolling 12-month performance overview</p>
                        </div>
                        <div class="profit-actions">
                            <button type="button" id="profitTrackerRefresh" class="btn-secondary">
                                <ion-icon name="refresh-outline"></ion-icon>
                                Refresh Data
                            </button>
                            <button type="button" id="exportProfitCsvBtn" class="btn-secondary" onclick="exportProfitTracker()">
                                <ion-icon name="download-outline"></ion-icon>
                                Export CSV
                            </button>
                            <span class="profit-tracker-updated" id="profitTrackerUpdated"></span>
                        </div>
                    </div>

                    <div class="inventory-summary profit-summary">
                        <div class="summary-card">
                            <span class="summary-icon">₱</span>
                            <h3 class="summary-number" id="profitTrackerTotalRevenue">₱0.00</h3>
                            <p class="summary-label">12-Month Sales</p>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon">🧾</span>
                            <h3 class="summary-number" id="profitTrackerTotalExpenses">₱0.00</h3>
                            <p class="summary-label">12-Month Expenses</p>
                        </div>
                        <div class="summary-card">
                            <span class="summary-icon">📈</span>
                            <h3 class="summary-number" id="profitTrackerTotalProfit">₱0.00</h3>
                            <p class="summary-label">12-Month Profit</p>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Sales</th>
                                    <th>Ingredient Cost</th>
                                    <th>Profit</th>
                                    <th>Margin</th>
                                </tr>
                            </thead>
                            <tbody id="profit-tracker-body">
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                                        Loading profit data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-container">
                        <div class="profit-weekly-header">
                            <h2>Weekly Snapshot (Last 4 Weeks)</h2>
                        </div>
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Sales</th>
                                    <th>Ingredient Cost</th>
                                    <th>Profit</th>
                                    <th>Margin</th>
                                </tr>
                            </thead>
                            <tbody id="profit-tracker-weekly-body">
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:30px; color:#6b7280;">
                                        Loading weekly data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
<?php endif; ?>
            

            
            <!-- ------------------ Transactions Tab ------------------ -->
            <section id="TransactionsForm" style="display:none;">
                <div class="transactions-root">
                    <div class="transactions-header">
                        <h1 class="transactions-title">Transactions <span class="transactions-subtitle">Order Management</span></h1>
                    </div>

                    <!-- Filter Controls -->
                    <div class="transactions-filters">
                        <div class="filter-row">
                            <select id="transactionStatusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <select id="transactionPaymentFilter" class="filter-select">
                                <option value="">All Payment Methods</option>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                            </select>
                            <input type="date" id="transactionDateFilter" class="filter-input">
                            <input type="text" id="transactionSearch" placeholder="Search by transaction ID" class="filter-input">
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="transactions-table-container">
                        <table id="transactionsTable" class="transactions-table">
                    <thead>
                            <tr>
                            <th>Transaction ID</th>
                            <th>Reference Number</th>
                            <th>Date & Time</th>
                            <th>CashierID</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                            <tbody id="transactionsTableBody">
                                <!-- Loaded via JavaScript -->
                            </tbody>
                </table>
                        
                        <!-- Summary Cards -->
                        <div class="transactions-summary">
                            <div class="summary-card">
                                <h4>Today's Total</h4>
                                <span id="todaysRevenue">₱0.00</span>
                            </div>
                            <div class="summary-card">
                                <h4>Transaction Count</h4>
                                <span id="transactionCount">0</span>
                            </div>
                            <div class="summary-card">
                                <h4>Avg Transaction</h4>
                                <span id="averageTransaction">₱0.00</span>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- ------------------ Employees Tab ------------------ -->
            <section id="EmployeesForm" style="display:none;">
                <div class="employees-root">
                    <div class="employees-header">
                        <h1 class="employees-title">👥 Employees <span class="employees-subtitle">Staff Management & Controls</span></h1>
                    </div>

                    <!-- Employee Actions & Filters -->
                    <div class="employees-filters">
                        <div class="filter-row">
                            <button id="addEmployeeBtn" class="btn-primary" onclick="showAddEmployeeModal()">
                                <ion-icon name="person-add-outline"></ion-icon>
                                Add Employee
                            </button>
                            <div id="employeeBulkActions" class="employee-bulk-actions">
                                <span class="bulk-count"><strong id="selectedEmployeesCount">0</strong> selected</span>
                                <button id="employeeBulkDeleteBtn" class="btn-danger" disabled>
                                    <ion-icon name="trash-outline"></ion-icon>
                                    Delete Selected
                                </button>
                            </div>
                            <input type="text" id="employeeSearch" placeholder="Search employees..." class="filter-input">
                            <select id="employeeRoleFilter" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                            </select>
                        </div>
                    </div>

                    <!-- Employees Table -->
                    <div class="employees-table-container">
                        <table class="employees-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Employee ID</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Status</th>
                                    <th>
                                        <input type="checkbox" id="employeesSelectAll" class="select-all-checkbox">
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody">
                                <!-- Employees will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Employee Modal -->
                    <div id="addEmployeeModal" class="modal" style="display:none;">
                        <div class="modal-content employee-modal">
                            <span class="close" onclick="closeAddEmployeeModal()">&times;</span>
                            <h2>Add New Employee</h2>
                            
                            <!-- Tab Navigation -->
                            <div class="employee-tabs">
                                <button type="button" class="tab-button active" onclick="switchEmployeeTab('basic')">
                                    <ion-icon name="person-outline"></ion-icon>
                                    Basic Information
                                </button>
                                <button type="button" class="tab-button" onclick="switchEmployeeTab('login')">
                                    <ion-icon name="lock-closed-outline"></ion-icon>
                                    Login Information
                                </button>
                            </div>
                            
                            <form id="addEmployeeForm">
                                <!-- Basic Information Tab -->
                                <div id="basicInfoTab" class="tab-content active">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employeeFirstName">First Name:</label>
                                            <input type="text" id="employeeFirstName" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="employeeLastName">Last Name:</label>
                                            <input type="text" id="employeeLastName" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="employeeEmail">Email:</label>
                                        <input type="email" id="employeeEmail" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="employeePhone">Phone Number:</label>
                                        <input type="tel" id="employeePhone" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="employeeAddress">Address:</label>
                                        <textarea id="employeeAddress" rows="3" required></textarea>
                                    </div>
                                    <div class="tab-navigation">
                                        <button type="button" class="btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
                                        <button type="button" class="btn-primary" onclick="switchEmployeeTab('login')">Next: Login Info</button>
                                    </div>
                                </div>
                                
                                <!-- Login Information Tab -->
                                <div id="loginInfoTab" class="tab-content">
                                    <div class="form-group">
                                        <label for="employeeUsername">Username:</label>
                                        <input type="text" id="employeeUsername" required>
                                    </div>
                                    <div class="form-group" id="employeePasswordGroup" style="display:none;">
                                        <label for="employeePassword">Password:</label>
                                        <input type="password" id="employeePassword" autocomplete="new-password">
                                    </div>
                                    <div class="form-group">
                                        <label for="employeeRole">Role:</label>
                                        <select id="employeeRole" required>
                                            <option value="cashier">Cashier</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="employeePinGroup">
                                        <label for="employeePin">4-Digit PIN:</label>
                                        <input type="password" id="employeePin" maxlength="4" pattern="[0-9]{4}" placeholder="1234" required>
                                        <small>4-digit PIN for POS login</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="employeeId">Employee ID:</label>
                                        <input type="text" id="employeeId" placeholder="EMP001" required>
                                        <small>Unique employee identifier</small>
                                    </div>
                                    <div class="tab-navigation">
                                        <button type="button" class="btn-secondary" onclick="switchEmployeeTab('basic')">Back: Basic Info</button>
                                        <button type="submit" class="btn-primary">Add Employee</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ------------------------------------ Settings Form ------------------------------------ -->
            <section id="SettingsForm" style="display:none;">
                <div class="settings-root">
                    <div class="settings-header">
                        <h2 class="page-title">⚙️ Settings <span class="page-subtitle">System Control & Management</span></h2>
                    </div>

            
                        <!-- Audit Navigation Section -->
                        <div class="audit-navigation-section">
                            <div class="audit-navigation-header">
                                <h3>🛡️ Audit & Security Management</h3>
                                <p class="audit-description">Monitor employee activity and system security logs</p>
                            </div>
                            
                            <div class="audit-navigation-buttons">
                                <button class="audit-nav-button active" onclick="showAuditSection('logs')">
                                    <ion-icon name="list-outline"></ion-icon>
                                    <span>Activity Logs</span>
                                </button>
                                <button class="audit-nav-button" onclick="showAuditSection('security')">
                                    <ion-icon name="shield-outline"></ion-icon>
                                    <span>Security Dashboard</span>
                                </button>
                                <button class="audit-nav-button" onclick="showAuditSection('reports')">
                                    <ion-icon name="document-text-outline"></ion-icon>
                                    <span>Audit Reports</span>
                                </button>
                            </div>
                        </div>

                        <!-- Audit Logs Section -->
                        <div id="audit-logs-section" class="audit-content-section active">
                            <div class="audit-header">
                                <h3>Employee Activity Logs</h3>
                                <div class="audit-controls">
                                    <button class="btn-primary" onclick="exportAuditLogs()">
                                        <ion-icon name="download-outline"></ion-icon>
                                        Export CSV
                                    </button>
                                </div>
                            </div>
                            
                            <div class="audit-filters">
                                <div class="filter-group">
                                    <label for="auditDateFilter">Date Range:</label>
                                    <input type="date" id="auditDateFrom" class="filter-input">
                                    <span>to</span>
                                    <input type="date" id="auditDateTo" class="filter-input">
                                </div>
                                <div class="filter-group">
                                    <label for="auditUserFilter">Employee:</label>
                                    <select id="auditUserFilter" class="filter-select">
                                        <option value="">All Employees</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="auditActionFilter">Action:</label>
                                    <select id="auditActionFilter" class="filter-select">
                                        <option value="">All Actions</option>
                                        <option value="login">Login</option>
                                        <option value="logout">Logout</option>
                                        <option value="failed_login">Failed Login</option>
                                        <option value="product_add">Product Added</option>
                                        <option value="product_update">Product Updated</option>
                                        <option value="product_delete">Product Deleted</option>
                                        <option value="employee_add">Employee Added</option>
                                        <option value="employee_update">Employee Updated</option>
                                        <option value="employee_delete">Employee Deleted</option>
                                    </select>
                                </div>
                                <button class="btn-primary" onclick="filterAuditLogs()">
                                    <ion-icon name="search-outline"></ion-icon>
                                    Filter
                                </button>
                            </div>
                            
                            <div class="audit-table-container">
                                <table class="audit-table">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>Employee</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auditLogsBody">
                                        <tr>
                                            <td colspan="6" class="loading-spinner">Loading audit logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                    </div>

                            <div class="audit-pagination">
                                <button class="btn-secondary" onclick="previousAuditPage()" id="prevAuditBtn" disabled>
                                    <ion-icon name="chevron-back-outline"></ion-icon>
                                    Previous
                                </button>
                                <span id="auditPageInfo">Page 1 of 1</span>
                                <button class="btn-secondary" onclick="nextAuditPage()" id="nextAuditBtn" disabled>
                                    Next
                                    <ion-icon name="chevron-forward-outline"></ion-icon>
                                </button>
                            </div>
                        </div>

                        <!-- Security Dashboard Section -->
                        <div id="audit-security-section" class="audit-content-section">
                            <div class="security-dashboard">
                                <h3>🔒 Security Dashboard</h3>
                                <div class="security-stats-grid">
                                    <div class="security-stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="warning-outline"></ion-icon>
                                        </div>
                                        <div class="stat-content">
                                            <h4>Failed Logins (24h)</h4>
                                            <p class="stat-value" id="failedLogins">0</p>
                                        </div>
                                    </div>
                                    <div class="security-stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="people-outline"></ion-icon>
                                        </div>
                                        <div class="stat-content">
                                            <h4>Active Sessions</h4>
                                            <p class="stat-value" id="activeSessions">0</p>
                                        </div>
                                    </div>
                                    <div class="security-stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="log-in-outline"></ion-icon>
                                        </div>
                                        <div class="stat-content">
                                            <h4>Total Logins Today</h4>
                                            <p class="stat-value" id="totalLogins">0</p>
                                        </div>
                                    </div>
                                    <div class="security-stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="time-outline"></ion-icon>
                                        </div>
                                        <div class="stat-content">
                                            <h4>Last Activity</h4>
                                            <p class="stat-value" id="lastActivity">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Reports Section -->
                        <div id="audit-reports-section" class="audit-content-section">
                            <div class="audit-reports">
                                <h3>📊 Audit Reports</h3>
                                <div class="reports-grid">
                                    <div class="report-card">
                                        <h4>Employee Activity Summary</h4>
                                        <p>Generate comprehensive reports of employee activities</p>
                                        <button class="btn-primary" onclick="generateEmployeeReport()">
                                            <ion-icon name="download-outline"></ion-icon>
                                            Generate Report
                                        </button>
                                    </div>
                                    <div class="report-card">
                                        <h4>Security Events Report</h4>
                                        <p>Export security events and login attempts</p>
                                        <button class="btn-primary" onclick="generateSecurityReport()">
                                            <ion-icon name="shield-outline"></ion-icon>
                                            Generate Report
                                        </button>
                                    </div>
                                    <div class="report-card">
                                        <h4>System Activity Log</h4>
                                        <p>Complete system activity and changes log</p>
                                        <button class="btn-primary" onclick="generateSystemReport()">
                                            <ion-icon name="document-text-outline"></ion-icon>
                                            Generate Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>




            <div id="toast" class="toast"></div>


        </div>    

        
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
	<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.51.0/apexcharts.min.js"></script>
    <script src="js/uiToast.js"></script>
    <script src="js/dataService.js"></script>
    <script src="js/productService.js"></script>
    <script src="js/modalHelper.js"></script>
  
    <script src="js/Dashboard.js"></script>
    <script src="js/DashboardManager.js"></script>
    <script src="js/RealTimeSync.js"></script>
    <script src="js/Navigation.js"></script>
    <script src="js/ProductSync.js"></script>
    <script src="js/Inventory.js"></script>
    <script src="js/ProductManagement.js"></script>
    <script src="js/SettingsManager.js"></script>
    <?php if ($_SESSION['role'] === 'cashier'): ?>
    <script src="js/POS.js"></script>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <script src="js/dashboard_analytics.js"></script>
    <script src="js/Transactions.js"></script>
    <script src="js/Employees.js"></script>
    <?php endif; ?>
    
    <script>
        // Transfer PHP session role to JavaScript
        window.USER_ROLE = '<?php echo htmlspecialchars($_SESSION['role'] ?? 'admin'); ?>';
        console.log('User role set as:', window.USER_ROLE);
    </script>
</body>
</html>



