<?php
$adminTitle = "Product Management";
$adminPage = "products";
require_once __DIR__ . '/includes/header.php';

// Handle Safe Product Deactivation / Reactivation (Soft Delete)
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId > 0) {
        // Keep the product in the database so existing orders can still reference it.
        $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
        $stmt->execute([$delId]);
        set_flash_success("Product deactivated successfully. It was not permanently deleted.");
    }
    header('Location: products.php');
    exit;
}

if (isset($_GET['activate'])) {
    $activateId = (int)$_GET['activate'];
    if ($activateId > 0) {
        $stmt = $db->prepare("UPDATE products SET is_active = 1 WHERE id = ?");
        $stmt->execute([$activateId]);
        set_flash_success("Product activated successfully.");
    }
    header('Location: products.php');
    exit;
}

// Handle Add / Edit Product Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name           = trim($_POST['name'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $subcategory_id = (int)($_POST['subcategory_id'] ?? 0);
    $brand_id       = (int)($_POST['brand_id'] ?? 0);
    $sku            = trim($_POST['sku'] ?? '');
    $price          = (float)($_POST['price'] ?? 0);
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $unit           = trim($_POST['unit'] ?? '1 pack');
    $weight         = trim($_POST['weight'] ?? '500g');
    $description    = trim($_POST['description'] ?? '');
    $short_desc     = trim($_POST['short_description'] ?? '');
    $featured       = isset($_POST['featured']) ? 1 : 0;
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

    // Server-side validation for Task A
    if (empty($name) || empty($sku) || $price <= 0 || $category_id <= 0 || $subcategory_id <= 0 || $brand_id <= 0) {
        set_flash_error("Please fill in all required fields (Name, SKU, Category, Subcategory, Brand, Price).");
    } elseif ($stock_quantity < 0) {
        set_flash_error("Stock quantity cannot be negative.");
    } elseif ($discount_price !== null && ($discount_price <= 0 || $discount_price >= $price)) {
        set_flash_error("Discount price must be greater than 0 and lower than the regular price.");
    } else {
        if ($id > 0) {
            // Update
            $upd = $db->prepare("
              UPDATE products 
              SET category_id = ?, subcategory_id = ?, brand_id = ?, name = ?, slug = ?, sku = ?, description = ?, short_description = ?, price = ?, discount_price = ?, stock_quantity = ?, unit = ?, weight = ?, featured = ?, is_active = ?
              WHERE id = ?
            ");
            $upd->execute([$category_id, $subcategory_id, $brand_id, $name, $slug, $sku, $description, $short_desc, $price, $discount_price, $stock_quantity, $unit, $weight, $featured, $is_active, $id]);
            $product_id = $id;
            set_flash_success("Product '{$name}' updated successfully.");
        } else {
            // Insert
            $ins = $db->prepare("
              INSERT INTO products 
              (category_id, subcategory_id, brand_id, name, slug, sku, description, short_description, price, discount_price, stock_quantity, unit, weight, featured, is_active)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$category_id, $subcategory_id, $brand_id, $name, $slug, $sku, $description, $short_desc, $price, $discount_price, $stock_quantity, $unit, $weight, $featured, $is_active]);
            $product_id = $db->lastInsertId();
            set_flash_success("Product '{$name}' created successfully.");
        }

        // Handle Image Upload with Security Check
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['product_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $allowedExts  = ['jpg', 'jpeg', 'png', 'webp'];
            $maxSize      = 5 * 1024 * 1024; // 5MB

            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($file['tmp_name']);
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if ($file['size'] > 0 && $file['size'] <= $maxSize && in_array($fileExt, $allowedExts, true) && in_array($detectedMime, $allowedMimeTypes, true)) {
                $newFilename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                $targetDir = __DIR__ . '/../assets/images/products/';
                $targetPath = $targetDir . $newFilename;
                $dbPath = 'assets/images/products/' . $newFilename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Update primary image
                    $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$product_id]);
                    $insImg = $db->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)");
                    $insImg->execute([$product_id, $dbPath]);
                }
            }
        }

        header('Location: products.php');
        exit;
    }
}

