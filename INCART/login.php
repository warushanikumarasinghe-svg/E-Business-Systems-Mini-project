<?php

ob_start();

$pageTitle = "Account Login - InCart Grocery";

require_once __DIR__ . '/includes/header.php';

$db = getDB();


// =====================================================
// CHECK IF USER IS ALREADY LOGGED IN
// =====================================================

if (is_logged_in()) {
    header('Location: account.php');
    exit;
}


// =====================================================
// VARIABLES
// =====================================================

$error = '';
$email = '';


// =====================================================
// SUCCESS MESSAGE
// =====================================================

$successMessage = '';

if (isset($_SESSION['flash_success'])) {

    $successMessage = $_SESSION['flash_success'];

    // Remove after displaying once
    unset($_SESSION['flash_success']);
}


// =====================================================
// LOGIN PROCESS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    // ---------------------------------------------
    // Validation
    // ---------------------------------------------

    if (empty($email) || empty($password)) {

        $error = "Please enter both email and password.";

    } else {

        // ---------------------------------------------
        // Find user by email
        // ---------------------------------------------

        $stmt = $db->prepare(
            "SELECT * FROM users WHERE email = ?"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        // ---------------------------------------------
        // Verify password
        // ---------------------------------------------

        if ($user && password_verify($password, $user['password'])) {


            // -----------------------------------------
            // Store logged-in user in session
            // -----------------------------------------

            $_SESSION['user_id'] =
                $user['id'];

            $_SESSION['user_name'] =
                $user['first_name'] . ' ' . $user['last_name'];

            $_SESSION['user_email'] =
                $user['email'];

            $_SESSION['user_role'] =
                $user['role'];


            // -----------------------------------------
            // Login success message
            // -----------------------------------------

            set_flash_success(
                "Welcome back, " . $user['first_name'] . "!"
            );


            // -----------------------------------------
            // Redirect after login
            // -----------------------------------------

            $returnUrl = $_POST['return'] ?? $_GET['return'] ?? '';

             if (!empty($returnUrl)) {

                header('Location: ' . $returnUrl);

            } elseif ($user['role'] === 'admin') {

                 header('Location: admin/index.php');

            } else {

                    header('Location: account.php');
            }

            exit;


        } else {

            $error =
                "Invalid email address or password.";
        }
    }
}

?>


<div class="container" style="max-width:450px; margin:60px auto;">

  <div style="
      background:var(--white);
      padding:35px;
      border-radius:var(--radius-lg);
      box-shadow:var(--shadow-sm);
      border:1px solid var(--gray-200);
  ">


    <!-- ================================================= -->
    <!-- TITLE -->
    <!-- ================================================= -->

    <h1 style="
        font-size:26px;
        font-weight:800;
        text-align:center;
        color:var(--dark);
        margin-bottom:8px;
    ">
        Welcome Back
    </h1>


    <p style="
        text-align:center;
        color:var(--gray-600);
        margin-bottom:25px;
        font-size:14px;
    ">
        Sign in to your InCart account
    </p>


    <!-- ================================================= -->
    <!-- SUCCESS MESSAGE -->
    <!-- ================================================= -->

    <?php if (!empty($successMessage)): ?>

      <div style="
          background:#d4edda;
          color:#155724;
          padding:14px;
          border:1px solid #c3e6cb;
          border-radius:6px;
          margin-bottom:20px;
          font-size:14px;
      ">

        <i class="fas fa-check-circle"></i>

        <?php echo sanitize($successMessage); ?>

      </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- ERROR MESSAGE -->
    <!-- ================================================= -->

    <?php if (!empty($error)): ?>

      <div class="alert alert-danger" style="margin-bottom:20px;">

        <i class="fas fa-exclamation-circle"></i>

        <?php echo sanitize($error); ?>

      </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- LOGIN FORM -->
    <!-- ================================================= -->

    <form
        action="login.php"
        method="POST"
        autocomplete="off"
    >
    <input
    type="hidden"
    name="return"
    value="<?php echo sanitize($_GET['return'] ?? $_POST['return'] ?? ''); ?>"
    >


      <!-- EMAIL -->

      <div style="margin-bottom:18px;">

        <label style="
            display:block;
            font-weight:600;
            font-size:14px;
            margin-bottom:6px;
        ">
          Email Address
        </label>


        <input
            type="email"
            name="email"
            required
            placeholder="customer@incart.lk"

            <?php
            // Only show entered email after a failed login.
            // Do not show old email on a fresh page load.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo 'value="' . sanitize($email) . '"';
            }
            ?>

            autocomplete="off"

            style="
                width:100%;
                padding:12px 14px;
                border-radius:6px;
                border:1px solid var(--gray-300);
                outline:none;
            "
        >

      </div>


      <!-- PASSWORD -->

      <div style="margin-bottom:25px;">

        <div style="
            display:flex;
            justify-content:space-between;
            margin-bottom:6px;
        ">

          <label style="
              font-weight:600;
              font-size:14px;
          ">
            Password
          </label>

        </div>


        <input
            type="password"
            name="password"
            required
            placeholder="••••••••"
            autocomplete="new-password"

            style="
                width:100%;
                padding:12px 14px;
                border-radius:6px;
                border:1px solid var(--gray-300);
                outline:none;
            "
        >

      </div>


      <!-- LOGIN BUTTON -->

      <button
          type="submit"
          class="btn btn-primary"
          style="
              width:100%;
              padding:14px;
              font-size:16px;
          "
      >
        LOG IN
      </button>


    </form>


    <!-- ================================================= -->
    <!-- DEMO ACCOUNTS -->
    <!-- ================================================= -->

    <div style="
        background:var(--gray-100);
        padding:12px;
        border-radius:6px;
        margin-top:20px;
        font-size:12px;
        color:var(--gray-800);
    ">

      <strong>Demo Accounts:</strong><br>

      • Customer:
      <code>customer@incart.lk</code>
      /
      <code>Customer123!@#</code>

      <br>

      • Admin:
      <code>admin@incart.lk</code>
      /
      <code>Admin123!@#</code>

    </div>


    <!-- ================================================= -->
    <!-- REGISTER -->
    <!-- ================================================= -->

    <div style="
        text-align:center;
        margin-top:20px;
        font-size:14px;
    ">

      Don't have an account?

      <a
          href="register.php"
          style="font-weight:700;"
      >
        Register Here
      </a>

    </div>


  </div>

</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
