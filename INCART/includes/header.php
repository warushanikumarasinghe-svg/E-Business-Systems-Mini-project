<?php
require_once __DIR__ . '/functions.php';
$db = getDB();

// Fetch active categories for Header Mega Menu
$catStmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$cartCount = get_cart_count();
$wishlistCount = get_wishlist_count();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' - InCart Grocery' : 'InCart - Fresh Groceries Delivered to Your Doorstep'; ?></title>
  <meta name="description" content="Shop fresh produce, dairy, bakery, beverages and household essentials online at best prices with fast delivery in Sri Lanka.">
  <!-- Google Fonts & FontAwesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/INCART/assets/css/style.css">
</head>
<body>

  <!-- TOP BAR -->
  <div class="top-bar">
    <div class="container">
      <div class="top-bar-left">
        <span>🚚 <strong>Free Delivery</strong> on orders over LKR 5,000</span>
      </div>
      <div class="top-bar-right">
        <span><i class="fas fa-globe"></i> English</span>
        <a href="about.php"><i class="fas fa-info-circle"></i> Help & Support</a>
        <a href="track-order.php"><i class="fas fa-truck"></i> Track Order</a>
      </div>
    </div>
  </div>

  <!-- MAIN HEADER -->
  <header class="main-header">
    <div class="container">
      <div class="header-wrapper">

        <!-- LOGO -->
<a href="index.php" class="logo">
    <img src="/INCART/assets/images/logo/incart_logo.png" alt="InCart Logo">
</a>

        <!-- GLOBAL SEARCH BAR -->
        <div class="header-search">
          <form action="shop.php" method="GET" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="Search products, brands and groceries..." value="<?php echo isset($_GET['search']) ? sanitize($_GET['search']) : ''; ?>" required>
            <button type="submit" class="search-btn">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>

        <!-- HEADER ACTIONS -->
        <div class="header-actions">
          <a href="contact.php" class="action-item" title="Support">
            <i class="fas fa-headset"></i>
            <span>Support</span>
          </a>

          <a href="wishlist.php" class="action-item" title="Wishlist">
            <i class="far fa-heart"></i>
            <span>Wishlist</span>
            <span class="badge" id="headerWishlistBadge"><?php echo $wishlistCount; ?></span>
          </a>

          <?php if ($user): ?>
            <div class="action-item has-dropdown">
              <a href="account.php" class="action-item">
                <i class="far fa-user"></i>
                <span><?php echo sanitize($user['name']); ?></span>
              </a>
              <div class="dropdown-menu" style="right:0; left:auto;">
                <a href="account.php"><i class="fas fa-user-circle"></i> My Account</a>
                <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                <?php if (is_admin()): ?>
                  <a href="admin/index.php" style="color:var(--primary); font-weight:700;"><i class="fas fa-user-shield"></i> Admin Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              </div>
            </div>
          <?php else: ?>
            <a href="login.php" class="action-item">
              <i class="far fa-user"></i>
              <span>Account</span>
            </a>
          <?php endif; ?>

          <a href="cart.php" class="action-item" title="Shopping Cart">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
            <span class="badge" id="headerCartBadge"><?php echo $cartCount; ?></span>
          </a>
        </div>

      </div>
    </div>
  </header>

  <!-- NAVIGATION BAR -->
  <nav class="nav-bar">
    <div class="container">
      <div class="nav-wrapper">
        <ul class="nav-links">
          <li class="<?php echo (!isset($currentPage) || $currentPage == 'home') ? 'active' : ''; ?>">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
          </li>
          <li class="<?php echo (isset($currentPage) && $currentPage == 'shop') ? 'active' : ''; ?>">
            <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
          </li>

          <!-- CATEGORIES MEGA DROPDOWN -->
          <li class="has-dropdown <?php echo (isset($currentPage) && $currentPage == 'category') ? 'active' : ''; ?>">
            <a href="shop.php"><i class="fas fa-th-large"></i> Categories <i class="fas fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="dropdown-menu">
              <?php foreach ($categories as $cat): ?>
                <a href="shop.php?category=<?php echo $cat['id']; ?>">
                  <span><i class="fas <?php echo sanitize($cat['icon']); ?>"></i> <?php echo sanitize($cat['name']); ?></span>
                  <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                </a>
              <?php endforeach; ?>
            </div>
          </li>

          <li class="<?php echo (isset($currentPage) && $currentPage == 'deals') ? 'active' : ''; ?>">
            <a href="shop.php?discount=1" style="color:#ffc107;"><i class="fas fa-fire"></i> Daily Deals</a>
          </li>
          <li class="<?php echo (isset($currentPage) && $currentPage == 'about') ? 'active' : ''; ?>">
            <a href="about.php"><i class="fas fa-info-circle"></i> About Us</a>
          </li>
          <li class="<?php echo (isset($currentPage) && $currentPage == 'contact') ? 'active' : ''; ?>">
            <a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a>
          </li>
        </ul>

        <div style="display:flex; align-items:center; gap:10px;">
          <a href="track-order.php" class="btn btn-accent" style="padding: 6px 16px; font-size:13px;">
            <i class="fas fa-map-marker-alt"></i> Track Order
          </a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container" style="margin-top: 20px;">
    <?php echo display_flash_messages(); ?>
  </div>
