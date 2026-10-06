<?php
$pageTitle = "InCart - Fresh Groceries Delivered to Your Doorstep";
$currentPage = "home";
require_once __DIR__ . '/includes/header.php';
$db = getDB();

// 1. Fetch Active Categories with product count
$categoriesQuery = "
  SELECT c.*, COUNT(p.id) as product_count 
  FROM categories c 
  LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
  WHERE c.is_active = 1 
  GROUP BY c.id 
  ORDER BY c.name ASC
";
$categories = $db->query($categoriesQuery)->fetchAll();

// 2. Fetch Featured Products (featured = 1)
$featuredStmt = $db->query("
  SELECT p.*, b.name as brand_name, pi.image_path as primary_image
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE p.is_active = 1 AND p.featured = 1
  LIMIT 8
");
$featuredProducts = $featuredStmt->fetchAll();

// 3. Fetch Daily Deals (discount_price IS NOT NULL)
$dealsStmt = $db->query("
  SELECT p.*, b.name as brand_name, pi.image_path as primary_image
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE p.is_active = 1 AND p.discount_price IS NOT NULL AND p.discount_price < p.price
  ORDER BY (p.price - p.discount_price) DESC
  LIMIT 4
");
$dailyDeals = $dealsStmt->fetchAll();

// 4. Fetch Best Sellers
$bestsellersStmt = $db->query("
  SELECT p.*, b.name as brand_name, pi.image_path as primary_image
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE p.is_active = 1
  ORDER BY p.review_count DESC, p.rating DESC
  LIMIT 4
");
$bestSellers = $bestsellersStmt->fetchAll();
?>

<!-- 1. HERO SECTION -->
<section class="hero-section">

  <video class="hero-video" autoplay muted loop playsinline>
    <source src="assets/video/video.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>

  <div class="hero-overlay"></div>

  <div class="container">
    <div class="hero-content">

      <span class="hero-tag">
        <i class="fas fa-leaf"></i> 100% Organic & Fresh
      </span>

      <h1 class="hero-title">
        Fresh Groceries Delivered to Your Doorstep
      </h1>

      <p class="hero-desc">
        Shop fresh, quality products at the best prices and get them
        delivered to your doorstep in Colombo & across Sri Lanka.
      </p>

      <div class="hero-btns">
        <a href="shop.php" class="btn btn-accent">
          <i class="fas fa-shopping-bag"></i> SHOP NOW
        </a>

        <a href="shop.php?discount=1" class="btn btn-outline">
          <i class="fas fa-tags"></i> VIEW DEALS
        </a>
      </div>

    </div>
  </div>

</section>


<!-- 2. CATEGORY SECTION -->
<section style="margin-bottom: 50px;">
  <div class="container">

    <div class="section-header">
      <h2 class="section-title">SHOP BY CATEGORY</h2>

      <a href="shop.php" class="btn btn-outline"
         style="color:var(--primary); border-color:var(--primary); font-size:13px;">
        View All Categories
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="category-grid">

      <?php foreach ($categories as $cat): ?>

        <a href="shop.php?category=<?php echo $cat['id']; ?>"
           class="category-card">

          <div class="category-icon">

            <?php
            $icon = trim($cat['icon'] ?? '');

            // Check whether icon value is an image
            if (!empty($icon) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $icon)):
            ?>

              <img
                src="<?php echo sanitize($icon); ?>"
                alt="<?php echo sanitize($cat['name']); ?>"
                class="category-image"
              >

            <?php else: ?>

              <i class="fas <?php echo sanitize($icon ?: 'fa-shopping-basket'); ?>"></i>

            <?php endif; ?>

          </div>

          <h3 class="category-name">
            <?php echo sanitize($cat['name']); ?>
          </h3>

          <p class="category-count">
            <?php echo $cat['product_count']; ?> Products
          </p>

        </a>

      <?php endforeach; ?>

    </div>

  </div>
</section>

<!-- 3. FEATURED PRODUCTS -->
<section style="margin-bottom: 50px;">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">FEATURED PRODUCTS</h2>
      <a href="shop.php?featured=1" class="btn btn-outline" style="color:var(--primary); border-color:var(--primary); font-size:13px;">See All Featured <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="product-grid">
      <?php foreach ($featuredProducts as $prod): 
        $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
        $inWishlist = is_in_wishlist($prod['id']);
        $hasDiscount = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
        $discountPercent = $hasDiscount ? round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100) : 0;
      ?>
        <div class="product-card">
          <?php if ($hasDiscount): ?>
            <span class="badge-discount"><?php echo $discountPercent; ?>% OFF</span>
          <?php endif; ?>

          <button class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="Add to Wishlist">
            <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
          </button>

          <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="product-img-wrapper">
            <img src="<?php echo sanitize($img); ?>" alt="<?php echo sanitize($prod['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
          </a>

          <div class="product-info">
            <span class="product-brand"><?php echo sanitize($prod['brand_name'] ?? 'InCart'); ?></span>
            <h3 class="product-name">
              <a href="product-details.php?id=<?php echo $prod['id']; ?>" style="color:inherit;"><?php echo sanitize($prod['name']); ?></a>
            </h3>

            <?php echo render_rating_stars($prod['rating']); ?>

            <div class="product-price-row">
              <?php if ($hasDiscount): ?>
                <span class="current-price"><?php echo format_price($prod['discount_price']); ?></span>
                <span class="original-price"><?php echo format_price($prod['price']); ?></span>
              <?php else: ?>
                <span class="current-price"><?php echo format_price($prod['price']); ?></span>
              <?php endif; ?>
            </div>

            <button class="add-cart-btn" data-id="<?php echo $prod['id']; ?>">
              <i class="fas fa-cart-plus"></i> ADD TO CART
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 4. DAILY DEALS SECTION -->
<section style="margin-bottom: 50px;">
  <div class="container">
    <div class="deals-section">
      <div class="deals-header">
        <div class="deals-title-wrap">
          <i class="fas fa-bolt"></i>
          <div>
            <h2 style="font-size:24px; font-weight:800; color:var(--dark);">DAILY DEALS OF THE DAY</h2>
            <p style="font-size:14px; color:var(--gray-600);">Hurry! Special discounts available for a limited time.</p>
          </div>
        </div>

        <!-- COUNTDOWN TIMER -->
        <div class="deals-timer">
          <span style="font-weight:700; font-size:13px; color:var(--dark);">Ends In:</span>
          <div class="timer-box" id="dealHours">11</div> :
          <div class="timer-box" id="dealMins">45</div> :
          <div class="timer-box" id="dealSecs">30</div>
        </div>
      </div>

      <div class="product-grid" style="margin-bottom:0;">
        <?php foreach ($dailyDeals as $prod): 
          $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
          $inWishlist = is_in_wishlist($prod['id']);
          $discountPercent = round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100);
        ?>
          <div class="product-card">
            <span class="badge-discount" style="background:#d32f2f;"><?php echo $discountPercent; ?>% OFF</span>

            <button class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="Add to Wishlist">
              <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
            </button>

            <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="product-img-wrapper">
              <img src="<?php echo sanitize($img); ?>" alt="<?php echo sanitize($prod['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
            </a>

            <div class="product-info">
              <span class="product-brand"><?php echo sanitize($prod['brand_name'] ?? 'InCart'); ?></span>
              <h3 class="product-name">
                <a href="product-details.php?id=<?php echo $prod['id']; ?>" style="color:inherit;"><?php echo sanitize($prod['name']); ?></a>
              </h3>

              <?php echo render_rating_stars($prod['rating']); ?>

              <div class="product-price-row">
                <span class="current-price" style="color:#d32f2f;"><?php echo format_price($prod['discount_price']); ?></span>
                <span class="original-price"><?php echo format_price($prod['price']); ?></span>
              </div>

              <button class="add-cart-btn" data-id="<?php echo $prod['id']; ?>">
                <i class="fas fa-cart-plus"></i> ADD TO CART
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- 5. PROMOTIONAL BANNER -->
<section style="margin-bottom: 50px;">
  <div class="container">
    <div style="background: linear-gradient(135deg, #1b5e20, #2e7d32); color:white; border-radius:var(--radius-lg); padding:40px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;">
      <div>
        <span style="background:var(--accent); color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase;">Special Offer</span>
        <h2 style="font-size:28px; font-weight:800; margin:10px 0;">Get Free Express Delivery on Orders Over LKR 5,000!</h2>
        <p style="opacity:0.9; font-size:15px;">Shop your weekly groceries today and get them delivered straight to your door within 2 hours.</p>
      </div>
      <a href="shop.php" class="btn btn-accent" style="padding:14px 32px; font-size:16px;">START SHOPPING NOW <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- 6. BEST SELLERS -->
