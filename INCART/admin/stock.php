<?php
$adminTitle = "Stock Inventory Management";
$adminPage = "stock";
require_once __DIR__ . '/includes/header.php';

// Handle Stock Quantity Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $new_stock  = max(0, (int)$_POST['stock_quantity']);

    $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$new_stock, $product_id]);
    set_flash_success("Stock quantity updated successfully.");
    header('Location: stock.php');
    exit;
}

// Fetch All Products with Stock Details
$products = $db->query("
  SELECT p.id, p.name, p.sku, p.stock_quantity, c.name as category_name
  FROM products p
  JOIN categories c ON p.category_id = c.id
  ORDER BY p.stock_quantity ASC
")->fetchAll();
?>

<div class="admin-card">
  <div class="admin-card-title">
    <span><i class="fas fa-warehouse" style="color:var(--admin-primary);"></i> Quick Stock Inventory Control</span>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product Name</th>
          <th>SKU Code</th>
          <th>Category</th>
          <th>Current Stock</th>
          <th>Stock Status</th>
          <th>Update Quantity</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): 
          $stock = $p['stock_quantity'];
          if ($stock == 0) {
              $badgeClass = 'status-cancelled';
              $statusLabel = 'Out of Stock';
          } elseif ($stock <= 5) {
              $badgeClass = 'status-pending';
              $statusLabel = 'Low Stock Alert';
          } else {
              $badgeClass = 'status-delivered';
              $statusLabel = 'In Stock';
          }
        ?>
          <tr>
            <td><strong><?php echo sanitize($p['name']); ?></strong></td>
            <td><code><?php echo sanitize($p['sku']); ?></code></td>
            <td><?php echo sanitize($p['category_name']); ?></td>
            <td><strong style="font-size:16px;"><?php echo $stock; ?></strong></td>
            <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span></td>
            <td>
              <form action="stock.php" method="POST" style="display:flex; gap:8px; align-items:center;">
                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                <input type="number" name="stock_quantity" value="<?php echo $stock; ?>" min="0" style="width:90px; padding:6px; border-radius:4px; border:1px solid #ccc;">
                <button type="submit" name="update_stock" class="btn btn-primary" style="padding:6px 12px; font-size:12px;">UPDATE</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
