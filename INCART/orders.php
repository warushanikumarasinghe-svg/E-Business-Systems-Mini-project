<?php
$pageTitle = "My Orders - InCart Grocery";
require_once __DIR__ . '/includes/header.php';
require_login();
$db = getDB();

$user_id = $_SESSION['user_id'];

// Fetch All Customer Orders
$stmt = $db->prepare("
  SELECT o.*, d.tracking_number, d.status as delivery_status, COUNT(oi.id) as total_items
  FROM orders o
  LEFT JOIN deliveries d ON o.id = d.order_id
  LEFT JOIN order_items oi ON o.id = oi.order_id
  WHERE o.user_id = ?
  GROUP BY o.id
  ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
?>

<div class="container" style="margin:40px auto;">
  <h1 style="font-size:28px; font-weight:800; color:var(--dark); margin-bottom:24px;">My Order History</h1>

  <?php if (empty($orders)): ?>
    <div style="background:var(--white); padding:60px; text-align:center; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm);">
      <i class="fas fa-box-open" style="font-size:64px; color:var(--gray-300); margin-bottom:20px;"></i>
      <h2 style="font-size:24px; font-weight:800; color:var(--dark); margin-bottom:10px;">No past orders found</h2>
      <p style="color:var(--gray-600); margin-bottom:25px;">You haven't placed any orders with InCart yet.</p>
      <a href="shop.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> START SHOPPING</a>
    </div>
  <?php else: ?>
    <div style="display:flex; flex-direction:column; gap:20px;">
      <?php foreach ($orders as $ord): ?>
        <div style="background:var(--white); border-radius:var(--radius-lg); padding:25px; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--gray-200); padding-bottom:15px; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
            <div>
              <strong style="font-size:18px; color:var(--primary);"><?php echo sanitize($ord['order_number']); ?></strong>
              <span style="font-size:13px; color:var(--gray-600); margin-left:10px;">Placed on <?php echo date('M d, Y', strtotime($ord['created_at'])); ?></span>
            </div>
            <div>
              <span style="background:var(--primary-light); color:var(--primary-hover); font-weight:700; font-size:13px; padding:6px 12px; border-radius:20px;">
                <i class="fas fa-truck"></i> <?php echo sanitize($ord['order_status']); ?>
              </span>
            </div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <div>
              <p style="font-size:14px; margin-bottom:4px;">Total Items: <strong><?php echo $ord['total_items']; ?> Items</strong></p>
              <p style="font-size:14px; margin-bottom:4px;">Payment Method: <strong><?php echo sanitize($ord['payment_method']); ?></strong> (<?php echo sanitize($ord['payment_status']); ?>)</p>
              <p style="font-size:14px; margin:0;">Tracking Number: <strong><?php echo sanitize($ord['tracking_number'] ?? 'N/A'); ?></strong></p>
            </div>

            <div style="text-align:right;">
              <span style="font-size:22px; font-weight:800; color:var(--primary); display:block; margin-bottom:10px;"><?php echo format_price($ord['grand_total']); ?></span>
              <div style="display:flex; gap:10px;">
                <a href="order-confirmation.php?id=<?php echo $ord['id']; ?>" class="btn btn-outline" style="font-size:13px; padding:8px 16px; color:var(--dark); border-color:var(--gray-300);"><i class="fas fa-file-alt"></i> Receipt</a>
                <a href="track-order.php?order_id=<?php echo sanitize($ord['order_number']); ?>&email=<?php echo urlencode($ord['shipping_email']); ?>" class="btn btn-accent" style="font-size:13px; padding:8px 16px;"><i class="fas fa-map-marker-alt"></i> Track</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
