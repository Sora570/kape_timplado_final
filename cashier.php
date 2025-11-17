<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'cashier') {
    header("Location: loginRegister.html");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cashier POS — Kape Timplado's (Responsive)</title>

  <!-- Icons & Fonts -->
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <script src="js/uiToast.js"></script>
  <script src="js/dataService.js"></script>
  <script src="js/productService.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --brown:#7f5539;
      --brown-dark:#6d4329;
      --beige:#faf6f3;
      --accent:#3D2B1F;
      --bg:#f7f4f2;
      --card:#fff;
      --glass: rgba(255,255,255,0.7);
      --success: #28a745;
      --danger: #dc3545;
      --muted: #3D2B1F;
      --radius: 12px;
      --max-width: 1300px;
      --gap: 14px;
    }

    *{box-sizing:border-box}
    html,body{height:100%;margin:0;font-family:'Fredoka',system-ui,Arial;background:linear-gradient(180deg,#fbf9f7,#f0e9e5);color:#2b2020}
    a{color:inherit;text-decoration:none}

    /* Container */
    .app {
      max-width: 100%;
      margin: 0;
      background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(250,246,243,0.9));
      border-radius: 0;
      box-shadow: none;
      overflow: hidden;
      display: grid;
      grid-template-columns: 260px 1fr;
      gap: 0;
      min-height: 100vh;
      width: 100%;
    }

    /* Sidebar / Navigation */
    .sidebar {
      background: linear-gradient(180deg,var(--brown) 0%, #6a3f2e 100%);
      color: #fff;
      padding: 18px 12px;
      display:flex;
      flex-direction:column;
      gap: 12px;
      min-height: 320px;
    }
    .brand {
      display:flex;align-items:center;gap:10px;padding:6px 12px;border-radius:10px;background:rgba(255,255,255,0.06)
    }
    .brand img{width:42px;height:42px;border-radius:6px;object-fit:cover;box-shadow:0 2px 6px rgba(0,0,0,0.15)}
    .brand .title{font-size:1.1rem;font-weight:600}
    .nav-list{margin-top:8px;display:flex;flex-direction:column;gap:8px}
    .nav-item{display:flex;align-items:center;gap:12px;padding:10px;border-radius:10px;cursor:pointer;color:#fff;transition:background .15s}
    .nav-item ion-icon{font-size:20px}
    .nav-item.active, .nav-item:hover{background:rgba(255,255,255,0.07)}
    .nav-item .label{font-weight:600}
    .sidebar .userbox{margin-top:auto;padding:10px;border-radius:10px;background:rgba(0,0,0,0.06);display:flex;align-items:center;gap:12px}
    .userbox img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.12)}
    .userbox .name{font-size:0.95rem}
    .signout{margin-top:8px;background:rgba(255,255,255,0.08);border:none;padding:8px;border-radius:8px;color:#fff;cursor:pointer}

    /* Main */
    .main {
      padding: 16px;
      display:flex;
      flex-direction:column;
      gap:12px;
      min-height: calc(100vh - 100px);
    }

    .topbar {
      display:flex;
      align-items:center;
      gap:12px;
      justify-content:space-between;
    }
    .topbar .left {
      display:flex;align-items:center;gap:8px;
    }
    .topbar .toggle {display:none;padding:8px;border-radius:10px;background:var(--card);cursor:pointer;box-shadow:0 2px 6px rgba(20,10,8,0.05)}
    .search {
      display:flex;align-items:center;gap:8px;background:var(--card);padding:8px;border-radius:10px;box-shadow:0 2px 6px rgba(20,10,8,0.03)
    }
    .search input{border:0;outline:none;font-size:14px;background:transparent;padding:6px}

    .category-select {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid rgba(0,0,0,0.06);
      background: var(--card);
      font-size: 14px;
      color: var(--muted);
    }

    .cart-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 10px;
      background: var(--card);
      border: 1px solid rgba(0,0,0,0.06);
      cursor: pointer;
      font-size: 14px;
      color: var(--brown);
      transition: background 0.15s;
    }

    .cart-btn:hover {
      background: var(--beige);
    }

    .cart-btn ion-icon {
      font-size: 18px;
    }

    .badge {
      background: var(--danger);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      min-width: 20px;
    }

    /* POS layout (Products + Cart) */
    .pos {
      display: flex;
      flex-direction: row;
      height: calc(100vh - 140px);
      gap: 18px;
    }

    /* Menu section */
    .menu {
      background:var(--card);
      padding:12px;border-radius:12px;box-shadow:0 6px 18px rgba(35,25,20,0.04);
      display:flex;flex-direction:column;gap:12px; flex: 1; overflow: hidden;
    }

    .products-grid {
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap:12px;
      overflow-y:auto;
      height: calc(100% - 50px);
      padding-bottom:6px;
    }
    .product-card {
      background:linear-gradient(180deg,var(--glass),var(--card));
      border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px;align-items:stretch;
      cursor:pointer;transition:transform .12s, box-shadow .12s;
      aspect-ratio: 1;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .product-card:hover{transform:translateY(-4px);box-shadow:0 10px 30px rgba(30,15,10,0.06)}
    .product-meta{display:flex;flex-direction:column;gap:4px;}
    .product-meta .name{font-weight:600;font-size:15px;color:var(--brown-dark); text-align:center; word-break: break-word;}
    .product-meta .category{font-size:12px;color:var(--muted); text-align:center; word-break: break-word; margin-bottom: 8px;}
    .product-meta .sizes{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
    .size-btn{
      padding:6px 12px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);background:var(--beige);font-size:12px;cursor:pointer;font-weight:500
    }

    /* Cart */
    .cart {
      background:var(--card);padding:12px;border-radius:12px;box-shadow:0 6px 18px rgba(35,25,20,0.04);display:flex;flex-direction:column; width: 320px; flex-shrink: 0; overflow-y: auto;
    }
    .cart .cart-header{display:flex;align-items:center;justify-content:space-between;gap:8px}
    .cart-items{margin-top:10px;display:flex;flex-direction:column;gap:8px;overflow:auto;max-height:52vh;padding-right:6px}
    .cart-items .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
      font-size: 16px;
      opacity: 0.7;
    }
    .cart-item{display:flex;align-items:center;gap:10px;padding:8px;border-radius:10px;background:linear-gradient(180deg, #fff,#fbf6f4)}
    .ci-info{flex:1;display:flex;flex-direction:column;gap:4px}
    .ci-controls{display:flex;align-items:center;gap:6px}
    .qty-btn{padding:6px 8px;border-radius:8px;background:transparent;border:1px solid rgba(0,0,0,0.06);cursor:pointer}
    .remove-btn{background:transparent;border:0;color:var(--danger);cursor:pointer;font-size:14px}

    .cart-footer{margin-top:auto;padding-top:10px;border-top:1px dashed rgba(0,0,0,0.06);display:flex;flex-direction:column;gap:8px}
    .totals{display:flex;flex-direction:column;gap:6px}
    .totals .row{display:flex;justify-content:space-between;font-weight:600}
    .checkout-btn{margin-top:6px;padding:12px;border-radius:10px;border:0;background:var(--brown);color:#fff;font-weight:700;cursor:pointer}

    /* Orders & Closeout sections (simple) */
    .section-card{background:var(--card);padding:14px;border-radius:12px;box-shadow:0 6px 18px rgba(35,25,20,0.04); min-height: calc(100vh - 200px); display: flex; flex-direction: column;}
    .orders-table{width:100%;border-collapse:collapse}
    .orders-table th, .orders-table td{padding:8px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:left;font-size:13px}
    .order-date-filter{display:flex;gap:6px;font-size:13px}
    .order-date-filter .filter-options{display:flex;gap:6px;flex-wrap:wrap}
    .order-date-filter button{border:1px solid rgba(0,0,0,0.1);background:#fff;padding:4px 8px;border-radius:8px;font-size:12px;cursor:pointer}
    .order-date-filter button.active{background:var(--brown);color:#fff;border-color:var(--brown)}
    .order-detail div{margin-bottom:6px;font-size:14px}
    .order-items{margin-top:4px;font-size:13px;line-height:1.4}
    .closeout-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:12px}
    .closeout-card{background:var(--card);padding:16px;border-radius:14px;box-shadow:0 4px 14px rgba(0,0,0,0.05)}
    .shift-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-top:12px}
    .shift-metric h2{margin:0;font-size:24px;color:var(--brown)}
    .shift-metric .label{font-size:12px;color:var(--muted);margin:0 0 4px}
    .shift-times{display:flex;gap:24px;flex-wrap:wrap;margin-top:16px}
    .shift-times .label{font-size:12px;color:var(--muted);margin:0 0 4px}
    .closeout-right{display:flex;flex-direction:column;gap:16px}
    .payment-line{display:flex;justify-content:space-between;font-size:14px;margin-bottom:8px}
    .closeout-table-wrapper{max-height:360px;overflow:auto;margin-top:12px}
    .closeout-table th{color:#9a816f;font-size:12px;text-transform:uppercase}
    .closeout-table td{font-size:13px}
    @media(max-width:1024px){
      .closeout-grid{grid-template-columns:1fr;gap:16px}
      .closeout-right{flex-direction:row;flex-wrap:wrap}
      .closeout-right .closeout-card{flex:1;min-width:220px}
    }
    .orders-actions button{margin-right:8px;padding:6px 10px;border-radius:8px;border:0;cursor:pointer}

    /* Checkout modal */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:60}
    .modal{background:var(--card);padding:18px;border-radius:12px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(10,10,10,0.2)}
    .modal h3{margin-top:0}

    /* Responsiveness */
    @media (max-width:1200px){
      .products-grid{grid-template-columns: repeat(auto-fit, minmax(250px, 1fr))}
      .app{grid-template-columns: 220px 1fr}
    }
    @media (max-width:880px){
      .app{grid-template-columns: 1fr}
      .sidebar{flex-direction:row;gap:8px;align-items:center;padding:10px;overflow:auto;min-height:64px}
      .sidebar .brand{display:none}
      .topbar .toggle{display:block}
      .pos{flex-direction: column; height: auto; gap:10px;}
      .products-grid{grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); height: 60vh;}
      .cart { width: auto; order: 2; }
    }
    @media (max-width:800px){
      .products-grid{grid-template-columns: 1fr;}
      .product-image{height:100px;}
      .size-btn{font-size:11px; padding:5px 10px;}
    }
    @media (max-width:520px){
      .products-grid{grid-template-columns: repeat(1, 1fr)}
      .product-image{width:70px;max-height:60px}
      .cart-items{max-height:38vh}
    }

    /* small helpers */
    .muted{color:var(--muted);font-size:12px}
    .flex{display:flex;gap:8px;align-items:center}
    .small{font-size:13px}
    .pill{padding:6px 10px;border-radius:999px;background:rgba(0,0,0,0.04)}

    /* Toast notification */
    .toast{position:fixed;top:20px;right:20px;background:var(--danger);color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:1000;opacity:0;transform:translateY(-20px);transition:opacity 0.3s, transform 0.3s;max-width:300px;word-wrap:break-word}
    .toast.show{opacity:1;transform:translateY(0)}
    .toast.success{background:var(--success)}
  </style>
</head>
<body>
  <div class="app" id="app">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <img src="assest/image/logo.png" alt="logo" onerror="this.src='assest/image/no-image.png'">
        <div>
          <div style="font-size:1rem;font-weight:700">Kape Timplado's</div>
          <div style="font-size:12px;color:rgba(255,255,255,0.85)">Cashier</div>
        </div>
      </div>

      <nav class="nav-list" id="nav">
        <div class="nav-item active" data-section="ProductsForm"><ion-icon name="fast-food-outline"></ion-icon><span class="label">Products</span></div>
        <div class="nav-item" data-section="OrdersForm"><ion-icon name="receipt-outline"></ion-icon><span class="label">Orders</span></div>
        <div class="nav-item" data-section="CloseoutForm"><ion-icon name="calculator-outline"></ion-icon><span class="label">Close-Out</span></div>
        <div class="nav-item" id="signOutBtn"><ion-icon name="log-out-outline"></ion-icon><span class="label">Sign Out</span></div>
      </nav>

      <div class="userbox">
        <img src="assest/image/User Image.jpg" alt="user" onerror="this.src='assest/image/no-image.png'">
        <div>
          <div class="name">Hello, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Cashier'); ?></div>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <main class="main">
      <div class="topbar">
        <div class="left">
          <button class="toggle" id="sidebarToggle"><ion-icon name="menu-outline"></ion-icon></button>
          <h2 style="margin:0">Point of Sale & Orders</h2>
        </div>

        <div style="display:flex;align-items:center;gap:10px">
          <div class="search">
            <ion-icon name="search-outline"></ion-icon>
            <input id="globalSearch" placeholder="Search products..." />
          </div>
          <select id="categoryFilter" class="category-select">
            <option value="">All Categories</option>
          </select>
          <button id="cartBtn" class="cart-btn">
            <ion-icon name="cart-outline"></ion-icon>
            Cart <span id="cartBadge" class="badge">0</span>
          </button>
        </div>
      </div>

      <!-- Sections -->
      <section id="ProductsForm" class="section-card" style="display:block">
        <div class="pos">
          <!-- Menu -->
          <div class="menu">

            <div class="products-grid" id="productsGrid" aria-live="polite">
              <!-- product cards inserted here -->
            </div>
          </div>

          <!-- Cart -->
          <aside class="cart">
            <div class="cart-header">
              <h3 style="margin:0">Cart</h3>
              <div class="muted small">₱ currency</div>
            </div>

            <div class="cart-items" id="cartItems">
              <div class="muted empty-state">Cart is empty</div>
            </div>

            <div class="cart-footer">
              <div class="totals">
                <div class="row" style="font-size:18px"><strong>Total</strong><strong id="total">₱0.00</strong></div>
              </div>
              <div style="display:flex;gap:8px;align-items:center;margin-top:6px">
                <button class="checkout-btn" id="checkoutBtn">Checkout</button>
                <button class="btn-secondary pill" id="clearCartBtn">Clear</button>
              </div>
            </div>
          </aside>
        </div>
      </section>

      <!-- Orders -->
      <section id="OrdersForm" class="section-card" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:12px">
          <h3 style="margin:0">Orders</h3>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <input id="orderSearch" placeholder="Search orders..." style="padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.06)">
            <div class="order-date-filter">
              <div class="filter-options">
                <button type="button" data-range="today">Today</button>
                <button type="button" data-range="yesterday">Yesterday</button>
                <button type="button" data-range="week">This Week</button>
                <button type="button" data-range="all">All</button>
              </div>
            </div>
          </div>
        </div>

        <div style="flex: 1; overflow: auto;">
          <table class="orders-table" aria-live="polite" style="width: 100%;">
            <thead>
              <tr><th>ID</th><th>Items</th><th>Total</th><th>Status</th><th>Reference Number</th><th>Timestamp</th></tr>
            </thead>
            <tbody id="ordersTableBody">
              <!-- orders -->
            </tbody>
          </table>
        </div>
      </section>

      <!-- Closeout -->
      <section id="CloseoutForm" class="section-card" style="display:none">
        <div class="closeout-grid">
          <div class="closeout-left">
            <div class="closeout-card shift-card">
              <div class="shift-card-header">
                <div>
                  <h3 style="margin:0">Close-Out / End of Shift</h3>
                  <p class="muted small" style="margin:2px 0 0">Shift summary for today</p>
                </div>
              </div>
              <div class="shift-metrics">
                <div class="shift-metric">
                  <p class="label">Total Orders (Today)</p>
                  <h2 id="closeoutTotalOrders">0</h2>
                </div>
                <div class="shift-metric">
                  <p class="label">Gross Sales (Today)</p>
                  <h2 id="closeoutGrossSales">₱0.00</h2>
                </div>
                <div class="shift-metric">
                  <p class="label">Net Sales (Today)</p>
                  <h2 id="closeoutNetSales">₱0.00</h2>
                </div>
              </div>
              <div class="shift-times">
                <div>
                  <p class="label">Shift Time In</p>
                  <strong id="closeoutShiftStart">-</strong>
                </div>
                <div>
                  <p class="label">Shift Time Out</p>
                  <strong id="closeoutShiftEnd">-</strong>
                </div>
              </div>
            </div>

            <div class="closeout-card">
              <div class="closeout-table-header">
                <h4 style="margin:0">Sales Breakdown by Product</h4>
              </div>
              <div class="closeout-table-wrapper">
                <table class="orders-table closeout-table">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Qty Sold</th>
                      <th>Gross</th>
                      <th>Cost</th>
                      <th>Net</th>
                    </tr>
                  </thead>
                  <tbody id="closeoutSalesBody">
                    <tr><td colspan="5" class="muted">No sales data for today</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <aside class="closeout-right">
            <div class="closeout-card payment-card">
              <h4 style="margin-top:0">Payment Breakdown</h4>
              <div class="payment-line"><span>Cash</span><strong id="closeoutCashTotal">₱0.00</strong></div>
              <div class="payment-line"><span>GCash / E-wallet</span><strong id="closeoutGcashTotal">₱0.00</strong></div>
            </div>
            <div class="closeout-card">
              <button class="checkout-btn" id="exportCloseoutBtn" style="width:100%;justify-content:center">Generate End-of-Shift Report</button>
            </div>
          </aside>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal placeholder -->
  <div id="modalRoot"></div>

  <script>
    /* ----------------------
       Simple responsive POS JS
       - loads products (tries backend else uses mock)
       - search, filter
       - cart functionality (qty, remove)
       - checkout modal (simulate)
       - mock orders & closeout summary
    ----------------------- */

    (function(){
      // State
      let products = [];
      let categories = [];
      let cart = [];
      let orders = [];
      let useMock = false;
      let latestCloseoutSummary = null;
      const productNameLookup = {};
      const sizeNameLookup = {};
      let productCostMap = null;
      let productCostPromise = null;

      // DOM refs
      const productsGrid = document.getElementById('productsGrid');
      const categoryFilter = document.getElementById('categoryFilter');
      const globalSearch = document.getElementById('globalSearch');
      const cartItemsEl = document.getElementById('cartItems');
      const totalEl = document.getElementById('total');
      const checkoutBtn = document.getElementById('checkoutBtn');
      const clearCartBtn = document.getElementById('clearCartBtn');
      const cartBtn = document.getElementById('cartBtn');
      const cartBadge = document.getElementById('cartBadge');

      const navItems = document.querySelectorAll('.nav-item');
      const sections = { ProductsForm: document.getElementById('ProductsForm'), OrdersForm: document.getElementById('OrdersForm'), CloseoutForm: document.getElementById('CloseoutForm') };

      // Mock product data (used when backend not available)
      const mockProducts = [
        { productID:1, productName:'Espresso', categoryName:'Coffee', categoryID:1, image_url:'https://images.unsplash.com/photo-1511920170033-f8396924c348?w=800&q=60', sizes:[{sizeID:1,sizeName:'Single',price:60},{sizeID:2,sizeName:'Double',price:80}], isActive:1 },
        { productID:2, productName:'Cappuccino', categoryName:'Coffee', categoryID:1, image_url:'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=800&q=60', sizes:[{sizeID:3,sizeName:'Small',price:90},{sizeID:4,sizeName:'Large',price:120}], isActive:1 },
        { productID:3, productName:'Iced Latte', categoryName:'Cold Drinks', categoryID:2, image_url:'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=800&q=60', sizes:[{sizeID:5,sizeName:'Regular',price:100}], isActive:1 },
        { productID:4, productName:'Brown Sugar Latte', categoryName:'Specialty', categoryID:3, image_url:'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=800&q=60', sizes:[{sizeID:6,sizeName:'Regular',price:130}], isActive:1 },
        { productID:5, productName:'Mocha', categoryName:'Coffee', categoryID:1, image_url:'https://images.unsplash.com/photo-1507668077129-56e32842fceb?w=800&q=60', sizes:[{sizeID:7,sizeName:'Small',price:95},{sizeID:8,sizeName:'Large',price:140}], isActive:1 },
        { productID:6, productName:'Mango Smoothie', categoryName:'Fruit Drinks', categoryID:4, image_url:'https://images.unsplash.com/photo-1572448862527-68f2b52d1d11?w=800&q=60', sizes:[{sizeID:9,sizeName:'Regular',price:110}], isActive:1 }
      ];

      // Try to fetch products from backend, else fallback
      async function fetchProducts(){
        if (!useMock){
          try {
            if (window.ProductService) {
              const data = await ProductService.fetchProducts({ includeInactive: true, includeUnits: true });
              products = Array.isArray(data.products) ? data.products : Array.isArray(data) ? data : [];
            } else {
              const res = await fetch('db/products_getAll.php', {cache:'no-store'});
              if (!res.ok) throw new Error('Network response not ok');
              const data = await res.json();
              products = data.products || data;
            }
            products = normalizeProductList(products);
            updateProductLookups(products);
            if (!Array.isArray(products) || products.length === 0) throw new Error('No products');
            buildCategories();
            renderProducts();
            return;
          } catch (err) {
            console.warn('Backend products fetch failed, switching to mock', err);
            useMock = true;
          }
        }

        // Use mock data
        products = normalizeProductList(mockProducts.slice());
        updateProductLookups(products);
        buildCategories();
        renderProducts();
      }

      function buildCategories(){
        const unique = {};
        categories = [];
        products.forEach(p => {
          if (!unique[p.categoryID]) {
            unique[p.categoryID] = p.categoryName || 'Uncategorized';
            categories.push({categoryID: p.categoryID, categoryName: p.categoryName || 'Uncategorized'});
          }
        });
        updateCategorySelect();
      }

      function updateCategorySelect(){
        categoryFilter.innerHTML = '<option value="">All Categories</option>';
        categories.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.categoryID;
          opt.textContent = c.categoryName;
          categoryFilter.appendChild(opt);
        });
      }

      function normalizeProductList(list) {
        if (!Array.isArray(list)) return [];
        return list.map(product => ({
          ...product,
          sizes: normalizeSizes(product.sizes)
        }));
      }

      function normalizeSizes(rawSizes) {
        const sizeMap = new Map();
        if (Array.isArray(rawSizes)) {
          rawSizes.forEach(raw => {
            const sizeID = Number(raw.sizeID ?? raw.sizeId ?? raw.id);
            if (!Number.isFinite(sizeID)) return;
            const priceValue = Number(raw.price ?? raw.defaultPrice ?? raw.basePrice ?? raw.unitPrice ?? 0);
            const price = Number.isFinite(priceValue) ? priceValue : 0;
            let label = raw.sizeName ?? raw.name ?? raw.label ?? '';
            if (!label) {
              label = String(sizeID);
            }
            label = formatSizeLabel(label);
            const key = label.toLowerCase();
            const existing = sizeMap.get(key);
            if (!existing || price > existing.price || (existing.price === 0 && price > 0)) {
              sizeMap.set(key, {
                sizeID,
                sizeName: label,
                price
              });
            }
          });
        }
        return Array.from(sizeMap.values());
      }

      function updateProductLookups(list) {
        if (!Array.isArray(list)) return;
        list.forEach(product => {
          const productId = product.productID ?? product.id;
          if (productId) {
            productNameLookup[productId] = product.productName || product.name || `Product #${productId}`;
          }
          if (Array.isArray(product.sizes)) {
            product.sizes.forEach(size => {
              const sizeId = size.sizeID ?? size.sizeId ?? size.id;
              if (sizeId) {
                sizeNameLookup[sizeId] = size.sizeName || size.name || '';
              }
            });
          }
        });
      }

      function formatSizeLabel(label) {
        const value = String(label ?? '').trim();
        if (!value) return '';
        if (/^\d+(\.\d+)?$/.test(value)) {
          return `${value}oz`;
        }
        if (/^\d+(\.\d+)?\s*oz$/i.test(value)) {
          return value.replace(/\s+/, '').replace(/oz$/i, 'oz');
        }
        return value;
      }

      // Render products grid (filtered)
      function renderProducts(filtered){
        const list = filtered || products;
        productsGrid.innerHTML = '';
        if (!list || list.length === 0){
          productsGrid.innerHTML = '<div class="muted empty-state">No products found</div>';
          return;
        }
        list.forEach(p => {
          const card = document.createElement('div');
          card.className = 'product-card';
          card.setAttribute('data-id', p.productID);
          card.innerHTML = `
            <div class="product-meta">
              <div class="name">${escapeHtml(p.productName)}</div>
              <div class="category muted small">${escapeHtml(p.categoryName || '')}</div>
              <div class="sizes"></div>
            </div>
          `;
          const sizesDiv = card.querySelector('.sizes');
          const uniqueSizes = normalizeSizes(p.sizes);
          if (uniqueSizes.length){
            uniqueSizes.forEach(s => {
              const b = document.createElement('button');
              b.className = 'size-btn';
              b.textContent = `${s.sizeName || ''} — ₱${formatNumber(s.price)}`;
              b.addEventListener('click', (e) => {
                e.stopPropagation();
                addToCart(p, s);
              });
              sizesDiv.appendChild(b);
            });
          } else {
            const b = document.createElement('button');
            b.className = 'size-btn';
            b.textContent = `Add — ₱0.00`;
            b.addEventListener('click', (e) => {
              e.stopPropagation();
              addToCart(p, null);
            });
            sizesDiv.appendChild(b);
          }

          productsGrid.appendChild(card);
        });
      }

      // Utility: escape html
      function escapeHtml(s){ return (''+s).replace(/[&<>"'`]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[c]); }

      // Formatting
      function formatNumber(n){ return Number(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
      const peso = '\u20B1';
      function currency(n){ return peso + formatNumber(n); }
      function formatDateTime(ts){
        if(!ts) return '-';
        const d = new Date(ts);
        if (isNaN(d)) return ts;
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
      }

      function parseOrderTimestamp(raw) {
        if (!raw) return null;
        let value = raw;
        if (typeof value === 'string') {
          value = value.trim();
          if (value.length === 10 && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
            value += 'T00:00:00';
          } else if (value.includes(' ') && !value.includes('T')) {
            value = value.replace(' ', 'T');
          }
        }
        const date = new Date(value);
        if (isNaN(date) && typeof raw === 'string') {
          const fallback = new Date(raw);
          return isNaN(fallback) ? null : fallback;
        }
        return isNaN(date) ? null : date;
      }

      function isSameDay(dateA, dateB) {
        if (!(dateA instanceof Date) || !(dateB instanceof Date)) return false;
        return dateA.getFullYear() === dateB.getFullYear() &&
               dateA.getMonth() === dateB.getMonth() &&
               dateA.getDate() === dateB.getDate();
      }

      async function ensureProductCostMap() {
        if (productCostMap) return productCostMap;
        if (productCostPromise) return productCostPromise;
        productCostPromise = fetch('db/inventory_costing.php', { cache: 'no-store' })
          .then(res => res.json())
          .then(data => {
            productCostMap = new Map();
            if (Array.isArray(data)) {
              data.forEach(entry => {
                const productName = normalizeKeyPart(entry.Product || entry.product);
                const sizeLabel = normalizeSizeKey(entry.Size || entry.size);
                const costValue = Number(entry.Cost ?? entry.cost ?? 0);
                if (!productName) return;
                const key = `${productName}|${sizeLabel}`;
                productCostMap.set(key, costValue);
                if (!sizeLabel) {
                  productCostMap.set(`${productName}|`, costValue);
                }
              });
            }
            return productCostMap;
          })
          .catch(err => {
            console.warn('Failed to load product costing data:', err);
            productCostPromise = null;
            return null;
          });
        return productCostPromise;
      }

      function normalizeKeyPart(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim().toLowerCase();
      }

      function normalizeSizeKey(value) {
        const base = normalizeKeyPart(value);
        if (!base) return '';
        if (base.includes('oz')) return base;
        if (/^\d+(\.\d+)?$/.test(base)) {
          return `${base}oz`;
        }
        return base;
      }

      function getCostForProduct(productName, sizeLabel) {
        if (!productCostMap) return 0;
        const baseKey = `${normalizeKeyPart(productName)}|${normalizeSizeKey(sizeLabel)}`;
        if (productCostMap.has(baseKey)) {
          return Number(productCostMap.get(baseKey)) || 0;
        }
        const fallbackKey = `${normalizeKeyPart(productName)}|`;
        return Number(productCostMap.get(fallbackKey)) || 0;
      }

      function parseOrderItems(raw) {
        if (!raw) return [];
        try {
          const parsed = JSON.parse(raw);
          return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
          return [];
        }
      }

      // Cart logic
      function addToCart(product, size){
        // If product has sizes, size provided; else default price 0
        const item = {
          cartID: Date.now() + Math.floor(Math.random()*1000),
          productID: product.productID,
          name: product.productName,
          sizeID: size ? size.sizeID : null,
          sizeName: size ? size.sizeName : '',
          price: size ? Number(size.price) : (product.price ? Number(product.price) : 0),
          qty: 1,
          image: product.image_url || ''
        };
        // If same product+size exists -> increment qty
        const existing = cart.find(c => c.productID === item.productID && c.sizeID === item.sizeID);
        if (existing){
          existing.qty += 1;
        } else {
          cart.push(item);
        }
        renderCart();
      }

      function renderCart(){
        cartItemsEl.innerHTML = '';
        if (cart.length === 0){
          cartItemsEl.innerHTML = '<div class="muted empty-state">Cart is empty</div>';
          updateTotals();
          if (cartBadge) cartBadge.textContent = '0';
          return;
        }
        cart.forEach(ci => {
          const el = document.createElement('div');
          el.className = 'cart-item';
          el.innerHTML = `
            <div class="ci-info" style="flex:1;">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <div style="font-weight:600">${escapeHtml(ci.name)}</div>
                <div style="font-size:13px;color:var(--muted)">${ci.sizeName ? ci.sizeName + 'oz' : ''}</div>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                <div class="ci-controls">
                  <button class="qty-btn" data-action="decrease">-</button>
                  <input type="number" class="qty-input" value="${ci.qty}" min="1" style="padding:6px 8px;border-radius:8px;background:#fff;border:1px solid rgba(0,0,0,0.04);width:60px;text-align:center;">
                  <button class="qty-btn" data-action="increase">+</button>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:700">${currency(ci.price * ci.qty)}</div>
                  <button class="remove-btn small" data-action="remove">Remove</button>
                </div>
              </div>
            </div>
          `;
          // Buttons
          el.querySelector('[data-action=increase]').addEventListener('click', ()=> { ci.qty++; renderCart(); });
          el.querySelector('[data-action=decrease]').addEventListener('click', ()=> { if (ci.qty>1) ci.qty--; else removeFromCart(ci.cartID); renderCart(); });
          el.querySelector('[data-action=remove]').addEventListener('click', ()=> { removeFromCart(ci.cartID); });
          // Input event
          const qtyInput = el.querySelector('.qty-input');
          qtyInput.addEventListener('input', (e)=> {
            const val = parseInt(e.target.value);
            if (isNaN(val) || val < 1) {
              e.target.value = ci.qty; // revert
            } else {
              ci.qty = val;
              renderCart();
            }
          });
          cartItemsEl.appendChild(el);
        });
        updateTotals();
        if (cartBadge) cartBadge.textContent = cart.length;
      }

      function removeFromCart(cartID){
        cart = cart.filter(c=> c.cartID !== cartID);
        renderCart();
      }

      function clearCart(){
        cart = [];
        renderCart();
      }

      function updateTotals(){
        const subtotal = cart.reduce((s,i)=> s + (i.price * i.qty), 0);
        const discount = 0; // placeholder for discount logic
        const tax = 0; // tax removed
        const total = subtotal - discount + tax;

        totalEl.textContent = currency(total);
      }

      // Toast notification
      function showToast(message, type = 'error') {
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'success' ? 'success' : ''}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
          toast.classList.remove('show');
          setTimeout(() => document.body.removeChild(toast), 300);
        }, 3000);
      }

      // Inventory check
      async function checkInventory(){
        try {
          const res = await fetch('db/inventory_get.php');
          if (!res.ok) throw new Error('Failed to fetch inventory');
          const inventory = await res.json();
          const sizeGroups = {};
          cart.forEach(item => {
            if (item.sizeID) {
              if (!sizeGroups[item.sizeID]) sizeGroups[item.sizeID] = { qty: 0, sizeName: item.sizeName };
              sizeGroups[item.sizeID].qty += item.qty;
            }
          });
          for (const sizeID in sizeGroups) {
            const group = sizeGroups[sizeID];
          const cupItem = inventory.find(i => i.InventoryName === 'Cup' && i.Size === group.sizeName && i.Unit === 'Ounce');
            if (cupItem && cupItem['Current Stock'] < group.qty) {
              showToast(`Insufficient stock for ${group.sizeName}oz cups: need ${group.qty}, have ${cupItem['Current Stock']}`);
              return false;
            }
          }
          return true;
        } catch (err) {
          console.error('Inventory check failed:', err);
          showToast('Unable to check inventory. Please try again.');
          return false;
        }
      }

      // Checkout
      async function openCheckout(){
        if (cart.length === 0) { alert('Cart is empty'); return; }
        const hasStock = await checkInventory();
        if (!hasStock) return;
        const subtotal = cart.reduce((s,i)=> s + (i.price * i.qty), 0);
        const tax = 0; // tax removed
        const total = subtotal + tax;
        const modal = createModal(`
          <h3>Checkout</h3>
          <div style="margin-top:8px">
            <div class="small muted">Items: ${cart.length}</div>
            <div style="display:flex;justify-content:space-between;font-size:18px;margin-top:6px"><strong>Total</strong><div>${currency(total)}</div></div>
          </div>
          <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
            <button id="payCash" class="pill">Pay Cash</button>
            <button id="payGcash" class="pill">Pay Gcash</button>
            <button id="cancelModal" class="pill">Cancel</button>
          </div>
        `);

        document.getElementById('cancelModal').addEventListener('click', ()=> modal.close());
        document.getElementById('payCash').addEventListener('click', ()=> processPayment('Cash', total, modal));
        document.getElementById('payGcash').addEventListener('click', ()=> processPayment('Gcash', total, modal));
      }

      async function processPayment(method, totalAmount, modal){
        try {
          // Validate cart data before sending
          if (!cart || cart.length === 0) {
            throw new Error('Cart is empty');
          }

          // Prepare cart items with validation
          const cartItems = cart.map(item => {
            if (!item.productID || !item.price || !item.qty) {
              throw new Error(`Invalid cart item: ${JSON.stringify(item)}`);
            }
            return {
              productID: item.productID,
              sizeID: item.sizeID || null,
              quantity: item.qty,
              unitPrice: item.price,
              totalPrice: item.price * item.qty,
              addons: []
            };
          });

          console.log('Sending cart data:', cartItems);

          const formData = new FormData();
          formData.append('cartItems', JSON.stringify(cartItems));
          formData.append('paymentMethod', method);
          formData.append('cashReceived', totalAmount.toString());
          formData.append('discountType', 'none');
          formData.append('discountPercentage', '0');

          console.log('Sending checkout request...');

          const res = await fetch('db/checkout_process.php', {
            method: 'POST',
            body: formData
          });

          console.log('Response status:', res.status);
          console.log('Response headers:', res.headers);

          if (!res.ok) {
            const errorText = await res.text();
            console.error('HTTP Error:', res.status, errorText);
            throw new Error(`HTTP ${res.status}: ${errorText}`);
          }

          const data = await res.json();
          console.log('Response data:', data);

          if (data.status === 'success') {
            showToast('Order completed successfully!', 'success');
            modal.close();
            clearCart();
            await loadCloseoutSummary();
            await loadOrders(); // Update the Orders table dynamically
          } else {
            console.error('Backend error:', data.message);
            showToast(data.message || 'Checkout failed');
          }
        } catch (err) {
          console.error('Checkout error details:', err);
          console.error('Error stack:', err.stack);
          showToast(`Checkout failed: ${err.message}`);
        }
      }

      async function fetchOrdersData() {
        const response = await fetch('db/orders_get.php');
        const data = await response.json();
        if (data.status !== 'success') {
          throw new Error(data.message || 'Failed to load orders');
        }
        return [...(data.pending || []), ...(data.completed || [])];
      }

      async function loadCloseoutSummary() {
        try {
          const data = await fetchOrdersData();
          orders = data;
          renderOrders();
          await buildCloseoutSummary(orders);
        } catch (error) {
          console.error('Error loading closeout summary:', error);
          showToast('Error loading closeout summary', 'error');
        }
      }

      async function loadOrders(){
        try {
          const data = await fetchOrdersData();
          orders = data;
          renderOrders();
          await buildCloseoutSummary(orders);
        } catch (error) {
          console.error('Error loading orders:', error);
          showToast('Error loading orders', 'error');
        }
      }

      async function buildCloseoutSummary(orderList = orders) {
        const totalEl = document.getElementById('closeoutTotalOrders');
        if (!totalEl) return;
        const grossEl = document.getElementById('closeoutGrossSales');
        const netEl = document.getElementById('closeoutNetSales');
        const timeInEl = document.getElementById('closeoutShiftStart');
        const timeOutEl = document.getElementById('closeoutShiftEnd');
        const cashEl = document.getElementById('closeoutCashTotal');
        const gcashEl = document.getElementById('closeoutGcashTotal');
        const salesBody = document.getElementById('closeoutSalesBody');

        const today = new Date();
        today.setHours(0,0,0,0);

        const todaysOrders = (orderList || []).filter(order => {
          const dateObj = parseOrderTimestamp(order.created_at || order.createdAt);
          return dateObj ? isSameDay(dateObj, today) : false;
        });

        if (todaysOrders.length === 0) {
          latestCloseoutSummary = null;
          totalEl.textContent = '0';
          grossEl.textContent = currency(0);
          netEl.textContent = currency(0);
          if (timeInEl) timeInEl.textContent = '-';
          if (timeOutEl) timeOutEl.textContent = '-';
          if (cashEl) cashEl.textContent = currency(0);
          if (gcashEl) gcashEl.textContent = currency(0);
          if (salesBody) salesBody.innerHTML = '<tr><td colspan="5" class="muted">No sales data for today</td></tr>';
          return;
        }

        updateProductLookups(products);
        await ensureProductCostMap();

        let gross = 0;
        let totalCost = 0;
        let firstOrderTime = null;
        let lastOrderTime = null;
        const paymentTotals = { cash: 0, gcash: 0 };
        const productSales = new Map();

        todaysOrders.forEach(order => {
          const amount = Number(order.totalAmount || 0);
          gross += amount;
          const created = parseOrderTimestamp(order.created_at || order.createdAt);
          if (created) {
            if (!firstOrderTime || created < firstOrderTime) firstOrderTime = created;
            if (!lastOrderTime || created > lastOrderTime) lastOrderTime = created;
          }

          const method = (order.paymentMethod || '').toString().toLowerCase();
          if (method.includes('gcash') || method.includes('wallet')) {
            paymentTotals.gcash += amount;
          } else if (method.includes('cash')) {
            paymentTotals.cash += amount;
          }

          const items = parseOrderItems(order.orderSummaryRaw || order.orderSummary);
          items.forEach(item => {
            const qty = Number(item.quantity ?? item.qty ?? 1) || 1;
            const unitPrice = Number(item.unitPrice ?? item.price ?? 0) || 0;
            const productId = item.productID ?? item.productId ?? item.id ?? 0;
            const sizeId = item.sizeID ?? item.sizeId ?? null;
            const productName = productNameLookup[productId] || item.productName || `Product #${productId || ''}`;
            const sizeLabel = sizeNameLookup[sizeId] || item.sizeName || '';
            const lineGross = qty * unitPrice;
            const unitCost = getCostForProduct(productName, sizeLabel);
            const lineCost = unitCost * qty;
            totalCost += lineCost;

            const key = productName;
            const existing = productSales.get(key) || { name: productName, qty: 0, gross: 0, cost: 0 };
            existing.qty += qty;
            existing.gross += lineGross;
            existing.cost += lineCost;
            productSales.set(key, existing);
          });
        });

        const net = gross - totalCost;
        totalEl.textContent = todaysOrders.length.toString();
        grossEl.textContent = currency(gross);
        netEl.textContent = currency(net);
        if (timeInEl) timeInEl.textContent = firstOrderTime ? formatDateTime(firstOrderTime) : '-';
        if (timeOutEl) timeOutEl.textContent = lastOrderTime ? formatDateTime(lastOrderTime) : '-';
        if (cashEl) cashEl.textContent = currency(paymentTotals.cash);
        if (gcashEl) gcashEl.textContent = currency(paymentTotals.gcash);

        const salesRows = [];
        if (salesBody) {
          const rows = Array.from(productSales.values()).sort((a, b) => b.gross - a.gross);
          if (!rows.length) {
            salesBody.innerHTML = '<tr><td colspan="5" class="muted">No sales data for today</td></tr>';
          } else {
            salesBody.innerHTML = '';
            rows.forEach(row => {
              const netValue = row.gross - row.cost;
              salesRows.push({
                name: row.name,
                qty: row.qty,
                gross: row.gross,
                cost: row.cost,
                net: netValue
              });
              const tr = document.createElement('tr');
              tr.innerHTML = `
                <td>${escapeHtml(row.name)}</td>
                <td>${row.qty}</td>
                <td>${currency(row.gross)}</td>
                <td>${currency(row.cost)}</td>
                <td>${currency(netValue)}</td>
              `;
              salesBody.appendChild(tr);
            });
          }
        }

        latestCloseoutSummary = {
          totalOrders: todaysOrders.length,
          gross,
          net,
          payments: paymentTotals,
          shiftStart: firstOrderTime ? firstOrderTime.toISOString() : null,
          shiftEnd: lastOrderTime ? lastOrderTime.toISOString() : null,
          sales: salesRows
        };
      }

      function renderOrders(){
        const tbody = document.getElementById('ordersTableBody');
        tbody.innerHTML = '';
        if (orders.length === 0){
          tbody.innerHTML = '<tr><td colspan="6" class="muted">No orders yet</td></tr>';
          return;
        }
        const searchTerm = (orderSearchInput?.value || '').toLowerCase();
        const selectedRange = currentDateRange;
        let rendered = 0;
        orders.forEach(o => {
          if (!matchesDateFilter(o, selectedRange)) {
            return;
          }
          if (!matchesOrderSearch(o, searchTerm)) {
            return;
          }
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${escapeHtml(o.orderID || o.id || 'N/A')}</td>
                          <td>${o.items || 'No items'}</td>
                          <td>${currency(o.totalAmount || o.total || 0)}</td>
                          <td>${escapeHtml(o.status || 'pending')}</td>
                          <td>${escapeHtml(o.referenceNumber || o.orderID || o.id || 'N/A')}</td>
                          <td>${escapeHtml(formatDateTime(o.created_at || o.createdAt))}</td>`;
          tr.addEventListener('click', () => openOrderModal(o));
          tbody.appendChild(tr);
          rendered++;
        });
        if (!rendered) {
          tbody.innerHTML = '<tr><td colspan="6" class="muted">No orders found for this filter</td></tr>';
        }
      }

      const orderSearchInput = document.getElementById('orderSearch');
      let currentDateRange = 'today';
      setupDateFilter();

      if (orderSearchInput) {
        orderSearchInput.addEventListener('input', () => {
          renderOrders();
        });
      }

      const exportCloseoutBtn = document.getElementById('exportCloseoutBtn');
      if (exportCloseoutBtn) {
        exportCloseoutBtn.addEventListener('click', downloadCloseoutReport);
      }

      function matchesOrderSearch(order, term) {
        if (!term) return true;
        const orderId = (order.orderID || order.id || '').toString().toLowerCase();
        const items = (order.items || '').toLowerCase();
        const reference = (order.referenceNumber || order.orderID || order.id || '').toString().toLowerCase();
        return orderId.includes(term) || items.includes(term) || reference.includes(term);
      }

      function openOrderModal(order) {
        const itemsHtml = order.items || 'No items';
        const modal = createModal(`
          <h3>Order Details</h3>
          <div class="order-detail">
            <div><strong>Order ID:</strong> ${escapeHtml(order.orderID || order.id || 'N/A')}</div>
            <div><strong>Date & Time:</strong> ${escapeHtml(formatDateTime(order.created_at || order.createdAt))}</div>
            <div><strong>Payment Method:</strong> ${escapeHtml((order.paymentMethod || 'Unknown').toUpperCase())}</div>
            <div><strong>Total:</strong> ${currency(order.totalAmount || 0)}</div>
            <div><strong>Reference #:</strong> ${escapeHtml(order.referenceNumber || order.orderID || order.id || 'N/A')}</div>
            <div>
              <strong>Items:</strong>
              <div class="order-items">${itemsHtml}</div>
            </div>
          </div>
          <div style="text-align:right;margin-top:12px;">
            <button class="pill" id="closeOrderDetail">Close</button>
          </div>
        `);
        document.getElementById('closeOrderDetail').addEventListener('click', () => modal.close());
      }

      function setupDateFilter() {
        const buttons = document.querySelectorAll('.order-date-filter button');
        buttons.forEach(btn => {
          if (btn.dataset.range === currentDateRange) {
            btn.classList.add('active');
          }
          btn.addEventListener('click', () => {
            currentDateRange = btn.dataset.range;
            buttons.forEach(b => b.classList.toggle('active', b === btn));
            renderOrders();
          });
        });
      }

      function matchesDateFilter(order, range) {
        if (range === 'all') return true;
        const created = parseOrderTimestamp(order.created_at || order.createdAt || order.timestamp);
        if (!created) return true;
        const today = new Date();
        today.setHours(0,0,0,0);
        const orderDate = new Date(created);
        orderDate.setHours(0,0,0,0);

        if (range === 'today') {
          return orderDate.getTime() === today.getTime();
        }
        if (range === 'yesterday') {
          const yesterday = new Date(today);
          yesterday.setDate(yesterday.getDate() - 1);
          return orderDate.getTime() === yesterday.getTime();
        }
        if (range === 'week') {
          const startOfWeek = new Date(today);
          const day = startOfWeek.getDay();
          const diff = day === 0 ? -6 : 1 - day;
          startOfWeek.setDate(startOfWeek.getDate() + diff);
          startOfWeek.setHours(0,0,0,0);
          const endOfWeek = new Date(startOfWeek);
          endOfWeek.setDate(endOfWeek.getDate() + 7);
          return orderDate >= startOfWeek && orderDate < endOfWeek;
        }
        return true;
      }

      function toCSVField(value) {
        if (value === null || value === undefined) {
          return '""';
        }
        const stringValue = String(value).replace(/"/g, '""');
        return `"${stringValue}"`;
      }

      async function downloadCloseoutReport() {
        if (!latestCloseoutSummary) {
          await buildCloseoutSummary(orders);
        }
        if (!latestCloseoutSummary) {
          showToast('No shift data available to export', 'warning');
          return;
        }

        const summary = latestCloseoutSummary;
        const shiftDate = summary.shiftStart
          ? summary.shiftStart.slice(0, 10)
          : new Date().toISOString().slice(0, 10);
        const formatTimeOnly = (value) => {
          if (!value) return '-';
          const d = new Date(value);
          return isNaN(d) ? '-' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        const sheetData = [
          ['Close-Out / End of Shift Report', ''],
          [''],
          ['Shift Date:', shiftDate],
          ['Shift Time In:', formatTimeOnly(summary.shiftStart)],
          ['Shift Time Out:', formatTimeOnly(summary.shiftEnd)],
          [''],
          ['Total Orders (Today):', summary.totalOrders],
          ['Gross Sales (Today):', formatNumber(summary.gross || 0)],
          ['Net Sales (Today):', formatNumber(summary.net || 0)],
          [''],
          ['Payment Breakdown:', ''],
          ['Cash', formatNumber(summary.payments.cash || 0)],
          ['GCash / E-wallet', formatNumber(summary.payments.gcash || 0)],
          [''],
          ['Sales Breakdown by Product', ''],
          ['Product', 'Qty Sold', 'Gross', 'Cost', 'Net'],
          ...summary.sales.map(row => [
            row.name,
            row.qty,
            formatNumber(row.gross || 0),
            formatNumber(row.cost || 0),
            formatNumber(row.net || 0)
          ])
        ];

        // Convert to CSV format
        const csvContent = sheetData
          .map(row => row.map(toCSVField).join(','))
          .join('\n');

        // Create and download CSV file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `closeout_${shiftDate}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        
        showToast('End-of-shift report exported successfully', 'success');
      }

      // Modal helper
      function createModal(innerHtml){
        const wrapper = document.createElement('div');
        wrapper.className = 'modal-backdrop';
        wrapper.innerHTML = `<div class="modal">${innerHtml}</div>`;
        document.getElementById('modalRoot').appendChild(wrapper);
        wrapper.addEventListener('click', (e)=> { if (e.target === wrapper) close(); });
        function close(){ wrapper.remove(); }
        return { el: wrapper, close };
      }

      // Search and filters
      function applyFilters(){
        const q = (globalSearch.value || '').toLowerCase();
        const cat = categoryFilter.value;
        let filtered = products.filter(p => {
          const matchesQ = p.productName.toLowerCase().includes(q) || (p.categoryName && p.categoryName.toLowerCase().includes(q));
          const matchesCat = !cat || p.categoryID == cat;
          return matchesQ && matchesCat;
        });
        renderProducts(filtered);
      }

      // Global search: switch view to orders if query looks like order id; else filter products
      globalSearch.addEventListener('input', (e)=>{
        const v = (e.target.value || '').trim();
        if (!v) return;
        // If starts with ORD -> switch to orders view and filter table
        if (/^ord/i.test(v)){
          showSection('OrdersForm');
          // filter orders table simple
          const rows = Array.from(document.querySelectorAll('#ordersTableBody tr'));
          rows.forEach(r => {
            const matches = r.innerText.toLowerCase().includes(v.toLowerCase());
            r.style.display = matches ? '' : 'none';
          });
        } else {
          // filter products
          applyFilters();
          showSection('ProductsForm');
        }
      });

      // UI events
      categoryFilter.addEventListener('change', applyFilters);
      clearCartBtn.addEventListener('click', ()=> { if (confirm('Clear cart?')) clearCart(); });
      checkoutBtn.addEventListener('click', openCheckout);
      // Cart button
      cartBtn.addEventListener('click', () => {
        showSection('ProductsForm');
        const cartEl = document.querySelector('.cart');
        if (cartEl) {
          cartEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });

      // Navigation
      navItems.forEach(n => {
        n.addEventListener('click', ()=> {
          navItems.forEach(x=>x.classList.remove('active'));
          n.classList.add('active');
          showSection(n.dataset.section);
        });
      });
      function showSection(id){
        Object.keys(sections).forEach(k => sections[k].style.display = (k===id) ? 'block' : 'none');
      }

      // Sidebar toggle (mobile)
      document.getElementById('sidebarToggle').addEventListener('click', ()=>{
        document.getElementById('sidebar').style.display = document.getElementById('sidebar').style.display === 'none' ? '' : 'none';
      });

      // Sign out functionality
      document.getElementById('signOutBtn').addEventListener('click', async ()=> {
        if (confirm('Sign out?')) {
          try {
            // Call logout endpoint to clear session
            const response = await fetch('db/logout.php', { method: 'POST' });
            // Redirect to login page regardless of response
            window.location.href = 'loginRegister.html';
          } catch (error) {
            console.error('Logout error:', error);
            // Still redirect even if logout fails
            window.location.href = 'loginRegister.html';
          }
        }
      });



      // Init
      (function init(){
        // try backend first, fallback to mock data if unavailable
        fetchProducts();
        renderCart();
        loadOrders(); // Load orders from backend instead of using mock data
      })();

      // small helper fallback: attempt to fetch categories endpoint (if backend available)
      async function tryLoadCategories(){
        if (!useMock){
          try {
            if (window.DataService) {
              categories = await DataService.fetchCategories();
            } else {
              const res = await fetch('db/categories_getAll.php');
              if (!res.ok) throw new Error('no cat');
              categories = await res.json();
            }
            if (Array.isArray(categories)) {
              updateCategorySelect();
            }
          } catch(e) {
            console.warn('Category preload failed:', e);
          }
        }
      }

      // expose some helpers for integration
      window.POS = {
        addToCart: addToCart,
        clearCart: clearCart,
        getCart: ()=> cart,
        setUseMock: (b)=> { useMock = !!b; fetchProducts(); }
      };

    })();
  </script>
</body>
</html>



