<?php
$pageTitle = "Order Tracking - InCart Grocery";
$currentPage = "track";
require_once __DIR__ . '/includes/header.php';
$db = getDB();

$orderInput = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$emailInput = isset($_GET['email']) ? trim($_GET['email']) : '';

$order = null;
$searched = false;
$errorMsg = '';

if ($orderInput !== '' && $emailInput !== '') {
    $searched = true;
    $stmt = $db->prepare("
      SELECT o.*, d.tracking_number, d.status as delivery_status, d.estimated_delivery, d.updated_at as status_updated_at
      FROM orders o
      LEFT JOIN deliveries d ON o.id = d.order_id
      WHERE (o.order_number = ? OR o.id = ?) AND o.shipping_email = ?
    ");
    $stmt->execute([$orderInput, (int)$orderInput, $emailInput]);
    $order = $stmt->fetch();

    if (!$order) {
        $errorMsg = "No matching order found for Order ID '{$orderInput}' and Email '{$emailInput}'. Please check details and try again.";
    }
}

// Order Status Steps Array
$statusSteps = [
    'Pending' => 1,
    'Confirmed' => 2,
    'Processing' => 3,
    'Out for Delivery' => 4,
    'Delivered' => 5
];

$currentStep = isset($order['order_status']) && isset($statusSteps[$order['order_status']]) ? $statusSteps[$order['order_status']] : 0;
?>

<div class="container" style="max-width:900px; margin:50px auto;">
  
  <div style="background:var(--white); padding:40px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); margin-bottom:40px;">
    <h1 style="font-size:28px; font-weight:800; text-align:center; color:var(--dark); margin-bottom:8px;">Track Your Grocery Order</h1>
    <p style="text-align:center; color:var(--gray-600); font-size:15px; margin-bottom:30px;">Enter your Order ID (e.g. INC-100852) and email address to get live delivery status updates.</p>

    <form action="track-order.php" method="GET" style="display:grid; grid-template-columns:1fr 1fr auto; gap:15px; align-items:end;">
      <div>
        <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Order ID / Number *</label>
        <input type="text" name="order_id" required placeholder="INC-100852" value="<?php echo sanitize($orderInput); ?>" style="width:100%; padding:12px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
      </div>

      <div>
        <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Billing Email Address *</label>
        <input type="email" name="email" required placeholder="customer@incart.lk" value="<?php echo sanitize($emailInput); ?>" style="width:100%; padding:12px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
      </div>

      <button type="submit" class="btn btn-primary" style="padding:12px 24px; font-size:15px;"><i class="fas fa-search"></i> TRACK ORDER</button>
    </form>
  </div>

  <?php if ($searched && $errorMsg): ?>
    <div class="alert alert-danger" style="text-align:center;">
      <i class="fas fa-exclamation-circle"></i> <?php echo sanitize($errorMsg); ?>
    </div>
  <?php endif; ?>

  <?php if ($order): ?>
    <div style="background:var(--white); padding:40px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
      
      <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--gray-200); padding-bottom:20px; margin-bottom:30px; flex-wrap:wrap; gap:15px;">
        <div>
          <h2 style="font-size:22px; font-weight:800; color:var(--dark); margin-bottom:4px;">Order: <span style="color:var(--primary);"><?php echo sanitize($order['order_number']); ?></span></h2>
          <span style="font-size:13px; color:var(--gray-600);">Placed on <?php echo date('F d, Y \a\t h:i A', strtotime($order['created_at'])); ?></span>
        </div>

        <div style="text-align:right;">
          <span style="font-size:14px; color:var(--gray-600); display:block;">Estimated Delivery:</span>
          <strong style="font-size:16px; color:var(--primary);"><?php echo sanitize($order['estimated_delivery']); ?></strong>
        </div>
      </div>

      <!-- VISUAL STEPPER TIMELINE -->
      <h3 style="font-size:18px; font-weight:700; margin-bottom:20px;">Live Delivery Progress</h3>
      <div class="timeline">
        <div class="timeline-step <?php echo $currentStep >= 1 ? 'completed' : ''; ?>">
          <div class="step-icon"><i class="fas fa-file-invoice"></i></div>
          <strong style="font-size:14px; display:block;">ORDER PLACED</strong>
          <span style="font-size:11px; color:var(--gray-600);">Received</span>
        </div>

        <div class="timeline-step <?php echo $currentStep >= 2 ? 'completed' : ''; ?>">
          <div class="step-icon"><i class="fas fa-check-circle"></i></div>
          <strong style="font-size:14px; display:block;">CONFIRMED</strong>
          <span style="font-size:11px; color:var(--gray-600);">Verified</span>
        </div>

        <div class="timeline-step <?php echo $currentStep >= 3 ? 'completed' : ''; ?>">
          <div class="step-icon"><i class="fas fa-box"></i></div>
          <strong style="font-size:14px; display:block;">PROCESSING</strong>
          <span style="font-size:11px; color:var(--gray-600);">Packed Fresh</span>
        </div>

        <div class="timeline-step <?php echo $currentStep >= 4 ? 'completed' : ''; ?>">
          <div class="step-icon"><i class="fas fa-truck"></i></div>
          <strong style="font-size:14px; display:block;">OUT FOR DELIVERY</strong>
          <span style="font-size:11px; color:var(--gray-600);">On the way</span>
        </div>

        <div class="timeline-step <?php echo $currentStep >= 5 ? 'completed' : ''; ?>">
          <div class="step-icon"><i class="fas fa-home"></i></div>
          <strong style="font-size:14px; display:block;">DELIVERED</strong>
          <span style="font-size:11px; color:var(--gray-600);">Handed over</span>
        </div>
      </div>

      <!-- SUMMARY DATA BOX -->
      <div style="background:var(--gray-100); padding:20px; border-radius:var(--radius-md); display:grid; grid-template-columns:1fr 1fr; gap:20px; font-size:14px;">
        <div>
          <h4 style="font-size:15px; font-weight:700; margin-bottom:8px; color:var(--dark);">Delivery Address</h4>
          <p style="margin:0; line-height:1.5; color:var(--gray-800);">
            <strong><?php echo sanitize($order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?></strong><br>
            <?php echo sanitize($order['shipping_address']); ?>, <?php echo sanitize($order['shipping_city']); ?><br>
            Phone: <?php echo sanitize($order['shipping_phone']); ?>
          </p>
        </div>

        <div>
          <h4 style="font-size:15px; font-weight:700; margin-bottom:8px; color:var(--dark);">Order Summary</h4>
          <p style="margin-bottom:4px;">Grand Total: <strong style="color:var(--primary); font-size:16px;"><?php echo format_price($order['grand_total']); ?></strong></p>
          <p style="margin-bottom:4px;">Payment Method: <strong><?php echo sanitize($order['payment_method']); ?></strong></p>
          <p style="margin:0;">Tracking Number: <strong><?php echo sanitize($order['tracking_number'] ?? 'N/A'); ?></strong></p>
        </div>
      </div>

    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
