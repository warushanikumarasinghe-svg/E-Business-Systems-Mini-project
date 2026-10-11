<?php
$adminTitle = "Product Review Moderation";
$adminPage = "reviews";
require_once __DIR__ . '/includes/header.php';

// Handle Review Moderation Actions (Approve / Hide / Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $revId = (int)$_GET['id'];
    $act   = $_GET['action'];

    if ($act === 'approve') {
        $db->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?")->execute([$revId]);
        set_flash_success("Review approved and published.");
    } elseif ($act === 'hide') {
        $db->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?")->execute([$revId]);
        set_flash_success("Review hidden from public store.");
    } elseif ($act === 'delete') {
        $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$revId]);
        set_flash_success("Review deleted.");
    }
    header('Location: reviews.php');
    exit;
}

// Fetch Reviews with Product & Customer Info
$reviews = $db->query("
  SELECT r.*, p.name as product_name, u.first_name, u.last_name, u.email
  FROM reviews r
  JOIN products p ON r.product_id = p.id
  JOIN users u ON r.user_id = u.id
  ORDER BY r.created_at DESC
")->fetchAll();
?>

<div class="admin-card">
  <div class="admin-card-title">
    <span><i class="fas fa-star" style="color:var(--admin-primary);"></i> Customer Reviews Moderation (<?php echo count($reviews); ?>)</span>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Customer</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Status</th>
          <th>Submitted Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reviews as $r): ?>
          <tr>
            <td><strong><?php echo sanitize($r['product_name']); ?></strong></td>
            <td>
              <?php echo sanitize($r['first_name'] . ' ' . $r['last_name']); ?><br>
              <span style="font-size:11px; color:#777;"><?php echo sanitize($r['email']); ?></span>
            </td>
            <td><?php echo render_rating_stars($r['rating']); ?></td>
            <td style="max-width:280px; font-size:13px;"><?php echo sanitize($r['comment']); ?></td>
            <td>
              <?php if ($r['is_approved']): ?>
                <span class="status-badge status-delivered">Approved</span>
              <?php else: ?>
                <span class="status-badge status-pending">Pending Approval</span>
              <?php endif; ?>
            </td>
            <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
            <td>
              <?php if ($r['is_approved']): ?>
                <a href="reviews.php?action=hide&id=<?php echo $r['id']; ?>" class="btn" style="padding:3px 8px; font-size:12px; background:#fff3e0; color:#e65100;">Hide</a>
              <?php else: ?>
                <a href="reviews.php?action=approve&id=<?php echo $r['id']; ?>" class="btn btn-primary" style="padding:3px 8px; font-size:12px;">Approve</a>
              <?php endif; ?>
              <a href="reviews.php?action=delete&id=<?php echo $r['id']; ?>" onclick="return confirm('Delete review?');" class="btn" style="padding:3px 8px; font-size:12px; background:#ffebee; color:#d32f2f;">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
