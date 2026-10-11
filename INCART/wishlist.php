<?php
$pageTitle = "My Wishlist - InCart Grocery";
$currentPage = "wishlist";
require_once __DIR__ . '/includes/header.php';
require_login();
$db = getDB();

$user_id = $_SESSION['user_id'];

// Handle Actions (Remove / Move to Cart)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_wishlist']) && isset($_POST['product_id'])) {
        $prodId = (int)$_POST['product_id'];
        $del = $db->prepare("
          DELETE wi FROM wishlist_items wi 
          JOIN wishlists w ON wi.wishlist_id = w.id 
          WHERE w.user_id = ? AND wi.product_id = ?
        ");
        $del->execute([$user_id, $prodId]);
        set_flash_success("Product removed from your wishlist.");
        header('Location: wishlist.php');
        exit;
    }

    if (isset($_POST['move_to_cart']) && isset($_POST['product_id'])) {
        $prodId = (int)$_POST['product_id'];
        $cart_id = get_cart_id();

        // Add to cart
        $checkCart = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $checkCart->execute([$cart_id, $prodId]);
        $existing = $checkCart->fetch();

        if ($existing) {
            $upd = $db->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?");
            $upd->execute([$existing['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)");
            $ins->execute([$cart_id, $prodId]);
        }

        // Remove from wishlist
        $del = $db->prepare("
          DELETE wi FROM wishlist_items wi 
          JOIN wishlists w ON wi.wishlist_id = w.id 
          WHERE w.user_id = ? AND wi.product_id = ?
        ");
        $del->execute([$user_id, $prodId]);

        set_flash_success("Product moved to your shopping cart!");
        header('Location: wishlist.php');
        exit;
    }
}

// Fetch Wishlist Items
$stmt = $db->prepare("
  SELECT wi.id as wishlist_item_id, p.*, pi.image_path as primary_image, b.name as brand_name
  FROM wishlist_items wi
  JOIN wishlists w ON wi.wishlist_id = w.id
  JOIN products p ON wi.product_id = p.id
  LEFT JOIN brands b ON p.brand_id = b.id
  LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
  WHERE w.user_id = ? AND p.is_active = 1
  ORDER BY wi.id DESC
");
$stmt->execute([$user_id]);
$wishlistItems = $stmt->fetchAll();
?>

<div class="container" style="margin:40px auto;">
  <h1 style="font-size:28px; font-weight:800; color:var(--dark); margin-bottom:24px;">My Saved Wishlist</h1>

  <?php if (empty($wishlistItems)): ?>
    <div style="background:var(--white); padding:60px; text-align:center; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm);">
      <i class="far fa-heart" style="font-size:64px; color:var(--gray-300); margin-bottom:20px;"></i>
      <h2 style="font-size:24px; font-weight:800; color:var(--dark); margin-bottom:10px;">Your wishlist is empty</h2>
      <p style="color:var(--gray-600); margin-bottom:25px;">Explore our fresh groceries and save your favorite items for later.</p>
      <a href="shop.php" class="btn btn-primary" style="padding:14px 32px;"><i class="fas fa-shopping-bag"></i> EXPLORE SHOP</a>
    </div>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($wishlistItems as $prod): 
        $img = $prod['primary_image'] ?? 'assets/images/products/default.jpg';
        $hasDiscount = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
        $price = $hasDiscount ? $prod['discount_price'] : $prod['price'];
      ?>
        <div class="product-card">
          <form action="wishlist.php" method="POST" style="position:absolute; top:12px; right:12px; z-index:10;">
            <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
            <button type="submit" name="remove_wishlist" class="wishlist-btn active" title="Remove from Wishlist">
              <i class="fas fa-trash-alt"></i>
            </button>
          </form>

          <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="product-img-wrapper">
            <img src="<?php echo sanitize($img); ?>" alt="<?php echo sanitize($prod['name']); ?>" loading="lazy" onerror="this.onerror=null; this.src='assets/images/products/default.jpg';">
          </a>

          <div class="product-info">
            <span class="product-brand"><?php echo sanitize($prod['brand_name'] ?? 'InCart'); ?></span>
            <h3 class="product-name">
              <a href="product-details.php?id=<?php echo $prod['id']; ?>" style="color:inherit;"><?php echo sanitize($prod['name']); ?></a>
            </h3>

            <div class="product-price-row">
              <span class="current-price"><?php echo format_price($price); ?></span>
              <?php if ($hasDiscount): ?>
                <span class="original-price"><?php echo format_price($prod['price']); ?></span>
              <?php endif; ?>
            </div>

            <form action="wishlist.php" method="POST">
              <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
              <button type="submit" name="move_to_cart" class="add-cart-btn" style="background:var(--primary); color:white;">
                <i class="fas fa-cart-plus"></i> MOVE TO CART
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