<section style="margin-bottom: 50px;">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">BEST SELLERS</h2>
      <a href="shop.php?sort=popularity" class="btn btn-outline" style="color:var(--primary); border-color:var(--primary); font-size:13px;">View All Bestsellers <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="product-grid">
      <?php foreach ($bestSellers as $prod): 
        $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
        $inWishlist = is_in_wishlist($prod['id']);
        $hasDiscount = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
        $discountPercent = $hasDiscount ? round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100) : 0;
      ?>
        <div class="product-card">
          <?php if ($hasDiscount): ?>
            <span class="badge-discount"><?php echo $discountPercent; ?>% OFF</span>
          <?php endif; ?>

          <button class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="Add to Wishlist">
            <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
          </button>

          <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="product-img-wrapper">
            <img src="<?php echo sanitize($img); ?>" alt="<?php echo sanitize($prod['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
          </a>

          <div class="product-info">
            <span class="product-brand"><?php echo sanitize($prod['brand_name'] ?? 'InCart'); ?></span>
            <h3 class="product-name">
              <a href="product-details.php?id=<?php echo $prod['id']; ?>" style="color:inherit;"><?php echo sanitize($prod['name']); ?></a>
            </h3>

            <?php echo render_rating_stars($prod['rating']); ?>

            <div class="product-price-row">
              <?php if ($hasDiscount): ?>
                <span class="current-price"><?php echo format_price($prod['discount_price']); ?></span>
                <span class="original-price"><?php echo format_price($prod['price']); ?></span>
              <?php else: ?>
                <span class="current-price"><?php echo format_price($prod['price']); ?></span>
              <?php endif; ?>
            </div>

            <button class="add-cart-btn" data-id="<?php echo $prod['id']; ?>">
              <i class="fas fa-cart-plus"></i> ADD TO CART
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 7. WHY SHOP WITH US -->
<section style="margin-bottom: 50px;">
  <div class="container">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px;">
      <div style="background:var(--white); padding:25px; border-radius:var(--radius-md); text-align:center; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
        <i class="fas fa-shield-alt" style="font-size:36px; color:var(--primary); margin-bottom:15px;"></i>
        <h4 style="font-size:17px; font-weight:700; margin-bottom:8px;">100% Freshness Guaranteed</h4>
        <p style="font-size:13px; color:var(--gray-600);">Directly sourced from trusted local farmers and suppliers daily.</p>
      </div>

      <div style="background:var(--white); padding:25px; border-radius:var(--radius-md); text-align:center; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
        <i class="fas fa-shipping-fast" style="font-size:36px; color:var(--accent); margin-bottom:15px;"></i>
        <h4 style="font-size:17px; font-weight:700; margin-bottom:8px;">Express Fast Delivery</h4>
        <p style="font-size:13px; color:var(--gray-600);">Get your grocery items delivered within 2 hours of ordering.</p>
      </div>

      <div style="background:var(--white); padding:25px; border-radius:var(--radius-md); text-align:center; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
        <i class="fas fa-tags" style="font-size:36px; color:var(--primary); margin-bottom:15px;"></i>
        <h4 style="font-size:17px; font-weight:700; margin-bottom:8px;">Best Market Prices</h4>
        <p style="font-size:13px; color:var(--gray-600);">Competitive daily discounts and exclusive bundle offers.</p>
      </div>

      <div style="background:var(--white); padding:25px; border-radius:var(--radius-md); text-align:center; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
        <i class="fas fa-headset" style="font-size:36px; color:var(--accent); margin-bottom:15px;"></i>
        <h4 style="font-size:17px; font-weight:700; margin-bottom:8px;">Dedicated Support</h4>
        <p style="font-size:13px; color:var(--gray-600);">Friendly customer service team ready to assist you 7 days a week.</p>
      </div>
    </div>
  </div>
</section>

<!-- 8. NEWSLETTER -->
<section style="margin-bottom: 30px;">
  <div class="container">
    <div style="background:#e8f5e9; border:2px dashed var(--primary); padding:40px; border-radius:var(--radius-lg); text-align:center;">
      <h3 style="font-size:24px; font-weight:800; color:var(--primary-hover); margin-bottom:10px;">Subscribe to InCart Offers & Deals</h3>
      <p style="font-size:14px; color:var(--gray-800); margin-bottom:20px;">Get exclusive discount coupons and weekly fresh arrivals delivered straight to your inbox.</p>
      <form onsubmit="event.preventDefault(); showToast('Thank you for subscribing to InCart newsletter!', 'success');" style="max-width:500px; margin:0 auto; display:flex; gap:10px;">
        <input type="email" placeholder="Enter your email address..." required style="flex:1; padding:12px 18px; border-radius:30px; border:1px solid var(--gray-300); outline:none;">
        <button type="submit" class="btn btn-primary" style="border-radius:30px; padding:12px 24px;">SUBSCRIBE</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
