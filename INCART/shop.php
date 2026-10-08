<?php
$pageTitle = "Shop Products - InCart";
$currentPage = "shop";
require_once __DIR__ . '/includes/header.php';
$db = getDB();

// 1. Capture Filter Inputs
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$cat_id       = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$subcat_id    = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : 0;
$brand_id     = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$min_price    = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price    = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$availability = isset($_GET['availability']) ? trim($_GET['availability']) : 'all';
$discount     = isset($_GET['discount']) ? (int)$_GET['discount'] : 0;
$rating_min   = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$featured     = isset($_GET['featured']) ? (int)$_GET['featured'] : 0;
$sort         = isset($_GET['sort']) ? trim($_GET['sort']) : 'default';
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit        = 12;
$offset       = ($page - 1) * $limit;

// 2. Build Dynamic MySQL Query
$where = ["p.is_active = 1"];
$params = [];

// Global Search
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR b.name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Category
if ($cat_id > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $cat_id;
}

// Subcategory
if ($subcat_id > 0) {
    $where[] = "p.subcategory_id = ?";
    $params[] = $subcat_id;
}

// Brand
if ($brand_id > 0) {
    $where[] = "p.brand_id = ?";
    $params[] = $brand_id;
}

// Price Range (use discount_price if available, else price)
if ($min_price !== null) {
    $where[] = "(COALESCE(p.discount_price, p.price) >= ?)";
    $params[] = $min_price;
}
if ($max_price !== null) {
    $where[] = "(COALESCE(p.discount_price, p.price) <= ?)";
    $params[] = $max_price;
}

// Availability
if ($availability === 'in_stock') {
    $where[] = "p.stock_quantity > 5";
} elseif ($availability === 'low_stock') {
    $where[] = "(p.stock_quantity > 0 AND p.stock_quantity <= 5)";
} elseif ($availability === 'out_of_stock') {
    $where[] = "p.stock_quantity = 0";
}

// Discount Filter
if ($discount === 1) {
    $where[] = "(p.discount_price IS NOT NULL AND p.discount_price < p.price)";
}

// Rating Filter
if ($rating_min > 0) {
    $where[] = "p.rating >= ?";
    $params[] = $rating_min;
}

// Featured Filter
if ($featured === 1) {
    $where[] = "p.featured = 1";
}

$whereSQL = implode(" AND ", $where);

// 3. Sorting SQL Order By Clause
switch ($sort) {
    case 'price_asc':
        $orderSQL = "ORDER BY COALESCE(p.discount_price, p.price) ASC";
        break;
    case 'price_desc':
        $orderSQL = "ORDER BY COALESCE(p.discount_price, p.price) DESC";
        break;
    case 'name_asc':
        $orderSQL = "ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $orderSQL = "ORDER BY p.name DESC";
        break;
    case 'newest':
        $orderSQL = "ORDER BY p.created_at DESC";
        break;
    case 'oldest':
        $orderSQL = "ORDER BY p.created_at ASC";
        break;
    case 'rating_desc':
        $orderSQL = "ORDER BY p.rating DESC";
        break;
    case 'discount_desc':
        $orderSQL = "ORDER BY (p.price - COALESCE(p.discount_price, p.price)) DESC";
        break;
    case 'popularity':
        $orderSQL = "ORDER BY p.review_count DESC, p.rating DESC";
        break;
    default:
        $orderSQL = "ORDER BY p.featured DESC, p.id DESC";
        break;
}

// 4. Count Total Products for Pagination
$countSql = "
    SELECT COUNT(*) as total 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE {$whereSQL}
";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// 5. Fetch Paginated Products
$sql = "
    SELECT p.*, c.name as category_name, b.name as brand_name, pi.image_path as primary_image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE {$whereSQL}
    {$orderSQL}
    LIMIT {$limit} OFFSET {$offset}
";
$stmtProducts = $db->prepare($sql);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();

