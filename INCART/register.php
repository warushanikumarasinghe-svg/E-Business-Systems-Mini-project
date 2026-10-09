<?php

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Register - InCart Grocery";

require_once __DIR__ . '/includes/functions.php';

$db = getDB();

$errors = [];

$first_name = '';
$last_name  = '';
$email      = '';
$phone      = '';
$address    = '';
$city       = '';


// =====================================================
// REGISTRATION FORM SUBMITTED
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');


    // =================================================
    // VALIDATION
    // =================================================

    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[@!%*?&]/', $password)
    ) {
        $errors[] = "Password must be at least 8 characters and contain uppercase, lowercase, number and special character.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }


    // =================================================
    // CHECK DUPLICATE EMAIL
    // =================================================

    if (empty($errors)) {

        $stmt = $db->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = "An account with this email already exists.";
        }
    }


    // =================================================
    // CREATE ACCOUNT
    // =================================================

    if (empty($errors)) {

        // Hash password
        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        $stmt = $db->prepare(
            "INSERT INTO users
            (first_name, last_name, email, phone, password, role, address, city)
            VALUES (?, ?, ?, ?, ?, 'customer', ?, ?)"
        );

        $success = $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone,
            $hashed_password,
            $address,
            $city
        ]);


    if ($success) {

    set_flash_success(
        "Registration successful! Please login to your account."
    );

    header('Location: login.php');
    exit;

    } else {

            $errors[] = "Registration failed. Please try again.";
        }
    }
}


// =====================================================
// HEADER
// =====================================================

require_once __DIR__ . '/includes/header.php';

?>

<div class="container" style="max-width:550px; margin:50px auto;">

    <div style="
        background:var(--white);
        padding:35px;
        border-radius:var(--radius-lg);
        box-shadow:var(--shadow-sm);
        border:1px solid var(--gray-200);
    ">

        <!-- TITLE -->

        <h1 style="
            font-size:26px;
            font-weight:800;
            text-align:center;
            color:var(--dark);
            margin-bottom:8px;
        ">
            Create an Account
        </h1>

        <p style="
            text-align:center;
            color:var(--gray-600);
            margin-bottom:25px;
            font-size:14px;
        ">
            Register for your InCart account
        </p>


        <!-- ERRORS -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger" style="margin-bottom:20px;">

                <ul style="
                    margin-left:20px;
                    list-style-type:disc;
                ">

                    <?php foreach ($errors as $err): ?>

                        <li>
                            <?php echo sanitize($err); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- REGISTRATION FORM -->

        <form action="register.php" method="POST">


            <!-- FIRST NAME -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    First Name
                </label>

                <input
                    type="text"
                    name="first_name"
                    required
                    value="<?php echo sanitize($first_name); ?>"
                    placeholder="Enter your first name"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- LAST NAME -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Last Name
                </label>

                <input
                    type="text"
                    name="last_name"
                    required
                    value="<?php echo sanitize($last_name); ?>"
                    placeholder="Enter your last name"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- EMAIL -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    required
                    value="<?php echo sanitize($email); ?>"
                    placeholder="Enter your email"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- PHONE -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Phone Number
                </label>

                <input
                    type="text"
                    name="phone"
                    required
                    value="<?php echo sanitize($phone); ?>"
                    placeholder="Enter your phone number"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- PASSWORD -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    placeholder="Enter your password"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- CONFIRM PASSWORD -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Confirm Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    required
                    placeholder="Confirm your password"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- ADDRESS -->

            <div style="margin-bottom:18px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    Address
                </label>

                <textarea
                    name="address"
                    required
                    placeholder="Enter your address"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                        resize:vertical;
                    "
                ><?php echo sanitize($address); ?></textarea>

            </div>


            <!-- CITY -->

            <div style="margin-bottom:25px;">

                <label style="
                    display:block;
                    font-weight:600;
                    margin-bottom:6px;
                ">
                    City
                </label>

                <input
                    type="text"
                    name="city"
                    required
                    value="<?php echo sanitize($city); ?>"
                    placeholder="Enter your city"
                    style="
                        width:100%;
                        padding:12px 14px;
                        border-radius:6px;
                        border:1px solid var(--gray-300);
                    "
                >

            </div>


            <!-- REGISTER BUTTON -->

            <button
                type="submit"
                class="btn btn-primary"
                style="
                    width:100%;
                    padding:14px;
                    font-size:16px;
                "
            >
                REGISTER
            </button>

        </form>


        <!-- LOGIN LINK -->

        <div style="
            text-align:center;
            margin-top:20px;
            font-size:14px;
        ">

            Already have an account?

            <a
                href="login.php"
                style="font-weight:700;"
            >
                Login Here
            </a>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>