<?php
$adminTitle = "Brand Management";
$adminPage = "brands";
require_once __DIR__ . '/includes/header.php';

// Handle Save Brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!empty($name)) {
        if ($id > 0) {
            $db->prepare("UPDATE brands SET name = ?, is_active = ? WHERE id = ?")->execute([$name, $is_active, $id]);
            set_flash_success("Brand '{$name}' updated.");
        } else {
            $db->prepare("INSERT INTO brands (name, is_active) VALUES (?, ?)")->execute([$name, $is_active]);
            set_flash_success("Brand '{$name}' created.");
        }
        header('Location: brands.php');
        exit;
    }
}

// Handle Delete Brand
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM brands WHERE id = ?")->execute([$delId]);
    set_flash_success("Brand deleted.");
    header('Location: brands.php');
    exit;
}

$brands = $db->query("
  SELECT b.*, COUNT(p.id) as product_count 
  FROM brands b 
  LEFT JOIN products p ON b.id = p.brand_id 
  GROUP BY b.id 
  ORDER BY b.name ASC
")->fetchAll();

$editBrand = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$editId]);
    $editBrand = $stmt->fetch();
}
?>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
  
  <!-- FORM -->
  <div class="admin-card">
    <div class="admin-card-title">
      <span><?php echo $editBrand ? 'Edit Brand' : 'Add New Brand'; ?></span>
    </div>

    <form action="brands.php" method="POST">
      <?php if ($editBrand): ?>
        <input type="hidden" name="id" value="<?php echo $editBrand['id']; ?>">
      <?php endif; ?>

      <div class="form-group">
        <label>Brand Name *</label>
        <input type="text" name="name" class="form-control" required value="<?php echo sanitize($editBrand['name'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label><input type="checkbox" name="is_active" value="1" <?php echo (!isset($editBrand['is_active']) || $editBrand['is_active'] == 1) ? 'checked' : ''; ?>> Active Status</label>
      </div>

      <button type="submit" name="save_brand" class="btn btn-primary"><?php echo $editBrand ? 'UPDATE BRAND' : 'SAVE BRAND'; ?></button>
    </form>
  </div>

  <!-- TABLE -->
  <div class="admin-card">
    <div class="admin-card-title">
      <span>Grocery Brands (<?php echo count($brands); ?>)</span>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Brand Name</th>
            <th>Associated Products</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($brands as $b): ?>
            <tr>
              <td><strong><?php echo sanitize($b['name']); ?></strong></td>
              <td><?php echo $b['product_count']; ?> items</td>
              <td>
                <span class="status-badge <?php echo $b['is_active'] ? 'status-delivered' : 'status-cancelled'; ?>">
                  <?php echo $b['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
              </td>
              <td>
                <a href="brands.php?edit=<?php echo $b['id']; ?>" class="btn btn-outline" style="padding:3px 8px; font-size:12px; color:var(--dark); border-color:#ccc;">Edit</a>
                <a href="brands.php?delete=<?php echo $b['id']; ?>" onclick="return confirm('Delete brand?');" class="btn" style="padding:3px 8px; font-size:12px; background:#ffebee; color:#d32f2f;">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
