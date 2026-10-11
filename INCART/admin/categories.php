<?php
$adminTitle = "Category Management";
$adminPage = "categories";
require_once __DIR__ . '/includes/header.php';

// Handle Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-shopping-basket');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

    if (!empty($name)) {
        if ($id > 0) {
            $db->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, is_active = ? WHERE id = ?")->execute([$name, $slug, $icon, $is_active, $id]);
            set_flash_success("Category '{$name}' updated.");
        } else {
            $db->prepare("INSERT INTO categories (name, slug, icon, is_active) VALUES (?, ?, ?, ?)")->execute([$name, $slug, $icon, $is_active]);
            set_flash_success("Category '{$name}' created.");
        }
        header('Location: categories.php');
        exit;
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
    set_flash_success("Category deleted.");
    header('Location: categories.php');
    exit;
}

$categories = $db->query("
  SELECT c.*, COUNT(p.id) as product_count 
  FROM categories c 
  LEFT JOIN products p ON c.id = p.category_id 
  GROUP BY c.id 
  ORDER BY c.name ASC
")->fetchAll();

$editCat = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
}
?>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:30px;">
  
  <!-- FORM -->
  <div class="admin-card">
    <div class="admin-card-title">
      <span><?php echo $editCat ? 'Edit Category' : 'Add New Category'; ?></span>
    </div>

    <form action="categories.php" method="POST">
      <?php if ($editCat): ?>
        <input type="hidden" name="id" value="<?php echo $editCat['id']; ?>">
      <?php endif; ?>

      <div class="form-group">
        <label>Category Name *</label>
        <input type="text" name="name" class="form-control" required value="<?php echo sanitize($editCat['name'] ?? ''); ?>">
      </div>

      <div class="form-group">
  <label>Category Image / Icon</label>
  <input type="text"
         name="icon"
         class="form-control"
         placeholder="e.g. assets/images/category/bakery.jpg"
         value="<?php echo sanitize($editCat['icon'] ?? ''); ?>">

  <small style="color:#777;">
    Enter image path or FontAwesome class.
  </small>
</div>

      <div class="form-group">
        <label><input type="checkbox" name="is_active" value="1" <?php echo (!isset($editCat['is_active']) || $editCat['is_active'] == 1) ? 'checked' : ''; ?>> Active Status</label>
      </div>

      <button type="submit" name="save_category" class="btn btn-primary"><?php echo $editCat ? 'UPDATE CATEGORY' : 'SAVE CATEGORY'; ?></button>
    </form>
  </div>

  <!-- TABLE -->
  <div class="admin-card">
    <div class="admin-card-title">
      <span>Store Categories (<?php echo count($categories); ?>)</span>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Icon</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Products</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td>
<?php
$icon = trim($c['icon'] ?? '');

if (!empty($icon) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $icon)) {
?>
    <img 
        src="../<?php echo htmlspecialchars($icon); ?>" 
        alt="<?php echo htmlspecialchars($c['name']); ?>"
        style="width:50px; height:50px; object-fit:cover; border-radius:8px;"
    >
<?php
} else {
?>
    <i class="fas <?php echo htmlspecialchars($icon ?: 'fa-shopping-basket'); ?>"
       style="font-size:18px; color:var(--admin-primary);">
    </i>
<?php
}
?>
</td>

              <td><strong><?php echo sanitize($c['name']); ?></strong></td>
              <td><code><?php echo sanitize($c['slug']); ?></code></td>
              <td><?php echo $c['product_count']; ?> items</td>
              <td>
                <span class="status-badge <?php echo $c['is_active'] ? 'status-delivered' : 'status-cancelled'; ?>">
                  <?php echo $c['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
              </td>
              <td>
                <a href="categories.php?edit=<?php echo $c['id']; ?>" class="btn btn-outline" style="padding:3px 8px; font-size:12px; color:var(--dark); border-color:#ccc;">Edit</a>
                <a href="categories.php?delete=<?php echo $c['id']; ?>" onclick="return confirm('Delete category?');" class="btn" style="padding:3px 8px; font-size:12px; background:#ffebee; color:#d32f2f;">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
