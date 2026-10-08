<?php
require_once __DIR__ . '/includes/functions.php';
$db = getDB();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: shop.php');
    exit;
}

// Fetch Product Details with Category, Subcategory & Brand
$stmt = $db->prepare("
  SELECT p.*, c.name as category_name, sc.name as subcategory_name, b.name as brand_name
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.id
  LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
  LEFT JOIN brands b ON p.brand_id = b.id
  WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash_error("Product not found or unavailable.");
    header('Location: shop.php');
    exit;
}

$pageTitle = $product['name'] . " - InCart Grocery";
$currentPage = "shop";
require_once __DIR__ . '/includes/header.php';

// Fetch Product Images Gallery
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$imgStmt->execute([$product_id]);
$productImages = $imgStmt->fetchAll();
$primaryImage = !empty($productImages) ? $productImages[0]['image_path'] : 'assets/images/products/default.jpg';

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) {
        set_flash_error("Please log in to submit a review.");
        header("Location: login.php");
        exit;
    }

    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Check if review exists
        $checkReview = $db->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
        $checkReview->execute([$product_id, $user_id]);
        if ($checkReview->fetch()) {
            set_flash_error("You have already submitted a review for this product.");
        } else {
            $insReview = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, is_approved) VALUES (?, ?, ?, ?, 0)");
            $insReview->execute([$product_id, $user_id, $rating, $comment]);
            set_flash_success("Thank you! Your review has been submitted and is pending administrator approval.");
        }
    } else {
        set_flash_error("Please provide a valid rating and review comment.");
    }
    header("Location: product-details.php?id=" . $product_id);
    exit;
}

