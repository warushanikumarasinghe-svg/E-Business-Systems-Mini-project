<?php

$pageTitle = "About Us - InCart Grocery";
$currentPage = "about";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="margin:50px auto;">
  <div style="background:var(--white); padding:50px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); margin-bottom:40px;">
    
    <div style="max-width:800px; margin:0 auto; text-align:center; margin-bottom:50px;">
      <span style="background:var(--primary-light); color:var(--primary); font-weight:700; padding:6px 16px; border-radius:20px; font-size:13px; text-transform:uppercase;">Welcome to InCart</span>
      <h1 style="font-size:36px; font-weight:800; color:var(--dark); margin:15px 0;">Sri Lanka's Premier Online Supermarket</h1>
      <p style="font-size:16px; color:var(--gray-800); line-height:1.8;">
        At <strong>InCart</strong>, we believe shopping for fresh groceries should be convenient, reliable, and delightful. Founded with a vision to revolutionize grocery shopping, we connect households directly with fresh local produce, organic farm fruits, bakery items, dairy, and household essentials.
      </p>
    </div>

    <!-- CORE PILLARS GRID -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:30px; margin-bottom:50px;">
      <div style="background:var(--gray-100); padding:30px; border-radius:var(--radius-md); border-top:4px solid var(--primary);">
        <i class="fas fa-seedling" style="font-size:36px; color:var(--primary); margin-bottom:15px;"></i>
        <h3 style="font-size:20px; font-weight:800; margin-bottom:10px;">Our Freshness Guarantee</h3>
        <p style="font-size:14px; color:var(--gray-600); line-height:1.6;">
          Every fruit, vegetable, and dairy product is handpicked by our trained quality experts directly from local farms every single morning.
        </p>
      </div>

      <div style="background:var(--gray-100); padding:30px; border-radius:var(--radius-md); border-top:4px solid var(--accent);">
        <i class="fas fa-truck-loading" style="font-size:36px; color:var(--accent); margin-bottom:15px;"></i>
        <h3 style="font-size:20px; font-weight:800; margin-bottom:10px;">Express Doorstep Delivery</h3>
        <p style="font-size:14px; color:var(--gray-600); line-height:1.6;">
          Our temperature-controlled delivery fleet ensures your frozen foods and fresh milk arrive cold, crisp, and ready for your kitchen.
        </p>
      </div>

      <div style="background:var(--gray-100); padding:30px; border-radius:var(--radius-md); border-top:4px solid var(--primary);">
        <i class="fas fa-hand-holding-heart" style="font-size:36px; color:var(--primary); margin-bottom:15px;"></i>
        <h3 style="font-size:20px; font-weight:800; margin-bottom:10px;">Customer First Mission</h3>
        <p style="font-size:14px; color:var(--gray-600); line-height:1.6;">
          We treat every order with utmost care. If you are ever dissatisfied with any product quality, we replace it instantly with zero hassle.
        </p>
      </div>
    </div>

    <div style="background:linear-gradient(135deg, #e8f5e9, #c8e6c9); padding:40px; border-radius:var(--radius-lg); text-align:center;">
      <h2 style="font-size:26px; font-weight:800; color:var(--primary-hover); margin-bottom:12px;">Ready to Fill Your Cart?</h2>
      <p style="font-size:15px; color:var(--gray-800); margin-bottom:25px;">Explore 1,000+ fresh products with daily discounts and free delivery over LKR 5,000.</p>
      <a href="shop.php" class="btn btn-primary" style="padding:14px 32px;"><i class="fas fa-shopping-bag"></i> SHOP GROCERIES NOW</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
