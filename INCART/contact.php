<?php
$pageTitle = "Contact Us - InCart Grocery";
$currentPage = "contact";
require_once __DIR__ . '/includes/header.php';
$db = getDB();

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($subject) && !empty($message)) {
        $ins = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $ins->execute([$name, $email, $subject, $message]);
        $successMsg = "Thank you! Your message has been sent to InCart customer support. We will get back to you shortly.";
    } else {
        $errorMsg = "Please fill in all fields with a valid email address.";
    }
}
?>

<div class="container" style="margin:50px auto;">
  <h1 style="font-size:32px; font-weight:800; text-align:center; color:var(--dark); margin-bottom:10px;">Contact InCart Support</h1>
  <p style="text-align:center; color:var(--gray-600); margin-bottom:40px;">Have questions about your order, delivery, or product freshness? We're here to help!</p>

  <?php if ($successMsg): ?>
    <div class="alert alert-success" style="max-width:800px; margin:0 auto 30px;"><i class="fas fa-check-circle"></i> <?php echo sanitize($successMsg); ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="alert alert-danger" style="max-width:800px; margin:0 auto 30px;"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($errorMsg); ?></div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px; background:var(--white); padding:40px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
    
    <!-- LEFT: GET IN TOUCH -->
    <div>
      <h2 style="font-size:24px; font-weight:800; color:var(--dark); margin-bottom:20px;">Get In Touch</h2>
      <p style="font-size:15px; color:var(--gray-800); line-height:1.6; margin-bottom:30px;">
        Whether you have a query about our online supermarket service or want to provide feedback, our support team is available 7 days a week.
      </p>

      <ul style="display:flex; flex-direction:column; gap:20px; font-size:15px;">
        <li style="display:flex; gap:16px; align-items:flex-start;">
          <div style="width:45px; height:45px; border-radius:50%; background:var(--primary-light); color:var(--primary); font-size:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div>
            <strong>Store Headquarters Address</strong>
            <p style="color:var(--gray-600); margin:0;">No. 45, Galle Road, Colombo 03, Sri Lanka</p>
          </div>
        </li>

        <li style="display:flex; gap:16px; align-items:flex-start;">
          <div style="width:45px; height:45px; border-radius:50%; background:var(--accent-light); color:var(--accent); font-size:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fas fa-phone-alt"></i>
          </div>
          <div>
            <strong>Customer Hotline</strong>
            <p style="color:var(--gray-600); margin:0;">+94 11 234 5678 / +94 77 123 4567</p>
          </div>
        </li>

        <li style="display:flex; gap:16px; align-items:flex-start;">
          <div style="width:45px; height:45px; border-radius:50%; background:var(--primary-light); color:var(--primary); font-size:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fas fa-envelope"></i>
          </div>
          <div>
            <strong>Email Enquiries</strong>
            <p style="color:var(--gray-600); margin:0;">support@incart.lk / sales@incart.lk</p>
          </div>
        </li>

        <li style="display:flex; gap:16px; align-items:flex-start;">
          <div style="width:45px; height:45px; border-radius:50%; background:var(--accent-light); color:var(--accent); font-size:20px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <i class="fas fa-clock"></i>
          </div>
          <div>
            <strong>Delivery & Store Hours</strong>
            <p style="color:var(--gray-600); margin:0;">Monday – Sunday: 7:00 AM – 10:00 PM</p>
          </div>
        </li>
      </ul>
    </div>

    <!-- RIGHT: SEND US A MESSAGE FORM -->
    <div style="background:var(--gray-100); padding:30px; border-radius:var(--radius-md);">
      <h2 style="font-size:22px; font-weight:800; color:var(--dark); margin-bottom:20px;">Send Us A Message</h2>

      <form action="contact.php" method="POST">
        <div style="margin-bottom:15px;">
          <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Your Name *</label>
          <input type="text" name="name" required placeholder="Kasun Perera" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
        </div>

        <div style="margin-bottom:15px;">
          <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Your Email Address *</label>
          <input type="email" name="email" required placeholder="kasun@example.com" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
        </div>

        <div style="margin-bottom:15px;">
          <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Subject *</label>
          <input type="text" name="subject" required placeholder="Order inquiry / Feedback" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Message Details *</label>
          <textarea name="message" rows="5" required placeholder="How can we help you today?" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;"></textarea>
        </div>

        <button type="submit" name="send_message" class="btn btn-primary" style="width:100%; padding:12px; font-size:15px;">SEND MESSAGE</button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
