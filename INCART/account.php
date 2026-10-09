<?php
$pageTitle = "My Account - InCart Grocery";

require_once __DIR__ . '/includes/functions.php';

require_login();



$db = getDB();

$user_id = $_SESSION['user_id'];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');

    if (!empty($first_name) && !empty($last_name) && !empty($phone)) {
        $upd = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, city = ? WHERE id = ?");
        $upd->execute([$first_name, $last_name, $phone, $address, $city, $user_id]);
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        set_flash_success("Profile details updated successfully.");
        header('Location: account.php');
        exit;
    } else {
        set_flash_error("First Name, Last Name and Phone are required.");
    }
}
// =====================================================
// CHANGE PASSWORD
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Get current password from database
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);


    // 1. CHECK CURRENT PASSWORD
    if (!$u || !password_verify($current_pass, $u['password'])) {

        set_flash_error("Incorrect current password.");

    }

    // 2. CHECK NEW PASSWORD LENGTH
    elseif (strlen($new_pass) < 6) {

        set_flash_error("New password must be at least 6 characters.");

    }

    // 3. CHECK PASSWORD CONFIRMATION
    elseif ($new_pass !== $confirm_pass) {

        set_flash_error("New password and confirm password do not match.");

    }

    // 4. UPDATE ONLY IF EVERYTHING IS CORRECT
    else {

        $newHash = password_hash($new_pass, PASSWORD_DEFAULT);

        $update = $db->prepare(
            "UPDATE users SET password = ? WHERE id = ?"
        );

        $update->execute([
            $newHash,
            $user_id
        ]);

        set_flash_success(
            "Your password has been changed successfully."
        );

        header('Location: account.php');
        exit;
    }
}

// Fetch User Profile
$stmt = $db->prepare("
    SELECT *, created_at AS registration_date
    FROM users
    WHERE id = ?
");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();

// Fetch Summary Stats
$orderCount = $db->query("SELECT COUNT(*) FROM orders WHERE user_id = {$user_id}")->fetchColumn();
$wishCount = get_wishlist_count();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="margin:40px auto;">
  <h1 style="font-size:28px; font-weight:800; color:var(--dark); margin-bottom:24px;">My Account Dashboard</h1>

  <div style="display:grid; grid-template-columns: 240px 1fr; gap:30px;">

    <!-- ACCOUNT SIDEBAR MENU -->
    <div style="background:var(--white); padding:20px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); height:fit-content;">
      <div style="text-align:center; padding-bottom:20px; border-bottom:1px solid var(--gray-200); margin-bottom:15px;">
        <div style="width:70px; height:70px; border-radius:50%; background:var(--primary-light); color:var(--primary); font-size:28px; display:flex; align-items:center; justify-content:center; margin:0 auto 10px;">
          <i class="fas fa-user"></i>
        </div>
        <strong style="font-size:16px; color:var(--dark); display:block;"><?php echo sanitize($userData['first_name'] . ' ' . $userData['last_name']); ?></strong>
        <span style="font-size:12px; color:var(--gray-600);"><?php echo sanitize($userData['email']); ?></span>
      </div>

      <ul style="display:flex; flex-direction:column; gap:6px;">
        <li><a href="account.php" style="display:block; padding:10px 14px; border-radius:6px; background:var(--primary-light); color:var(--primary); font-weight:700;"><i class="fas fa-user-circle"></i> Profile Overview</a></li>
        <li><a href="orders.php" style="display:block; padding:10px 14px; border-radius:6px; color:var(--dark); font-weight:500;"><i class="fas fa-box"></i> My Orders (<?php echo $orderCount; ?>)</a></li>
        <li><a href="wishlist.php" style="display:block; padding:10px 14px; border-radius:6px; color:var(--dark); font-weight:500;"><i class="fas fa-heart"></i> Wishlist (<?php echo $wishCount; ?>)</a></li>
        <?php if (is_admin()): ?>
          <li><a href="admin/index.php" style="display:block; padding:10px 14px; border-radius:6px; color:var(--primary); font-weight:700;"><i class="fas fa-user-shield"></i> Admin Dashboard</a></li>
        <?php endif; ?>
        <li><a href="logout.php" style="display:block; padding:10px 14px; border-radius:6px; color:var(--danger); font-weight:600;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </div>

    <!-- MAIN ACCOUNT CONTENT -->
    <div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:30px;">
        <div style="background:var(--white); padding:20px; border-radius:var(--radius-md); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); display:flex; align-items:center; gap:15px;">
          <i class="fas fa-shopping-bag" style="font-size:32px; color:var(--primary);"></i>
          <div>
            <h3 style="font-size:24px; font-weight:800; margin:0;"><?php echo $orderCount; ?></h3>
            <span style="font-size:13px; color:var(--gray-600);">Total Orders Placed</span>
          </div>
        </div>

        <div style="background:var(--white); padding:20px; border-radius:var(--radius-md); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); display:flex; align-items:center; gap:15px;">
          <i class="fas fa-heart" style="font-size:32px; color:var(--accent);"></i>
          <div>
            <h3 style="font-size:24px; font-weight:800; margin:0;"><?php echo $wishCount; ?></h3>
            <span style="font-size:13px; color:var(--gray-600);">Items Saved in Wishlist</span>
          </div>
        </div>
      </div>

      <!-- EDIT PROFILE FORM -->
      <div style="background:var(--white); padding:30px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); margin-bottom:30px;">
        <h3 style="font-size:20px; font-weight:800; margin-bottom:20px; color:var(--dark);">Edit Personal Profile</h3>

        <form action="account.php" method="POST">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">First Name</label>
              <input type="text" name="first_name" required value="<?php echo sanitize($userData['first_name']); ?>" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Last Name</label>
              <input type="text" name="last_name" required value="<?php echo sanitize($userData['last_name']); ?>" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Email Address (Read-only)</label>
              <input type="email" value="<?php echo sanitize($userData['email']); ?>" disabled style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); background:var(--gray-100);">
            </div>
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Phone Number</label>
              <input type="tel" name="phone" required value="<?php echo sanitize($userData['phone']); ?>" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
          </div>

          <div style="display:grid; grid-template-columns:2fr 1fr; gap:15px; margin-bottom:20px;">
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Delivery Street Address</label>
              <input type="text" name="address" value="<?php echo sanitize($userData['address']); ?>" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">City</label>
              <input type="text" name="city" value="<?php echo sanitize($userData['city']); ?>" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
          </div>

          <button type="submit" name="update_profile" class="btn btn-primary">SAVE PROFILE DETAILS</button>
        </form>
      </div>

      <!-- CHANGE PASSWORD FORM -->
      <div style="background:var(--white); padding:30px; border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); border:1px solid var(--gray-200);">
        <h3 style="font-size:20px; font-weight:800; margin-bottom:20px; color:var(--dark);">Change Security Password</h3>

        <form action="account.php" method="POST">
          <div style="margin-bottom:15px;">
            <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Current Password</label>
            <input type="password" name="current_password" required style="width:100%; max-width:400px; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; max-width:600px; margin-bottom:20px;">
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">New Password</label>
              <input type="password" name="new_password" required minlength="6" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
            <div>
              <label style="display:block; font-weight:600; font-size:14px; margin-bottom:6px;">Confirm New Password</label>
              <input type="password" name="confirm_password" required minlength="6" style="width:100%; padding:10px 14px; border-radius:6px; border:1px solid var(--gray-300); outline:none;">
            </div>
          </div>

          <button type="submit" name="change_password" class="btn btn-accent">UPDATE PASSWORD</button>
        </form>
      </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