// 6. Fetch Sidebar Data (Active Categories, Subcategories, Brands)
$allCategories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allSubcategories = $subcat_id > 0 || $cat_id > 0 ? $db->query("SELECT * FROM subcategories WHERE is_active = 1" . ($cat_id > 0 ? " AND category_id = {$cat_id}" : "") . " ORDER BY name ASC")->fetchAll() : [];
$allBrands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>

<div class="container">
  <div class="shop-layout">

    <!-- LEFT FILTER SIDEBAR -->
    <aside class="filter-sidebar">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h3 style="font-size:18px; font-weight:800; color:var(--dark);"><i class="fas fa-filter"></i> FILTERS</h3>
        <a href="shop.php" style="font-size:12px; font-weight:700; color:var(--danger);"><i class="fas fa-redo"></i> CLEAR ALL</a>
      </div>

      <form action="shop.php" method="GET" id="filterForm">
        <!-- Preserve search input if present -->
        <?php if ($search !== ''): ?>
          <input type="hidden" name="search" value="<?php echo sanitize($search); ?>">
        <?php endif; ?>

        <!-- CATEGORY FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Category</h4>
          <div class="filter-list">
            <label>
              <input type="radio" name="category" value="0" <?php echo $cat_id === 0 ? 'checked' : ''; ?> onchange="this.form.submit()">
              <span>All Categories</span>
            </label>
            <?php foreach ($allCategories as $c): ?>
              <label>
                <input type="radio" name="category" value="<?php echo $c['id']; ?>" <?php echo $cat_id === $c['id'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span><?php echo sanitize($c['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- SUBCATEGORY FILTER (IF CATEGORY SELECTED) -->
        <?php if (!empty($allSubcategories)): ?>
          <div class="filter-group">
            <h4 class="filter-title">Subcategory</h4>
            <div class="filter-list">
              <label>
                <input type="radio" name="subcategory" value="0" <?php echo $subcat_id === 0 ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span>All Subcategories</span>
              </label>
              <?php foreach ($allSubcategories as $sc): ?>
                <label>
                  <input type="radio" name="subcategory" value="<?php echo $sc['id']; ?>" <?php echo $subcat_id === $sc['id'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                  <span><?php echo sanitize($sc['name']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- BRAND FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Brand</h4>
          <div class="filter-list">
            <label>
              <input type="radio" name="brand" value="0" <?php echo $brand_id === 0 ? 'checked' : ''; ?> onchange="this.form.submit()">
              <span>All Brands</span>
            </label>
            <?php foreach ($allBrands as $b): ?>
              <label>
                <input type="radio" name="brand" value="<?php echo $b['id']; ?>" <?php echo $brand_id === $b['id'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span><?php echo sanitize($b['name']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- PRICE RANGE FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Price Range (LKR)</h4>
          <div class="price-inputs">
            <input type="number" name="min_price" placeholder="Min" value="<?php echo $min_price !== null ? $min_price : ''; ?>" min="0">
            <span>-</span>
            <input type="number" name="max_price" placeholder="Max" value="<?php echo $max_price !== null ? $max_price : ''; ?>" min="0">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px; padding:8px; font-size:13px;">APPLY PRICE</button>
        </div>

        <!-- AVAILABILITY FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Availability</h4>
          <div class="filter-list">
            <label><input type="radio" name="availability" value="all" <?php echo $availability === 'all' ? 'checked' : ''; ?> onchange="this.form.submit()"> All Items</label>
            <label><input type="radio" name="availability" value="in_stock" <?php echo $availability === 'in_stock' ? 'checked' : ''; ?> onchange="this.form.submit()"> In Stock</label>
            <label><input type="radio" name="availability" value="low_stock" <?php echo $availability === 'low_stock' ? 'checked' : ''; ?> onchange="this.form.submit()"> Low Stock</label>
            <label><input type="radio" name="availability" value="out_of_stock" <?php echo $availability === 'out_of_stock' ? 'checked' : ''; ?> onchange="this.form.submit()"> Out of Stock</label>
          </div>
        </div>

        <!-- RATING FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Customer Rating</h4>
          <div class="filter-list">
            <label><input type="radio" name="rating" value="0" <?php echo $rating_min == 0 ? 'checked' : ''; ?> onchange="this.form.submit()"> Any Rating</label>
            <label><input type="radio" name="rating" value="4.5" <?php echo $rating_min == 4.5 ? 'checked' : ''; ?> onchange="this.form.submit()"> 4.5★ & Above</label>
            <label><input type="radio" name="rating" value="4.0" <?php echo $rating_min == 4.0 ? 'checked' : ''; ?> onchange="this.form.submit()"> 4.0★ & Above</label>
            <label><input type="radio" name="rating" value="3.0" <?php echo $rating_min == 3.0 ? 'checked' : ''; ?> onchange="this.form.submit()"> 3.0★ & Above</label>
          </div>
        </div>

        <!-- SPECIAL OFFERS FILTER -->
        <div class="filter-group">
          <h4 class="filter-title">Promotions</h4>
          <div class="filter-list">
            <label><input type="checkbox" name="discount" value="1" <?php echo $discount === 1 ? 'checked' : ''; ?> onchange="this.form.submit()"> Discounted Deals</label>
            <label><input type="checkbox" name="featured" value="1" <?php echo $featured === 1 ? 'checked' : ''; ?> onchange="this.form.submit()"> Featured Only</label>
          </div>
        </div>

        <!-- Preserve Sort -->
        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
      </form>
    </aside>

    <!-- RIGHT PRODUCT LISTING & TOOLBAR -->
    <main>
      <!-- TOOLBAR -->
      <div class="shop-toolbar">
        <div>
          <span style="font-weight:700; font-size:15px; color:var(--dark);">
            <?php echo $totalProducts; ?> <?php echo $totalProducts === 1 ? 'product' : 'products'; ?> found
            <?php if ($search !== ''): ?>
              for "<strong style="color:var(--primary);"><?php echo sanitize($search); ?></strong>"
            <?php endif; ?>
          </span>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
          <label style="font-size:14px; font-weight:600;">Sort By:</label>
          <select id="sortSelect" class="sort-select">
            <option value="default" <?php echo $sort === 'default' ? 'selected' : ''; ?>>Default Sorting</option>
            <option value="popularity" <?php echo $sort === 'popularity' ? 'selected' : ''; ?>>Popularity</option>
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
            <option value="rating_desc" <?php echo $sort === 'rating_desc' ? 'selected' : ''; ?>>Highest Rated</option>
            <option value="discount_desc" <?php echo $sort === 'discount_desc' ? 'selected' : ''; ?>>Biggest Discount</option>
          </select>
        </div>
      </div>

      <!-- PRODUCT GRID -->
      <?php if (empty($products)): ?>
        <div style="background:var(--white); padding:60px; text-align:center; border-radius:var(--radius-md); box-shadow:var(--shadow-sm);">
          <i class="fas fa-search" style="font-size:48px; color:var(--gray-300); margin-bottom:15px;"></i>
          <h3 style="font-size:22px; font-weight:800; color:var(--dark); margin-bottom:8px;">No products found.</h3>
          <p style="color:var(--gray-600); margin-bottom:20px;">Try adjusting your search criteria or clearing filters.</p>
          <a href="shop.php" class="btn btn-primary">CLEAR FILTERS</a>
        </div>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $prod): 
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

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php 
              // Build base URL for pagination links preserving existing params
              $queryParams = $_GET;
            ?>
            <?php if ($page > 1): $queryParams['page'] = $page - 1; ?>
              <a href="shop.php?<?php echo http_build_query($queryParams); ?>">&laquo; Previous</a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): $queryParams['page'] = $p; ?>
              <a href="shop.php?<?php echo http_build_query($queryParams); ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): $queryParams['page'] = $page + 1; ?>
              <a href="shop.php?<?php echo http_build_query($queryParams); ?>">Next &raquo;</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </main>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