// Fetch Approved Reviews
$revStmt = $db->prepare("
  SELECT r.*, u.first_name, u.last_name 
  FROM reviews r 
  JOIN users u ON r.user_id = u.id 
  WHERE r.product_id = ? AND r.is_approved = 1 
  ORDER BY r.created_at DESC
");
$revStmt->execute([$product_id]);
$approvedReviews = $revStmt->fetchAll();

// Fetch Related Products (Same category/subcategory)
$relStmt = $db->prepare("
  SELECT p.*, pi.image_path as primary_image, b.name as brand_name
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
  ORDER BY RAND()
  LIMIT 4
");
$relStmt->execute([$product['category_id'], $product_id]);
$relatedProducts = $relStmt->fetchAll();

// Fetch Latest Products
$latestStmt = $db->prepare("
  SELECT p.*, pi.image_path as primary_image, b.name as brand_name
  FROM products p
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE p.id != ? AND p.is_active = 1
  ORDER BY p.created_at DESC
  LIMIT 4
");
$latestStmt->execute([$product_id]);
$latestProducts = $latestStmt->fetchAll();

$hasDiscount = !empty($product['discount_price']) && $product['discount_price'] < $product['price'];
$discountPercent = $hasDiscount ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
$inWishlist = is_in_wishlist($product['id']);
?>

<div class="container">
  <!-- BREADCRUMBS -->
  <div style="font-size:13px; color:var(--gray-600); margin: 20px 0;">
    <a href="index.php">Home</a> &gt; 
    <a href="shop.php?category=<?php echo $product['category_id']; ?>"><?php echo sanitize($product['category_name']); ?></a> &gt; 
    <a href="shop.php?subcategory=<?php echo $product['subcategory_id']; ?>"><?php echo sanitize($product['subcategory_name']); ?></a> &gt; 
    <span style="color:var(--dark); font-weight:600;"><?php echo sanitize($product['name']); ?></span>
  </div>

  <!-- PRODUCT DETAIL WRAPPER -->
  <div class="product-detail-container">
    <!-- LEFT: GALLERY -->
    <div>
      <div class="gallery-main">
        <img id="mainProductImg" src="<?php echo sanitize($primaryImage); ?>" alt="<?php echo sanitize($product['name']); ?>" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
      </div>

      <?php if (count($productImages) > 1): ?>
        <div class="gallery-thumbs">
          <?php foreach ($productImages as $idx => $img): ?>
            <img src="<?php echo sanitize($img['image_path']); ?>" class="thumb <?php echo $idx === 0 ? 'active' : ''; ?>" alt="Product Thumbnail" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: PRODUCT INFO -->
    <div class="product-detail-info">
      <span class="product-brand"><?php echo sanitize($product['brand_name'] ?? 'InCart'); ?></span>
      <h1><?php echo sanitize($product['name']); ?></h1>

      <div style="display:flex; align-items:center; gap:15px; margin-bottom:15px;">
        <?php echo render_rating_stars($product['rating']); ?>
        <span style="font-size:13px; color:var(--gray-600);">(<?php echo count($approvedReviews); ?> Customer Reviews)</span>
        <span style="font-size:13px; color:var(--gray-600);">| SKU: <strong><?php echo sanitize($product['sku']); ?></strong></span>
      </div>

      <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:20px;">
        <?php if ($hasDiscount): ?>
          <span style="font-size:32px; font-weight:800; color:var(--primary);"><?php echo format_price($product['discount_price']); ?></span>
          <span style="font-size:18px; color:var(--gray-600); text-decoration:line-through;"><?php echo format_price($product['price']); ?></span>
          <span class="badge-discount" style="position:static; font-size:14px;"><?php echo $discountPercent; ?>% OFF</span>
        <?php else: ?>
          <span style="font-size:32px; font-weight:800; color:var(--primary);"><?php echo format_price($product['price']); ?></span>
        <?php endif; ?>
      </div>

      <div style="margin-bottom:20px; font-size:14px; color:var(--gray-800); line-height:1.6;">
        <p><?php echo sanitize($product['short_description']); ?></p>
      </div>

      <div style="margin-bottom:20px; font-size:14px;">
        <p style="margin-bottom:6px;">Unit Size: <strong><?php echo sanitize($product['unit']); ?> (<?php echo sanitize($product['weight']); ?>)</strong></p>
        <p style="margin-bottom:6px;">Country of Origin: <strong><?php echo sanitize($product['country_of_origin']); ?></strong></p>
        <p>Availability: 
          <?php if ($product['stock_quantity'] > 5): ?>
            <span style="color:var(--success); font-weight:700;"><i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock_quantity']; ?> available)</span>
          <?php elseif ($product['stock_quantity'] > 0): ?>
            <span style="color:var(--accent); font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Low Stock (Only <?php echo $product['stock_quantity']; ?> left!)</span>
          <?php else: ?>
            <span style="color:var(--danger); font-weight:700;"><i class="fas fa-times-circle"></i> Out of Stock</span>
          <?php endif; ?>
        </p>
      </div>

      <!-- QUANTITY & ACTIONS -->
      <?php if ($product['stock_quantity'] > 0): ?>
        <div style="display:flex; align-items:center; gap:20px; margin-top:30px;">
          <div class="qty-selector">
            <button class="qty-btn qty-minus">-</button>
            <input type="number" id="productQty" class="qty-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
            <button class="qty-btn qty-plus">+</button>
          </div>

          <button class="btn btn-primary add-cart-btn" data-id="<?php echo $product['id']; ?>" style="padding:14px 28px;">
            <i class="fas fa-cart-plus"></i> ADD TO CART
          </button>

          <button class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>" data-id="<?php echo $product['id']; ?>" style="position:static; width:48px; height:48px; font-size:18px;" title="Wishlist">
            <i class="<?php echo $inWishlist ? 'fas' : 'far'; ?> fa-heart"></i>
          </button>
        </div>
      <?php else: ?>
        <button class="btn" disabled style="background:var(--gray-300); color:var(--gray-600); cursor:not-allowed;">OUT OF STOCK</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- INFORMATION TABS -->
  <div class="product-tabs">
    <div class="tab-nav">
      <button class="tab-btn active" data-tab="descTab">DESCRIPTION</button>
      <button class="tab-btn" data-tab="infoTab">ADDITIONAL INFORMATION</button>
      <button class="tab-btn" data-tab="reviewsTab">REVIEWS (<?php echo count($approvedReviews); ?>)</button>
      <button class="tab-btn" data-tab="shippingTab">SHIPPING & RETURNS</button>
    </div>

    <!-- TAB 1: DESCRIPTION -->
    <div class="tab-content active" id="descTab">
      <h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Product Details</h3>
      <p style="font-size:15px; color:var(--gray-800); line-height:1.8;"><?php echo nl2br(sanitize($product['description'])); ?></p>
    </div>

    <!-- TAB 2: ADDITIONAL INFO -->
    <div class="tab-content" id="infoTab">
      <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700; width:200px;">Brand</td><td style="padding:10px;"><?php echo sanitize($product['brand_name'] ?? 'N/A'); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">Category</td><td style="padding:10px;"><?php echo sanitize($product['category_name']); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">Subcategory</td><td style="padding:10px;"><?php echo sanitize($product['subcategory_name']); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">SKU</td><td style="padding:10px;"><?php echo sanitize($product['sku']); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">Weight</td><td style="padding:10px;"><?php echo sanitize($product['weight']); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">Unit</td><td style="padding:10px;"><?php echo sanitize($product['unit']); ?></td></tr>
        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px; font-weight:700;">Country of Origin</td><td style="padding:10px;"><?php echo sanitize($product['country_of_origin']); ?></td></tr>
      </table>
    </div>

    <!-- TAB 3: REVIEWS -->
    <div class="tab-content" id="reviewsTab">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px;">

        <!-- REVIEWS LIST -->
        <div>
          <h3 style="font-size:18px; font-weight:700; margin-bottom:20px;">Customer Reviews</h3>
          <?php if (empty($approvedReviews)): ?>
            <p style="color:var(--gray-600);">No reviews yet. Be the first to review this product!</p>
          <?php else: ?>
            <?php foreach ($approvedReviews as $rev): ?>
              <div style="border-bottom:1px solid var(--gray-200); padding-bottom:15px; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                  <strong style="font-size:15px; color:var(--dark);"><?php echo sanitize($rev['first_name'] . ' ' . $rev['last_name']); ?></strong>
                  <span style="font-size:12px; color:var(--gray-600);"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                </div>
                <?php echo render_rating_stars($rev['rating']); ?>
                <p style="font-size:14px; color:var(--gray-800); margin-top:8px;"><?php echo sanitize($rev['comment']); ?></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- WRITE A REVIEW FORM -->
        <div style="background:var(--gray-100); padding:24px; border-radius:var(--radius-md);">
          <h3 style="font-size:18px; font-weight:700; margin-bottom:15px;">Write a Review</h3>
          <?php if (is_logged_in()): ?>
            <form action="product-details.php?id=<?php echo $product_id; ?>" method="POST">
              <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Rating</label>
                <select name="rating" required style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--gray-300);">
                  <option value="5">5 Stars - Excellent</option>
                  <option value="4">4 Stars - Good</option>
                  <option value="3">3 Stars - Average</option>
                  <option value="2">2 Stars - Poor</option>
                  <option value="1">1 Star - Very Poor</option>
                </select>
              </div>

              <div style="margin-bottom:15px;">
                <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Your Comment</label>
                <textarea name="comment" rows="4" required placeholder="Write your experience with this product..." style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--gray-300); outline:none;"></textarea>
              </div>

              <button type="submit" name="submit_review" class="btn btn-primary" style="width:100%;">SUBMIT REVIEW</button>
            </form>
          <?php else: ?>
            <p style="font-size:14px; color:var(--gray-800);">Please <a href="login.php" style="font-weight:700; text-decoration:underline;">login</a> to leave a review.</p>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <!-- TAB 4: SHIPPING & RETURNS -->
    <div class="tab-content" id="shippingTab">
      <h3 style="font-size:18px; font-weight:700; margin-bottom:12px;">Shipping & Delivery Information</h3>
      <p style="font-size:14px; line-height:1.6; margin-bottom:15px;">InCart offers fast temperature-controlled delivery for all fresh produce and frozen items across Colombo and suburbs.</p>
      <ul style="font-size:14px; line-height:1.8; margin-left:20px; list-style-type:disc;">
        <li>Standard Delivery: Within 24 Hours (LKR 350 flat fee; FREE on orders over LKR 5,000)</li>
        <li>Express Delivery: Delivered within 2 Hours (LKR 550)</li>
        <li>Freshness Guarantee: If any product arrives damaged or unsatisfied, request instant replacement within 24 hours.</li>
      </ul>
    </div>
  </div>

  <!-- RELATED PRODUCTS ("YOU MAY ALSO LIKE") -->
  <?php if (!empty($relatedProducts)): ?>
    <section style="margin-top:60px;">
      <div class="section-header">
        <h2 class="section-title">YOU MAY ALSO LIKE</h2>
      </div>

      <div class="product-grid">
        <?php foreach ($relatedProducts as $prod): 
          $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
          $inWish = is_in_wishlist($prod['id']);
          $hasDisc = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
        ?>
          <div class="product-card">
            <button class="wishlist-btn <?php echo $inWish ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="Wishlist">
              <i class="<?php echo $inWish ? 'fas' : 'far'; ?> fa-heart"></i>
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
                <span class="current-price"><?php echo format_price($hasDisc ? $prod['discount_price'] : $prod['price']); ?></span>
              </div>
              <button class="add-cart-btn" data-id="<?php echo $prod['id']; ?>"><i class="fas fa-cart-plus"></i> ADD TO CART</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- LATEST PRODUCTS -->
  <?php if (!empty($latestProducts)): ?>
    <section style="margin-top:40px; margin-bottom:60px;">
      <div class="section-header">
        <h2 class="section-title">LATEST PRODUCTS</h2>
      </div>

      <div class="product-grid">
        <?php foreach ($latestProducts as $prod): 
          $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
          $inWish = is_in_wishlist($prod['id']);
          $hasDisc = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
        ?>
          <div class="product-card">
            <button class="wishlist-btn <?php echo $inWish ? 'active' : ''; ?>" data-id="<?php echo $prod['id']; ?>" title="Wishlist">
              <i class="<?php echo $inWish ? 'fas' : 'far'; ?> fa-heart"></i>
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
                <span class="current-price"><?php echo format_price($hasDisc ? $prod['discount_price'] : $prod['price']); ?></span>
              </div>
              <button class="add-cart-btn" data-id="<?php echo $prod['id']; ?>"><i class="fas fa-cart-plus"></i> ADD TO CART</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
