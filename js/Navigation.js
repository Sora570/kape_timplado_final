// Proper role-limited navigation 
function setupCashierMode() {
    const isCashier = window.USER_ROLE === 'cashier' || 
                      document.body.classList.contains('cashier-mode') || 
                      window.location.pathname.includes('cashier') ||
                      sessionStorage.getItem('role') === 'cashier';
    
    if (isCashier) {
        //  Skip setup if the PHP already hidden the sections
        return true;
    }
    
    return false;
}

function hideAllSections() {
    const dashboardForm = document.getElementById("DashboardForm");
    if (dashboardForm) dashboardForm.style.display = "none";
    const productsForm = document.getElementById("ProductsForm");
    if (productsForm) productsForm.style.display = "none";
    const inventoryForm = document.getElementById("InventoryForm");
    if (inventoryForm) inventoryForm.style.display = "none";
    const profitTrackerForm = document.getElementById("ProfitTrackerForm");
    if (profitTrackerForm) profitTrackerForm.style.display = "none";
    const transactionsForm = document.getElementById("TransactionsForm");
    if (transactionsForm) transactionsForm.style.display = "none";
    const employeesForm = document.getElementById("EmployeesForm");
    if (employeesForm) employeesForm.style.display = "none";
    const settingsForm = document.getElementById("SettingsForm");
    if (settingsForm) settingsForm.style.display = "none";
    const ordersForm = document.getElementById("OrdersForm");
    if (ordersForm) ordersForm.style.display = "none";
    
    // Hide sub-order sections only for cashier context
    const pendingOrdersEl = document.getElementById("PendingOrdersForm");
    const completedOrdersEl = document.getElementById("CompletedOrdersForm");
    if (pendingOrdersEl) pendingOrdersEl.style.display = "none";
    if (completedOrdersEl) completedOrdersEl.style.display = "none";
}

function toggleOrdersSubmenu() {
    // Removed as Orders tab is cashier-only and no longer has submenus
    return;
}

function setActiveNav(anchorId) {
    const allItems = document.querySelectorAll('.navigation ul li');
    allItems.forEach(li => li.classList.remove('hovered'));
    const anchor = document.getElementById(anchorId);
    if (anchor && anchor.parentElement && anchor.parentElement.tagName === 'LI') {
        anchor.parentElement.classList.add('hovered');
    }
}

document.getElementById("Dashboard-button")?.addEventListener("click", function () {
    hideAllSections();
    const dashboardForm = document.getElementById("DashboardForm");
    if (dashboardForm) dashboardForm.style.display = "block";
    setActiveNav('Dashboard-button');
    
    // Trigger analytics load for admin dashboard
    if(window.USER_ROLE === 'admin' && typeof window.loadDashboardAnalytics === 'function') {
        window.loadDashboardAnalytics();
    }
});

document.getElementById("ProductsForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const productsForm = document.getElementById("ProductsForm");
    if (productsForm) productsForm.style.display = "block";
    setActiveNav('ProductsForm-button');
    
    // Load products when tab is opened
    if (typeof loadProducts === 'function') {
        loadProducts();
    }
});

document.getElementById("InventoryForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const inventoryForm = document.getElementById("InventoryForm");
    if (inventoryForm) inventoryForm.style.display = "block";
    setActiveNav('InventoryForm-button');

    // Initialize inventory when tab is opened
    if (typeof initializeInventory === "function") {
        initializeInventory();
    }
});

document.getElementById("ProfitTrackerForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const profitForm = document.getElementById("ProfitTrackerForm");
    if (profitForm) profitForm.style.display = "block";
    setActiveNav('ProfitTrackerForm-button');

    if (typeof loadProfitTracker === 'function') {
        loadProfitTracker(true);
    }
});

document.getElementById("TransactionsForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const transactionsForm = document.getElementById("TransactionsForm");
    if (transactionsForm) transactionsForm.style.display = "block";
    setActiveNav('TransactionsForm-button');
    
    // Load transactions when tab is opened
    if (typeof loadTransactions === 'function') {
        loadTransactions();
    }
});

document.getElementById("EmployeesForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const employeesForm = document.getElementById("EmployeesForm");
    if (employeesForm) employeesForm.style.display = "block";
    setActiveNav('EmployeesForm-button');
    
    // Load employees when tab is opened
    if (typeof loadEmployees === 'function') {
        loadEmployees();
    }
});

document.getElementById("SettingsForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const settingsForm = document.getElementById("SettingsForm");
    if (settingsForm) settingsForm.style.display = "block";
    setActiveNav('SettingsForm-button');
});

// Orders button - Cashier only
document.getElementById("OrdersForm-button")?.addEventListener("click", function () {
    hideAllSections();
    const ordersForm = document.getElementById("OrdersForm");
    if (ordersForm) ordersForm.style.display = "block";
    setActiveNav('OrdersForm-button');
    
    // Initialize cashier POS Order view if needed
    if (window.USER_ROLE === 'cashier' && typeof loadTodaysStats === 'function') {
        loadTodaysStats();
    }
});

document.getElementById("product")?.addEventListener("click", function (){
    const dashboardForm = document.getElementById("DashboardForm");
    if (dashboardForm) dashboardForm.style.display = "none";
    const productsForm = document.getElementById("ProductsForm");
    if (productsForm) productsForm.style.display = "block";
    setActiveNav('ProductsForm-button');
});

document.getElementById("transactions")?.addEventListener("click", function (){
    const dashboardForm = document.getElementById("DashboardForm");
    if (dashboardForm) dashboardForm.style.display = "none";
    const transactionsForm = document.getElementById("TransactionsForm");
    if (transactionsForm) transactionsForm.style.display = "block";
    setActiveNav('TransactionsForm-button');
});
  
// Ensure the default selected nav is correct on load
window.addEventListener('load', function () {
    console.log('Navigation.js: Page loaded, USER_ROLE:', window.USER_ROLE);
    
    // Check for cashier mode first
    const isCashier = setupCashierMode();
    
    if (isCashier) {
        console.log('Navigation.js: Cashier mode detected');
        // Cashiers start with Products tab open by default
        hideAllSections();
        const productsForm = document.getElementById('ProductsForm');
        if (productsForm) productsForm.style.display = 'block';
        setActiveNav('ProductsForm-button');
        
        // Initialize POS if available
        if (typeof initPOS === 'function') {
            setTimeout(initPOS, 100);
        }
    } else {
        console.log('Navigation.js: Admin mode detected, showing Dashboard');
        // Admin defaults to Dashboard
        hideAllSections();
        const dashboardForm = document.getElementById('DashboardForm');
        if (dashboardForm) dashboardForm.style.display = 'block';
        setActiveNav('Dashboard-button');
        
        // Load dashboard analytics if available
        if (typeof window.loadDashboardAnalytics === 'function') {
            setTimeout(() => window.loadDashboardAnalytics(), 200);
        }
    }
});

// ----------------- Sign Out -----------------
document.getElementById('SignOutForm-button')?.addEventListener('click', function (e) {
    e.preventDefault();
    fetch('db/logout.php', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            console.log('Logout successful:', data.message);
            window.location.href = 'loginRegister.html';
        })
        .catch(error => {
            console.error('Logout error:', error);
            // Still redirect even if there's an error
            window.location.href = 'loginRegister.html';
        });
});
