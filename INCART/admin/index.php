<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$adminTitle = "Admin Dashboard";
$adminPage = "dashboard";

require_once __DIR__ . '/includes/header.php';

// Calculate Key Performance Indicators (KPIs)
$totalProducts   = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalCustomers  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalOrders     = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalSales      = (float)$db->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$pendingOrders   = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();
$lowStockCount   = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5")->fetchColumn();

// Fetch Recent Orders (Top 5)
$recentOrders = $db->query("
  SELECT o.*, u.first_name, u.last_name 
  FROM orders o 
  JOIN users u ON o.user_id = u.id 
  ORDER BY o.created_at DESC 
  LIMIT 5
")->fetchAll();

// Fetch Low Stock Alert Products (Top 5)
$lowStockProds = $db->query("
  SELECT p.*, c.name as category_name 
  FROM products p 
  JOIN categories c ON p.category_id = c.id 
  WHERE p.stock_quantity <= 5 
  ORDER BY p.stock_quantity ASC 
  LIMIT 5
")->fetchAll();

// Fetch Recent Contact Messages (Top 3)
$recentMessages = [];
try {
    $recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 3")->fetchAll();
} catch (Exception $e) {
    $recentMessages = [];
}
?>

<!-- QUICK ACTIONS BAR -->
<div class="quick-actions-bar">
  <a href="products.php" class="quick-action-btn">
    <i class="fas fa-plus-circle"></i> Add New Product
  </a>
  <a href="orders.php" class="quick-action-btn">
    <i class="fas fa-shopping-bag"></i> View Pending Orders (<?php echo $pendingOrders; ?>)
  </a>
  <a href="stock.php" class="quick-action-btn">
    <i class="fas fa-boxes"></i> Quick Stock Restock
  </a>
  <a href="categories.php" class="quick-action-btn">
    <i class="fas fa-folder-plus"></i> Manage Categories
  </a>
</div>

<!-- KPI METRICS GRID -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-data">
      <p>Total Revenue</p>
      <h3><?php echo format_price($totalSales); ?></h3>
    </div>
    <div class="kpi-icon bg-green"><i class="fas fa-coins"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <p>Total Orders</p>
      <h3><?php echo number_format($totalOrders); ?></h3>
    </div>
    <div class="kpi-icon bg-blue"><i class="fas fa-shopping-cart"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <p>Pending Orders</p>
      <h3 style="color:#c2410c;"><?php echo number_format($pendingOrders); ?></h3>
    </div>
    <div class="kpi-icon bg-orange"><i class="fas fa-clock"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <p>Active Products</p>
      <h3><?php echo number_format($totalProducts); ?></h3>
    </div>
    <div class="kpi-icon bg-purple"><i class="fas fa-box"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <p>Low Stock Items</p>
      <h3 style="color:#dc2626;"><?php echo number_format($lowStockCount); ?></h3>
    </div>
    <div class="kpi-icon bg-red"><i class="fas fa-exclamation-triangle"></i></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-data">
      <p>Registered Customers</p>
      <h3><?php echo number_format($totalCustomers); ?></h3>
    </div>
    <div class="kpi-icon bg-teal"><i class="fas fa-users"></i></div>
  </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
  
  <!-- RECENT ORDERS TABLE -->
  <div class="admin-card">
    <div class="admin-card-title">
      <span><i class="fas fa-list-alt" style="color:var(--admin-primary);"></i> Recent Customer Orders</span>
      <a href="orders.php" class="btn btn-outline btn-sm">View All Orders &raquo;</a>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="6" style="text-align:center; color:#777;">No customer orders found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $ord): 
              $statusClass = 'status-' . strtolower(str_replace(' ', '-', $ord['order_status']));
            ?>
              <tr>
                <td><strong><?php echo sanitize($ord['order_number']); ?></strong></td>
                <td><?php echo sanitize($ord['first_name'] . ' ' . $ord['last_name']); ?></td>
                <td><strong><?php echo format_price($ord['grand_total']); ?></strong></td>
                <td>
                  <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo sanitize($ord['order_status']); ?>
                  </span>
                </td>
                <td><?php echo date('M d, Y', strtotime($ord['created_at'])); ?></td>
                <td>
                  <a href="orders.php?view=<?php echo $ord['id']; ?>" class="btn btn-outline btn-sm">Manage</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RIGHT COLUMN: LOW STOCK & RECENT MESSAGES -->
  <div>
    <!-- LOW STOCK ALERT TABLE -->
    <div class="admin-card">
      <div class="admin-card-title">
        <span style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> Low Stock Alerts</span>
        <a href="stock.php" class="btn btn-outline btn-sm">Restock &raquo;</a>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Stock</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lowStockProds)): ?>
              <tr><td colspan="3" style="text-align:center; color:#777; padding:15px;">All items sufficiently stocked!</td></tr>
            <?php else: ?>
              <?php foreach ($lowStockProds as $p): ?>
                <tr>
                  <td><strong><?php echo sanitize($p['name']); ?></strong></td>
                  <td><span style="color:#dc2626; font-weight:800;"><?php echo $p['stock_quantity']; ?> left</span></td>
                  <td><a href="stock.php" class="btn btn-accent btn-sm">Restock</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- RECENT MESSAGES CARD -->
    <?php if (!empty($recentMessages)): ?>
      <div class="admin-card">
        <div class="admin-card-title">
          <span><i class="fas fa-envelope" style="color:#0288d1;"></i> Customer Inquiries</span>
          <a href="messages.php" class="btn btn-outline btn-sm">All Messages &raquo;</a>
        </div>
        <div>
          <?php foreach ($recentMessages as $msg): ?>
            <div style="padding:10px 0; border-bottom:1px solid #e2e8f0;">
              <div style="display:flex; justify-between; align-items:center;">
                <strong style="font-size:13px; color:var(--admin-text-main);"><?php echo sanitize($msg['name']); ?></strong>
                <span style="font-size:11px; color:#94a3b8;"><?php echo date('M d', strtotime($msg['created_at'])); ?></span>
              </div>
              <div style="font-size:12px; font-weight:600; color:var(--admin-primary); margin:2px 0;"><?php echo sanitize($msg['subject']); ?></div>
              <div style="font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:240px;"><?php echo sanitize($msg['message']); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
