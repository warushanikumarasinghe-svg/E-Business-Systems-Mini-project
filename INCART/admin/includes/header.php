<?php
require_once __DIR__ . '/../../includes/functions.php';
require_admin();
$db = getDB();

$adminUser = current_user();
$adminPage = $adminPage ?? 'dashboard';

// Quick Notification Counts for Sidebar Badges
$pendingOrdersCount = 0;
$lowStockBadgeCount = 0;
$pendingReviewsCount = 0;
$unreadMessagesCount = 0;

try {
    $pendingOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();
    $lowStockBadgeCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1")->fetchColumn();
    $pendingReviewsCount = (int)$db->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
    $unreadMessagesCount = (int)$db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
} catch (Exception $e) {
    // Graceful fallback if any query fails
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($adminTitle) ? sanitize($adminTitle) . ' - InCart Admin' : 'InCart Admin Portal'; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

  <!-- ADMIN SIDEBAR NAVIGATION -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
      <i class="fas fa-shopping-basket"></i>
      <span>InCart</span>
      <span class="badge-admin">Admin</span>
    </div>

    <div class="admin-menu-scroll">
      <div class="admin-menu-label">Main Navigation</div>
      <ul class="admin-menu">
        <li class="<?php echo $adminPage === 'dashboard' ? 'active' : ''; ?>">
          <a href="index.php">
            <span class="link-left"><i class="fas fa-chart-line"></i> Dashboard</span>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'orders' ? 'active' : ''; ?>">
          <a href="orders.php">
            <span class="link-left"><i class="fas fa-shopping-cart"></i> Orders</span>
            <?php if ($pendingOrdersCount > 0): ?>
              <span class="menu-badge"><?php echo $pendingOrdersCount; ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>

      <div class="admin-menu-label">Inventory & Catalog</div>
      <ul class="admin-menu">
        <li class="<?php echo $adminPage === 'products' ? 'active' : ''; ?>">
          <a href="products.php">
            <span class="link-left"><i class="fas fa-box"></i> Products</span>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'stock' ? 'active' : ''; ?>">
          <a href="stock.php">
            <span class="link-left"><i class="fas fa-warehouse"></i> Stock Management</span>
            <?php if ($lowStockBadgeCount > 0): ?>
              <span class="menu-badge" style="background:#f57c00;"><?php echo $lowStockBadgeCount; ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'categories' ? 'active' : ''; ?>">
          <a href="categories.php">
            <span class="link-left"><i class="fas fa-th-large"></i> Categories</span>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'brands' ? 'active' : ''; ?>">
          <a href="brands.php">
            <span class="link-left"><i class="fas fa-tags"></i> Brands</span>
          </a>
        </li>
      </ul>

      <div class="admin-menu-label">Users & Feedback</div>
      <ul class="admin-menu">
        <li class="<?php echo $adminPage === 'customers' ? 'active' : ''; ?>">
          <a href="customers.php">
            <span class="link-left"><i class="fas fa-users"></i> Customers</span>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'reviews' ? 'active' : ''; ?>">
          <a href="reviews.php">
            <span class="link-left"><i class="fas fa-star"></i> Reviews</span>
            <?php if ($pendingReviewsCount > 0): ?>
              <span class="menu-badge" style="background:#7b1fa2;"><?php echo $pendingReviewsCount; ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="<?php echo $adminPage === 'messages' ? 'active' : ''; ?>">
          <a href="messages.php">
            <span class="link-left"><i class="fas fa-envelope"></i> Contact Messages</span>
            <?php if ($unreadMessagesCount > 0): ?>
              <span class="menu-badge" style="background:#0288d1;"><?php echo $unreadMessagesCount; ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>

      <div class="admin-menu-label" style="margin-top: 15px;">System & Store</div>
      <ul class="admin-menu">
        <li>
          <a href="../index.php" target="_blank">
            <span class="link-left"><i class="fas fa-external-link-alt"></i> View Live Store</span>
          </a>
        </li>
        <li>
          <a href="../logout.php" style="color: #f87171;">
            <span class="link-left"><i class="fas fa-sign-out-alt" style="color:#f87171;"></i> Log Out</span>
          </a>
        </li>
      </ul>
    </div>
  </aside>

  <!-- ADMIN MAIN CONTAINER -->
  <div class="admin-main">
    
    <!-- ADMIN TOPBAR HEADER -->
    <header class="admin-header">
      <div class="admin-header-title">
        <button class="sidebar-toggle-btn" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
          <i class="fas fa-bars"></i>
        </button>
        <h2><?php echo isset($adminTitle) ? sanitize($adminTitle) : 'Dashboard Overview'; ?></h2>
      </div>

      <div class="admin-user-profile">
        <div class="admin-user-badge">
          <div class="admin-avatar">
            <i class="fas fa-user-shield"></i>
          </div>
          <span><?php echo sanitize($adminUser['name']); ?></span>
        </div>
      </div>
    </header>

    <div class="admin-content">
      <?php echo display_flash_messages(); ?>
