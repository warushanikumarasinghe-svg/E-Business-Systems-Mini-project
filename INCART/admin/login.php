<?php
$pageTitle = "Admin Login - InCart";
require_once __DIR__ . '/../includes/functions.php';
$db = getDB();

if (is_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
        $_SESSION['user_email'] = $admin['email'];
        $_SESSION['user_role'] = 'admin';

        set_flash_success("Admin authentication successful.");
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid administrator credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - InCart</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background:#1a252f; display:flex; align-items:center; justify-content:center; min-height:100vh;">

  <div style="background:#ffffff; width:100%; max-width:400px; padding:35px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
    <div style="text-align:center; margin-bottom:25px;">
      <i class="fas fa-user-shield" style="font-size:48px; color:#2e7d32; margin-bottom:10px;"></i>
      <h2 style="font-size:24px; font-weight:800; color:#212529;">Admin Portal Login</h2>
      <p style="font-size:13px; color:#6c757d;">InCart E-Commerce Administration</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger" style="margin-bottom:20px; font-size:14px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <div style="margin-bottom:18px;">
        <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Admin Email</label>
        <input type="email" name="email" required placeholder="admin@incart.lk" value="admin@incart.lk" style="width:100%; padding:12px; border-radius:6px; border:1px solid #ccc;">
      </div>

      <div style="margin-bottom:25px;">
        <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Admin Password</label>
        <input type="password" name="password" required value="Admin123!@#" style="width:100%; padding:12px; border-radius:6px; border:1px solid #ccc;">
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:16px;">AUTHENTICATE & LOG IN</button>
    </form>
  </div>

</body>
</html>
