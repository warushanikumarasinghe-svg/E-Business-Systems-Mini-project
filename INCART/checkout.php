<?php

$pageTitle = "Checkout - InCart Grocery";

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/payhere.php';

require_login();

require_once __DIR__ . '/includes/header.php';

$db = getDB();

$user_id = $_SESSION['user_id'];
$cart_id = get_cart_id();


// ============================================================
// FETCH CART ITEMS
// ============================================================

$stmt = $db->prepare("
    SELECT 
        ci.*, 
        p.name, 
        p.price, 
        p.discount_price, 
        p.stock_quantity, 
        p.unit
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.cart_id = ?
");

$stmt->execute([$cart_id]);

$cartItems = $stmt->fetchAll();


// ============================================================
// CHECK EMPTY CART
// ============================================================

if (empty($cartItems)) {

    set_flash_error(
        "Your cart is empty. Please add items before checking out."
    );

    header('Location: cart.php');
    exit;
}


// ============================================================
// FETCH USER PROFILE
// ============================================================

$userStmt = $db->prepare("
    SELECT *
    FROM users
    WHERE id = ?
");

$userStmt->execute([$user_id]);

$userInfo = $userStmt->fetch();


// ============================================================
// CALCULATE TOTALS
// ============================================================

$subtotal = 0;

foreach ($cartItems as $item) {

    $price = (
        !empty($item['discount_price']) &&
        $item['discount_price'] < $item['price']
    )
        ? $item['discount_price']
        : $item['price'];

    $subtotal += $price * $item['quantity'];
}


$deliveryType = $_POST['delivery_type'] ?? 'Standard';

$standardFee = ($subtotal >= 5000)
    ? 0.00
    : 350.00;

$deliveryFee = ($deliveryType === 'Express')
    ? 550.00
    : $standardFee;

$grandTotal = $subtotal + $deliveryFee;


$errors = [];


// ============================================================
// PAYMENT CANCELLED MESSAGE
// ============================================================

if (isset($_GET['payment_cancelled'])) {

    $errors[] =
        "PayHere payment was cancelled or interrupted. " .
        "You can try placing the order again or choose Cash on Delivery.";
}


// ============================================================
// HANDLE ORDER PLACEMENT
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['place_order'])
) {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $postal     = trim($_POST['postal_code'] ?? '');

    $payMethod = trim(
        $_POST['payment_method'] ?? 'Cash on Delivery'
    );

    $delType = trim(
        $_POST['delivery_type'] ?? 'Standard'
    );


    // ========================================================
    // VALIDATE SHIPPING DETAILS
    // ========================================================

    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($phone) ||
        empty($address) ||
        empty($city)
    ) {

        $errors[] =
            "Please fill in all required shipping details.";
    }


    // ========================================================
    // STOCK VALIDATION
    // ========================================================

    foreach ($cartItems as $item) {

        if ($item['quantity'] > $item['stock_quantity']) {

            $errors[] =
                "Item '{$item['name']}' has only " .
                "{$item['stock_quantity']} units available in stock. " .
                "Please adjust your cart quantity.";
        }
    }


    // ========================================================
    // PROCESS ORDER
    // ========================================================

    if (empty($errors)) {

        try {

            $db->beginTransaction();


            // ====================================================
            // GENERATE ORDER NUMBER
            // ====================================================

            $orderNumber = generate_order_number();


            // ====================================================
            // CALCULATE DELIVERY FEE
            // ====================================================

            $delFee = ($delType === 'Express')
                ? 550.00
                : (($subtotal >= 5000) ? 0.00 : 350.00);


            $totalAmount = $subtotal + $delFee;


            // ====================================================
            // INSERT ORDER
            // ====================================================

            $insOrder = $db->prepare("
                INSERT INTO orders (
                    order_number,
                    user_id,
                    total_amount,
                    discount_amount,
                    delivery_fee,
                    grand_total,
                    payment_method,
                    payment_status,
                    order_status,
                    shipping_first_name,
                    shipping_last_name,
                    shipping_email,
                    shipping_phone,
                    shipping_address,
                    shipping_city,
                    shipping_postal_code,
                    delivery_type
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    0.00,
                    ?,
                    ?,
                    ?,
                    'Pending',
                    'Pending',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            $insOrder->execute([
                $orderNumber,
                $user_id,
                $subtotal,
                $delFee,
                $totalAmount,
                $payMethod,
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $city,
                $postal,
                $delType
            ]);


            $order_id = $db->lastInsertId();


            // ====================================================
            // INSERT ORDER ITEMS
            // ====================================================

            $insItem = $db->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    product_name,
                    price,
                    quantity,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");


            foreach ($cartItems as $item) {

                $itemPrice = (
                    !empty($item['discount_price']) &&
                    $item['discount_price'] < $item['price']
                )
                    ? $item['discount_price']
                    : $item['price'];


                $itemSubtotal =
                    $itemPrice * $item['quantity'];


                $insItem->execute([
                    $order_id,
                    $item['product_id'],
                    $item['name'],
                    $itemPrice,
                    $item['quantity'],
                    $itemSubtotal
                ]);
            }


            // ====================================================
            // INSERT PAYMENT RECORD
            // ====================================================

            $txnId =
                'TXN-' .
                strtoupper(
                    substr(md5(uniqid()), 0, 10)
                );


            $payStatus = 'Pending';


            $insPayment = $db->prepare("
                INSERT INTO payments
                (
                    order_id,
                    payment_method,
                    transaction_id,
                    amount,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ");


            $insPayment->execute([
                $order_id,
                $payMethod,
                $txnId,
                $totalAmount,
                $payStatus
            ]);


            // ====================================================
            // INSERT DELIVERY RECORD
            // ====================================================

            $trackingNum =
                'TRK-' .
                strtoupper(
                    substr(uniqid(), -8)
                );


            $estDelivery =
                ($delType === 'Express')
                    ? 'Today within 2 Hours'
                    : '1-2 Days';


            $insDelivery = $db->prepare("
                INSERT INTO deliveries
                (
                    order_id,
                    delivery_type,
                    tracking_number,
                    status,
                    estimated_delivery
                )
                VALUES (?, ?, ?, 'Pending', ?)
            ");


            $insDelivery->execute([
                $order_id,
                $delType,
                $trackingNum,
                $estDelivery
            ]);


            // ====================================================
            // COD ONLY - DECREASE STOCK
            //
            // IMPORTANT:
            // Card payment stock is NOT decreased here.
            // Card stock will be decreased by payhere-notify.php
            // only after successful payment.
            // ====================================================

            if ($payMethod === 'Cash on Delivery') {

                $decStock = $db->prepare("
                    UPDATE products
                    SET stock_quantity = stock_quantity - ?
                    WHERE id = ?
                    AND stock_quantity >= ?
                ");


                foreach ($cartItems as $item) {

                    $decStock->execute([
                        $item['quantity'],
                        $item['product_id'],
                        $item['quantity']
                    ]);


                    if ($decStock->rowCount() !== 1) {

                        throw new Exception(
                            "Insufficient stock for product: " .
                            $item['name']
                        );
                    }
                }
            }


            // ====================================================
            // CLEAR CART
            // ====================================================

            $clearCart = $db->prepare("
                DELETE FROM cart_items
                WHERE cart_id = ?
            ");

            $clearCart->execute([$cart_id]);


            // ====================================================
            // COMMIT TRANSACTION
            // ====================================================

            $db->commit();


            // ====================================================
            // CARD PAYMENT
            // ====================================================

            if (
                $payMethod === 'Card Payment' ||
                strpos(
                    strtolower($payMethod),
                    'card'
                ) !== false
            ) {

                // --------------------------------------------
                // PAYHERE SANDBOX PAYLOAD
                // --------------------------------------------

                $amountFormatted =
                    number_format(
                        $totalAmount,
                        2,
                        '.',
                        ''
                    );


                $hash = generate_payhere_hash(
                    $orderNumber,
                    $amountFormatted,
                    PAYHERE_CURRENCY
                );


                $site_url = get_site_base_url();


                $return_url =
                    $site_url .
                    "/order-confirmation.php?id=" .
                    $order_id .
                    "&payhere_success=1";


                $cancel_url =
                    $site_url .
                    "/checkout.php?payment_cancelled=1";


                $notify_url =
                    $site_url .
                    "/payhere-notify.php";

                ?>

                <!DOCTYPE html>

                <html lang="en">

                <head>

                    <meta charset="UTF-8">

                    <meta
                        name="viewport"
                        content="width=device-width, initial-scale=1.0"
                    >

                    <title>
                        Connecting to PayHere Sandbox...
                    </title>

                    <link
                        rel="stylesheet"
                        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
                    >

                    <style>

                        body {
                            font-family:
                                'Inter',
                                -apple-system,
                                BlinkMacSystemFont,
                                sans-serif;

                            display: flex;
                            align-items: center;
                            justify-content: center;

                            min-height: 100vh;

                            margin: 0;

                            background-color: #f4f6f8;

                            text-align: center;
                        }

                        .pay-card {
                            background: #ffffff;

                            padding: 40px;

                            border-radius: 16px;

                            box-shadow:
                                0 10px 30px
                                rgba(0,0,0,0.08);

                            max-width: 480px;

                            width: 90%;

                            border: 1px solid #e5e7eb;
                        }

                        .spinner {
                            border: 4px solid #f3f4f6;

                            border-top: 4px solid #16a34a;

                            border-radius: 50%;

                            width: 56px;

                            height: 56px;

                            animation:
                                spin 0.9s linear infinite;

                            margin: 24px auto;
                        }

                        @keyframes spin {

                            0% {
                                transform: rotate(0deg);
                            }

                            100% {
                                transform: rotate(360deg);
                            }
                        }

                        h2 {
                            color: #1f2937;

                            font-size: 22px;

                            font-weight: 800;

                            margin-bottom: 8px;
                        }

                        p {
                            color: #6b7280;

                            font-size: 14px;

                            margin-bottom: 20px;

                            line-height: 1.5;
                        }

                        .order-badge {
                            background: #f0fdf4;

                            color: #166534;

                            font-weight: 700;

                            padding: 8px 16px;

                            border-radius: 20px;

                            display: inline-block;

                            font-size: 14px;

                            margin-bottom: 16px;

                            border: 1px solid #bbf7d0;
                        }

                        .btn-submit {
                            background: #16a34a;

                            color: white;

                            border: none;

                            padding: 12px 28px;

                            font-size: 15px;

                            font-weight: 700;

                            border-radius: 8px;

                            cursor: pointer;

                            transition: all 0.2s ease;

                            width: 100%;
                        }

                        .btn-submit:hover {
                            background: #15803d;
                        }

                    </style>

                </head>

                <body>

                    <div class="pay-card">

                        <div class="order-badge">

                            <i class="fas fa-shopping-bag"></i>

                            Order:
                            <?php
                            echo sanitize($orderNumber);
                            ?>

                        </div>


                        <h2>
                            Redirecting to PayHere Sandbox
                        </h2>


                        <p>
                            Please wait a moment while we
                            transfer you to PayHere Sandbox
                            payment gateway to complete
                            your test card payment.
                        </p>


                        <div class="spinner"></div>


                        <form
                            id="payhere_checkout_form"
                            action="<?php echo PAYHERE_URL; ?>"
                            method="post"
                        >

                            <input
                                type="hidden"
                                name="merchant_id"
                                value="<?php
                                echo sanitize(
                                    PAYHERE_MERCHANT_ID
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="return_url"
                                value="<?php
                                echo sanitize(
                                    $return_url
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="cancel_url"
                                value="<?php
                                echo sanitize(
                                    $cancel_url
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="notify_url"
                                value="<?php
                                echo sanitize(
                                    $notify_url
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="order_id"
                                value="<?php
                                echo sanitize(
                                    $orderNumber
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="items"
                                value="<?php
                                echo sanitize(
                                    'InCart Order #' .
                                    $orderNumber
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="currency"
                                value="<?php
                                echo sanitize(
                                    PAYHERE_CURRENCY
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="amount"
                                value="<?php
                                echo sanitize(
                                    $amountFormatted
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="first_name"
                                value="<?php
                                echo sanitize(
                                    $first_name
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="last_name"
                                value="<?php
                                echo sanitize(
                                    $last_name
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="email"
                                value="<?php
                                echo sanitize(
                                    $email
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="phone"
                                value="<?php
                                echo sanitize(
                                    $phone
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="address"
                                value="<?php
                                echo sanitize(
                                    $address
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="city"
                                value="<?php
                                echo sanitize(
                                    $city
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="country"
                                value="Sri Lanka"
                            >


                            <input
                                type="hidden"
                                name="hash"
                                value="<?php
                                echo sanitize(
                                    $hash
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="custom_1"
                                value="<?php
                                echo sanitize(
                                    $order_id
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="custom_2"
                                value="<?php
                                echo sanitize(
                                    $orderNumber
                                );
                                ?>"
                            >


                            <button
                                type="submit"
                                class="btn-submit"
                            >

                                <i class="fas fa-lock"></i>

                                Click here if not redirected
                                automatically

                            </button>

                        </form>

                    </div>


                    <script>

                        setTimeout(function () {

                            document
                                .getElementById(
                                    'payhere_checkout_form'
                                )
                                .submit();

                        }, 500);

                    </script>

                </body>

                </html>

                <?php

                exit;
            }


            // ====================================================
            // COD SUCCESS
            // ====================================================

            set_flash_success(
                "Your order {$orderNumber} has been placed successfully!"
            );


            header(
                "Location: order-confirmation.php?id=" .
                $order_id
            );

            exit;


        } catch (Exception $e) {

            if ($db->inTransaction()) {

                $db->rollBack();
            }


            $errors[] =
                "Order process failed: " .
                $e->getMessage();
        }
    }
}

?>

<div
    class="container"
    style="margin:40px auto;"
>

    <h1
        style="
            font-size:28px;
            font-weight:800;
            color:var(--dark);
            margin-bottom:24px;
        "
    >
        Order Checkout
    </h1>


    <?php if (!empty($errors)): ?>

        <div
            class="alert alert-danger"
            style="margin-bottom:24px;"
        >

            <ul
                style="
                    margin-left:20px;
                    list-style-type:disc;
                "
            >

                <?php foreach ($errors as $err): ?>

                    <li>
                        <?php echo sanitize($err); ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        action="checkout.php"
        method="POST"
    >

        <div
            style="
                display:grid;
                grid-template-columns:2fr 1fr;
                gap:35px;
            "
        >

            <!-- LEFT -->

            <div>

                <!-- DELIVERY INFORMATION -->

                <div
                    style="
                        background:var(--white);
                        padding:30px;
                        border-radius:var(--radius-lg);
                        box-shadow:var(--shadow-sm);
                        border:1px solid var(--gray-200);
                        margin-bottom:25px;
                    "
                >

                    <h3
                        style="
                            font-size:20px;
                            font-weight:800;
                            margin-bottom:20px;
                            color:var(--dark);
                        "
                    >

                        <i
                            class="fas fa-truck"
                            style="color:var(--primary);"
                        ></i>

                        Delivery Information

                    </h3>


                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr;
                            gap:15px;
                            margin-bottom:15px;
                        "
                    >

                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                First Name *
                            </label>


                            <input
                                type="text"
                                name="first_name"
                                required
                                value="<?php
                                echo sanitize(
                                    $userInfo['first_name']
                                );
                                ?>"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>


                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                Last Name *
                            </label>


                            <input
                                type="text"
                                name="last_name"
                                required
                                value="<?php
                                echo sanitize(
                                    $userInfo['last_name']
                                );
                                ?>"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>

                    </div>


                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr;
                            gap:15px;
                            margin-bottom:15px;
                        "
                    >

                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                Email Address *
                            </label>


                            <input
                                type="email"
                                name="email"
                                required
                                value="<?php
                                echo sanitize(
                                    $userInfo['email']
                                );
                                ?>"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>


                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                Phone Number *
                            </label>


                            <input
                                type="tel"
                                name="phone"
                                required
                                value="<?php
                                echo sanitize(
                                    $userInfo['phone']
                                );
                                ?>"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>

                    </div>


                    <div style="margin-bottom:15px;">

                        <label
                            style="
                                display:block;
                                font-weight:600;
                                font-size:14px;
                                margin-bottom:6px;
                            "
                        >
                            Street Address *
                        </label>


                        <input
                            type="text"
                            name="address"
                            required
                            placeholder="House No, Street Name"
                            value="<?php
                            echo sanitize(
                                $userInfo['address']
                            );
                            ?>"
                            style="
                                width:100%;
                                padding:10px 14px;
                                border-radius:6px;
                                border:1px solid var(--gray-300);
                                outline:none;
                            "
                        >

                    </div>


                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr;
                            gap:15px;
                        "
                    >

                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                City *
                            </label>


                            <input
                                type="text"
                                name="city"
                                required
                                value="<?php
                                echo sanitize(
                                    $userInfo['city']
                                    ?: 'Colombo'
                                );
                                ?>"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>


                        <div>

                            <label
                                style="
                                    display:block;
                                    font-weight:600;
                                    font-size:14px;
                                    margin-bottom:6px;
                                "
                            >
                                Postal Code
                            </label>


                            <input
                                type="text"
                                name="postal_code"
                                placeholder="00300"
                                style="
                                    width:100%;
                                    padding:10px 14px;
                                    border-radius:6px;
                                    border:1px solid var(--gray-300);
                                    outline:none;
                                "
                            >

                        </div>

                    </div>

                </div>


                <!-- DELIVERY TYPE -->

                <div
                    style="
                        background:var(--white);
                        padding:30px;
                        border-radius:var(--radius-lg);
                        box-shadow:var(--shadow-sm);
                        border:1px solid var(--gray-200);
                        margin-bottom:25px;
                    "
                >

                    <h3
                        style="
                            font-size:20px;
                            font-weight:800;
                            margin-bottom:15px;
                            color:var(--dark);
                        "
                    >

                        <i
                            class="fas fa-shipping-fast"
                            style="color:var(--accent);"
                        ></i>

                        Select Delivery Speed

                    </h3>


                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr 1fr;
                            gap:15px;
                        "
                    >

                        <label
                            style="
                                border:2px solid var(--primary);
                                border-radius:var(--radius-md);
                                padding:16px;
                                display:block;
                                cursor:pointer;
                                background:var(--primary-light);
                            "
                        >

                            <input
                                type="radio"
                                name="delivery_type"
                                value="Standard"
                                checked
                                style="margin-right:8px;"
                            >

                            <strong>
                                Standard Delivery
                            </strong>


                            <span
                                style="
                                    display:block;
                                    font-size:12px;
                                    color:var(--gray-600);
                                    margin-top:4px;
                                "
                            >

                                1-2 Days |

                                <?php
                                echo ($subtotal >= 5000)
                                    ? 'FREE'
                                    : 'LKR 350.00';
                                ?>

                            </span>

                        </label>


                        <label
                            style="
                                border:2px solid var(--gray-300);
                                border-radius:var(--radius-md);
                                padding:16px;
                                display:block;
                                cursor:pointer;
                            "
                        >

                            <input
                                type="radio"
                                name="delivery_type"
                                value="Express"
                                style="margin-right:8px;"
                            >

                            <strong>
                                Express Delivery
                            </strong>


                            <span
                                style="
                                    display:block;
                                    font-size:12px;
                                    color:var(--gray-600);
                                    margin-top:4px;
                                "
                            >
                                Within 2 Hours |
                                LKR 550.00
                            </span>

                        </label>

                    </div>

                </div>


                <!-- PAYMENT METHOD -->

                <div
                    style="
                        background:var(--white);
                        padding:30px;
                        border-radius:var(--radius-lg);
                        box-shadow:var(--shadow-sm);
                        border:1px solid var(--gray-200);
                    "
                >

                    <h3
                        style="
                            font-size:20px;
                            font-weight:800;
                            margin-bottom:15px;
                            color:var(--dark);
                        "
                    >

                        <i
                            class="fas fa-credit-card"
                            style="color:var(--primary);"
                        ></i>

                        Payment Method

                    </h3>


                    <div
                        style="
                            display:flex;
                            flex-direction:column;
                            gap:12px;
                            margin-bottom:20px;
                        "
                    >

                        <label
                            style="
                                border:1px solid var(--gray-300);
                                border-radius:var(--radius-md);
                                padding:14px;
                                display:flex;
                                align-items:center;
                                gap:12px;
                                cursor:pointer;
                            "
                        >

                            <input
                                type="radio"
                                name="payment_method"
                                value="Cash on Delivery"
                                checked
                                onclick="
                                    document.getElementById(
                                        'cardDemoBox'
                                    ).style.display='none';
                                "
                            >

                            <i
                                class="fas fa-money-bill-wave"
                                style="
                                    font-size:20px;
                                    color:var(--primary);
                                "
                            ></i>


                            <div>

                                <strong>
                                    Cash on Delivery (COD)
                                </strong>


                                <span
                                    style="
                                        display:block;
                                        font-size:12px;
                                        color:var(--gray-600);
                                    "
                                >
                                    Pay cash upon physical
                                    delivery at your door.
                                </span>

                            </div>

                        </label>


                        <label
                            style="
                                border:1px solid var(--gray-300);
                                border-radius:var(--radius-md);
                                padding:14px;
                                display:flex;
                                align-items:center;
                                gap:12px;
                                cursor:pointer;
                            "
                        >

                            <input
                                type="radio"
                                name="payment_method"
                                value="Card Payment"
                                onclick="
                                    document.getElementById(
                                        'cardDemoBox'
                                    ).style.display='block';
                                "
                            >


                            <i
                                class="fas fa-credit-card"
                                style="
                                    font-size:20px;
                                    color:var(--accent);
                                "
                            ></i>


                            <div>

                                <strong>
                                    Credit / Debit Card
                                    (PayHere Sandbox)
                                </strong>


                                <span
                                    style="
                                        display:block;
                                        font-size:12px;
                                        color:var(--gray-600);
                                    "
                                >
                                    Pay securely online using
                                    Visa, MasterCard or AMEX
                                    via PayHere.
                                </span>

                            </div>

                        </label>

                    </div>


                    <div
                        id="cardDemoBox"
                        style="
                            display:none;
                            background:var(--primary-light);
                            padding:20px;
                            border-radius:var(--radius-md);
                            border:1px solid var(--primary);
                        "
                    >

                        <p
                            style="
                                font-size:14px;
                                font-weight:700;
                                color:var(--primary-hover);
                                margin-bottom:6px;
                            "
                        >

                            <i class="fas fa-shield-alt"></i>

                            PayHere Sandbox Payment

                        </p>


                        <p
                            style="
                                font-size:13px;
                                color:var(--dark);
                                margin:0;
                            "
                        >

                            When you click
                            <strong>
                                PLACE ORDER NOW
                            </strong>,

                            you will be securely redirected
                            to PayHere Sandbox gateway to
                            complete your test card payment.

                        </p>

                    </div>

                </div>

            </div>


            <!-- RIGHT: ORDER SUMMARY -->

            <div>

                <div
                    style="
                        background:var(--white);
                        border-radius:var(--radius-lg);
                        padding:28px;
                        box-shadow:var(--shadow-sm);
                        border:1px solid var(--gray-200);
                        position:sticky;
                        top:100px;
                    "
                >

                    <h3
                        style="
                            font-size:20px;
                            font-weight:800;
                            margin-bottom:20px;
                            color:var(--dark);
                            border-bottom:2px solid var(--gray-100);
                            padding-bottom:10px;
                        "
                    >
                        Your Cart Items
                    </h3>


                    <div
                        style="
                            max-height:260px;
                            overflow-y:auto;
                            margin-bottom:20px;
                            padding-right:5px;
                        "
                    >

                        <?php foreach ($cartItems as $item):

                            $price = (
                                !empty($item['discount_price']) &&
                                $item['discount_price'] < $item['price']
                            )
                                ? $item['discount_price']
                                : $item['price'];

                        ?>

                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    margin-bottom:12px;
                                    font-size:14px;
                                "
                            >

                                <div>

                                    <strong
                                        style="color:var(--dark);"
                                    >
                                        <?php
                                        echo sanitize(
                                            $item['name']
                                        );
                                        ?>
                                    </strong>


                                    <span
                                        style="
                                            display:block;
                                            font-size:12px;
                                            color:var(--gray-600);
                                        "
                                    >

                                        <?php
                                        echo $item['quantity'];
                                        ?>

                                        x

                                        <?php
                                        echo format_price($price);
                                        ?>

                                    </span>

                                </div>


                                <span
                                    style="
                                        font-weight:700;
                                        color:var(--primary);
                                    "
                                >

                                    <?php
                                    echo format_price(
                                        $price *
                                        $item['quantity']
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <div
                        style="
                            border-top:1px solid var(--gray-200);
                            padding-top:14px;
                            margin-bottom:10px;
                            display:flex;
                            justify-content:space-between;
                            font-size:14px;
                        "
                    >

                        <span>
                            Subtotal
                        </span>


                        <span style="font-weight:700;">

                            <?php
                            echo format_price($subtotal);
                            ?>

                        </span>

                    </div>


                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            margin-bottom:14px;
                            font-size:14px;
                        "
                    >

                        <span>
                            Delivery Fee
                        </span>


                        <span style="font-weight:700;">

                            <?php
                            echo format_price($deliveryFee);
                            ?>

                        </span>

                    </div>


                    <div
                        style="
                            border-top:2px solid var(--gray-200);
                            padding-top:16px;
                            display:flex;
                            justify-content:space-between;
                            align-items:baseline;
                            margin-bottom:24px;
                        "
                    >

                        <span
                            style="
                                font-size:18px;
                                font-weight:800;
                                color:var(--dark);
                            "
                        >
                            Total Amount
                        </span>


                        <span
                            style="
                                font-size:24px;
                                font-weight:800;
                                color:var(--primary);
                            "
                        >

                            <?php
                            echo format_price($grandTotal);
                            ?>

                        </span>

                    </div>


                    <button
                        type="submit"
                        name="place_order"
                        class="btn btn-accent"
                        style="
                            width:100%;
                            padding:14px;
                            font-size:16px;
                        "
                    >

                        <i class="fas fa-check-circle"></i>

                        PLACE ORDER NOW

                    </button>

                </div>

            </div>

        </div>

    </form>

</div>


<?php

require_once __DIR__ . '/includes/footer.php';

?>