// Fetch Products List + Search
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmtProducts = $db->prepare("
      SELECT p.*, c.name as category_name, b.name as brand_name, pi.image_path as primary_image
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      LEFT JOIN brands b ON p.brand_id = b.id
      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
      WHERE p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ? OR b.name LIKE ?
      ORDER BY p.id DESC
    ");
    $like = '%' . $search . '%';
    $stmtProducts->execute([$like, $like, $like, $like]);
    $products = $stmtProducts->fetchAll();
} else {
    $products = $db->query("
      SELECT p.*, c.name as category_name, b.name as brand_name, pi.image_path as primary_image
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      LEFT JOIN brands b ON p.brand_id = b.id
      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
      ORDER BY p.id DESC
    ")->fetchAll();
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$subcategories = $db->query("SELECT * FROM subcategories ORDER BY name ASC")->fetchAll();
$brands = $db->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();

// Product Edit Pre-fill
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtEdit = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmtEdit->execute([$editId]);
    $editProduct = $stmtEdit->fetch();
}
?>

<div class="admin-card">
  <div class="admin-card-title">
    <span><i class="fas fa-box" style="color:var(--admin-primary);"></i> <?php echo $editProduct ? 'Edit Product' : 'Add New Grocery Product'; ?></span>
    <?php if ($editProduct): ?>
      <a href="products.php" class="btn btn-outline" style="font-size:12px; border-color:#ccc; color:var(--dark);">Cancel Edit</a>
    <?php endif; ?>
  </div>

  <form action="products.php" method="POST" enctype="multipart/form-data">
    <?php if ($editProduct): ?>
      <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
      <div>
        <label class="form-group label">Product Name *</label>
        <input type="text" name="name" class="form-control" required value="<?php echo sanitize($editProduct['name'] ?? ''); ?>">
      </div>
      <div>
        <label class="form-group label">SKU Code *</label>
        <input type="text" name="sku" class="form-control" required value="<?php echo sanitize($editProduct['sku'] ?? 'SKU-' . rand(100, 999)); ?>">
      </div>
      <div>
        <label class="form-group label">Regular Price (LKR) *</label>
        <input type="number" step="0.01" name="price" class="form-control" required value="<?php echo $editProduct['price'] ?? ''; ?>">
      </div>
      <div>
        <label class="form-group label">Discount Price (LKR)</label>
        <input type="number" step="0.01" name="discount_price" class="form-control" value="<?php echo $editProduct['discount_price'] ?? ''; ?>">
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
      <div>
        <label class="form-group label">Category *</label>
        <select name="category_id" class="form-control" required>
          <option value="">Select Category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo (isset($editProduct['category_id']) && $editProduct['category_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-group label">Subcategory *</label>
        <select name="subcategory_id" class="form-control" required>
          <option value="">Select Subcategory</option>
          <?php foreach ($subcategories as $sc): ?>
            <option value="<?php echo $sc['id']; ?>" <?php echo (isset($editProduct['subcategory_id']) && $editProduct['subcategory_id'] == $sc['id']) ? 'selected' : ''; ?>><?php echo sanitize($sc['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-group label">Brand *</label>
        <select name="brand_id" class="form-control" required>
          <option value="">Select Brand</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?php echo $b['id']; ?>" <?php echo (isset($editProduct['brand_id']) && $editProduct['brand_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo sanitize($b['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-group label">Stock Quantity *</label>
        <input type="number" name="stock_quantity" class="form-control" required value="<?php echo $editProduct['stock_quantity'] ?? 50; ?>">
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr 2fr; gap:15px; margin-bottom:15px;">
      <div>
        <label class="form-group label">Unit Pack</label>
        <input type="text" name="unit" class="form-control" value="<?php echo sanitize($editProduct['unit'] ?? '1 pack'); ?>">
      </div>
      <div>
        <label class="form-group label">Weight</label>
        <input type="text" name="weight" class="form-control" value="<?php echo sanitize($editProduct['weight'] ?? '500g'); ?>">
      </div>
      <div>
        <label class="form-group label">Product Image (.jpg, .png, .webp max 5MB)</label>
        <input type="file" name="product_image" class="form-control" accept="image/*">
      </div>
    </div>

    <div style="margin-bottom:15px;">
      <label class="form-group label">Short Description</label>
      <input type="text" name="short_description" class="form-control" value="<?php echo sanitize($editProduct['short_description'] ?? ''); ?>">
    </div>

    <div style="margin-bottom:20px;">
      <label class="form-group label">Detailed Description</label>
      <textarea name="description" rows="3" class="form-control"><?php echo sanitize($editProduct['description'] ?? ''); ?></textarea>
    </div>

    <div style="display:flex; gap:20px; align-items:center; margin-bottom:20px;">
      <label><input type="checkbox" name="featured" value="1" <?php echo (!empty($editProduct['featured'])) ? 'checked' : ''; ?>> Mark as Featured Product</label>
      <label><input type="checkbox" name="is_active" value="1" <?php echo (!isset($editProduct['is_active']) || $editProduct['is_active'] == 1) ? 'checked' : ''; ?>> Active / Visible on Store</label>
    </div>

    <button type="submit" name="save_product" class="btn btn-primary"><?php echo $editProduct ? 'UPDATE PRODUCT' : 'SAVE PRODUCT'; ?></button>
  </form>
</div>

<!-- PRODUCTS INVENTORY TABLE -->
<div class="admin-card">
  <div class="admin-card-title">
    <span><i class="fas fa-boxes"></i> Existing Products (<?php echo count($products); ?>)</span>
  </div>

  <!-- Product Search -->
  <form action="products.php" method="GET" style="display:flex; gap:10px; margin:15px 0; align-items:center;">
    <input type="text" name="search" class="form-control" style="max-width:420px;" placeholder="Search by product name, SKU, category or brand..." value="<?php echo sanitize($search); ?>">
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    <?php if ($search !== ''): ?>
      <a href="products.php" class="btn btn-outline" style="color:var(--dark); border-color:#ccc;">Clear</a>
    <?php endif; ?>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Image</th>
          <th>Name & SKU</th>
          <th>Category</th>
          <th>Brand</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): 
          $img = $p['primary_image'] ?? 'assets/images/products/default.jpg';
        ?>
          <tr>
            <td><strong>#<?php echo (int)$p['id']; ?></strong></td>
            <td><img src="../<?php echo sanitize($img); ?>" width="40" height="40" style="object-fit:contain; border-radius:4px; border:1px solid #ccc;" onerror="this.onerror=null; this.src='../assets/images/products/default.jpg';"></td>
            <td>
              <strong><?php echo sanitize($p['name']); ?></strong><br>
              <span style="font-size:11px; color:#777;"><?php echo sanitize($p['sku']); ?></span>
            </td>
            <td><?php echo sanitize($p['category_name']); ?></td>
            <td><?php echo sanitize($p['brand_name'] ?? 'N/A'); ?></td>
            <td>
              <strong><?php echo format_price($p['discount_price'] ?? $p['price']); ?></strong>
              <?php if (!empty($p['discount_price'])): ?>
                <br><span style="font-size:11px; color:#999; text-decoration:line-through;"><?php echo format_price($p['price']); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['stock_quantity'] <= 5): ?>
                <span style="color:#d32f2f; font-weight:800;"><?php echo $p['stock_quantity']; ?> (Low)</span>
              <?php else: ?>
                <span style="color:#2e7d32; font-weight:700;"><?php echo $p['stock_quantity']; ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['is_active']): ?>
                <span class="status-badge status-delivered">Active</span>
              <?php else: ?>
                <span class="status-badge status-cancelled">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="products.php?edit=<?php echo $p['id']; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline" style="padding:4px 8px; font-size:12px; color:var(--dark); border-color:#ccc;"><i class="fas fa-edit"></i> Edit</a>
              <?php if ($p['is_active']): ?>
                <a href="products.php?delete=<?php echo $p['id']; ?>" onclick="return confirm('Deactivate this product? It will remain in the database for existing orders.');" class="btn" style="padding:4px 8px; font-size:12px; background:#ffebee; color:#d32f2f;"><i class="fas fa-ban"></i> Deactivate</a>
              <?php else: ?>
                <a href="products.php?activate=<?php echo $p['id']; ?>" onclick="return confirm('Activate this product again?');" class="btn" style="padding:4px 8px; font-size:12px; background:#e8f5e9; color:#2e7d32;"><i class="fas fa-check"></i> Activate</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
