<?php
$pageTitle = "Order Receipt & Confirmation - InCart";
require_once __DIR__ . '/includes/header.php';
require_login();
$db = getDB();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Fetch Order Details
$stmt = $db->prepare("
  SELECT o.*, d.tracking_number, d.status as delivery_status, d.estimated_delivery
  FROM orders o
  LEFT JOIN deliveries d ON o.id = d.order_id
  WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash_error("Order not found.");
    header('Location: orders.php');
    exit;
}

// Check for PayHere Return Success Flag
if (isset($_GET['payhere_success']) && $order['payment_status'] !== 'Paid') {
    $updateStmt = $db->prepare("UPDATE orders SET payment_status = 'Paid', order_status = 'Processing' WHERE id = ?");
    $updateStmt->execute([$order_id]);

    $updatePayStmt = $db->prepare("UPDATE payments SET status = 'Paid' WHERE order_id = ?");
    $updatePayStmt->execute([$order_id]);

    $order['payment_status'] = 'Paid';
    $order['order_status'] = 'Processing';
}

// Fetch Order Items
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$order_id]);
$orderItems = $itemsStmt->fetchAll();
?>

<div class="container" style="max-width:800px; margin:50px auto;">
  <div style="background:var(--white); padding:40px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); text-align:center;">
    
    <div style="width:70px; height:70px; border-radius:50%; background:var(--primary-light); color:var(--primary); font-size:32px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
      <i class="fas fa-check"></i>
    </div>

    <h1 style="font-size:28px; font-weight:800; color:var(--dark); margin-bottom:8px;">Thank You For Your Order!</h1>
    <p style="color:var(--gray-600); font-size:15px; margin-bottom:25px;">Your order has been placed and is currently being processed by our store team.</p>

    <!-- ORDER SUMMARY BADGE -->
    <div style="background:var(--gray-100); padding:20px; border-radius:var(--radius-md); text-align:left; display:grid; grid-template-columns:1fr 1fr; gap:15px; font-size:14px; margin-bottom:30px;">
      <div>
        <p style="margin-bottom:6px;">Order Number: <strong style="color:var(--primary);"><?php echo sanitize($order['order_number']); ?></strong></p>
        <p style="margin-bottom:6px;">Order Date: <strong><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></strong></p>
        <p>Payment Method: <strong><?php echo sanitize($order['payment_method']); ?></strong> (<?php echo sanitize($order['payment_status']); ?>)</p>
      </div>

      <div>
        <p style="margin-bottom:6px;">Tracking Number: <strong><?php echo sanitize($order['tracking_number'] ?? 'N/A'); ?></strong></p>
        <p style="margin-bottom:6px;">Estimated Delivery: <strong><?php echo sanitize($order['estimated_delivery']); ?></strong></p>
        <p>Delivery Status: <span class="badge" style="background:var(--primary); color:white; padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo sanitize($order['order_status']); ?></span></p>
      </div>
    </div>

    <!-- ITEMS BREAKDOWN TABLE -->
    <h3 style="font-size:18px; font-weight:700; text-align:left; margin-bottom:15px;">Purchased Items</h3>
    <table class="order-table" style="margin-bottom:30px;">
      <thead>
        <tr>
          <th>Product Name</th>
          <th>Price</th>
          <th>Qty</th>
          <th style="text-align:right;">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orderItems as $item): ?>
          <tr>
            <td><?php echo sanitize($item['product_name']); ?></td>
            <td><?php echo format_price($item['price']); ?></td>
            <td><?php echo $item['quantity']; ?></td>
            <td style="text-align:right; font-weight:700; color:var(--primary);"><?php echo format_price($item['subtotal']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="text-align:right; font-size:15px; margin-bottom:30px;">
      <p style="margin-bottom:6px;">Items Subtotal: <strong><?php echo format_price($order['total_amount']); ?></strong></p>
      <p style="margin-bottom:6px;">Delivery Fee: <strong><?php echo format_price($order['delivery_fee']); ?></strong></p>
      <p style="font-size:20px; font-weight:800; color:var(--primary);">Grand Total: <?php echo format_price($order['grand_total']); ?></p>
    </div>

    <!-- SHIPPING ADDRESS -->
    <div style="background:var(--primary-light); padding:20px; border-radius:var(--radius-md); text-align:left; margin-bottom:30px; border:1px solid var(--primary);">
      <h4 style="font-size:16px; font-weight:700; color:var(--primary-hover); margin-bottom:8px;"><i class="fas fa-map-marker-alt"></i> Shipping Address</h4>
      <p style="font-size:14px; margin:0; color:var(--dark);">
        <strong><?php echo sanitize($order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?></strong><br>
        <?php echo sanitize($order['shipping_address']); ?>, <?php echo sanitize($order['shipping_city']); ?> <?php echo sanitize($order['shipping_postal_code']); ?><br>
        Phone: <?php echo sanitize($order['shipping_phone']); ?> | Email: <?php echo sanitize($order['shipping_email']); ?>
      </p>
    </div>

    <div style="display:flex; justify-content:center; gap:15px;">
      <a href="track-order.php?order_id=<?php echo sanitize($order['order_number']); ?>&email=<?php echo urlencode($order['shipping_email']); ?>" class="btn btn-accent"><i class="fas fa-map-marker-alt"></i> TRACK ORDER STATUS</a>
      <a href="shop.php" class="btn btn-outline" style="color:var(--dark); border-color:var(--gray-300);">CONTINUE SHOPPING</a>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
