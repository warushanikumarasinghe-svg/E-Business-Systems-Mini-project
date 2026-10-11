<?php
$adminTitle = "Customer Accounts";
$adminPage = "customers";
require_once __DIR__ . '/includes/header.php';

// Fetch Customer Accounts with order statistics
$customers = $db->query("
  SELECT u.*, COUNT(o.id) as order_count, COALESCE(SUM(o.grand_total), 0) as total_spent
  FROM users u
  LEFT JOIN orders o ON u.id = o.user_id AND o.order_status != 'Cancelled'
  WHERE u.role = 'customer'
  GROUP BY u.id
  ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="admin-card">
  <div class="admin-card-title">
    <span><i class="fas fa-users" style="color:var(--admin-primary);"></i> Registered Customer Accounts (<?php echo count($customers); ?>)</span>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Customer ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Address & City</th>
          <th>Orders</th>
          <th>Total Spent</th>
          <th>Joined Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><code>#CUST-<?php echo $c['id']; ?></code></td>
            <td><strong><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></strong></td>
            <td><?php echo sanitize($c['email']); ?></td>
            <td><?php echo sanitize($c['phone']); ?></td>
            <td><?php echo sanitize($c['address'] ?? 'N/A'); ?>, <?php echo sanitize($c['city'] ?? ''); ?></td>
            <td><strong><?php echo $c['order_count']; ?></strong> orders</td>
            <td><strong style="color:var(--admin-primary);"><?php echo format_price($c['total_spent']); ?></strong></td>
            <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
