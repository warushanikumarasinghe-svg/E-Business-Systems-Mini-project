
<?php

$pageTitle = "Shopping Cart - InCart Grocery";
$currentPage = "cart";

require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Get current user's cart
$cart_id = get_cart_id();

if (!$cart_id) {
    set_flash_error("Unable to access your shopping cart.");
    header("Location: shop.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Cart Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Update Cart Quantities
    |--------------------------------------------------------------------------
    */
    if (
        isset($_POST['update_cart']) &&
        isset($_POST['quantities']) &&
        is_array($_POST['quantities'])
    ) {

        foreach ($_POST['quantities'] as $prodId => $qty) {

            $prodId = (int) $prodId;
            $qty = (int) $qty;

            // Minimum quantity is 1
            if ($qty < 1) {
                $qty = 1;
            }

            // Check product and current stock
            $pStmt = $db->prepare("
                SELECT id, name, stock_quantity
                FROM products
                WHERE id = ?
                LIMIT 1
            ");

            $pStmt->execute([$prodId]);
            $product = $pStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                continue;
            }

            // Do not allow quantity greater than available stock
            if ($product['stock_quantity'] < 1) {

                // Product is out of stock
                $deleteStmt = $db->prepare("
                    DELETE FROM cart_items
                    WHERE cart_id = ?
                    AND product_id = ?
                ");

                $deleteStmt->execute([
                    $cart_id,
                    $prodId
                ]);

                continue;
            }

            if ($qty > (int)$product['stock_quantity']) {
                $qty = (int)$product['stock_quantity'];
            }

            // Update cart item
            $updateStmt = $db->prepare("
                UPDATE cart_items
                SET quantity = ?
                WHERE cart_id = ?
                AND product_id = ?
            ");

            $updateStmt->execute([
                $qty,
                $cart_id,
                $prodId
            ]);
        }

        set_flash_success("Shopping cart updated successfully.");

        header("Location: cart.php");
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Item From Cart
    |--------------------------------------------------------------------------
    */
    if (
        isset($_POST['remove_item']) &&
        isset($_POST['product_id'])
    ) {

        $prodId = (int) $_POST['product_id'];

        $deleteStmt = $db->prepare("
            DELETE FROM cart_items
            WHERE cart_id = ?
            AND product_id = ?
        ");

        $deleteStmt->execute([
            $cart_id,
            $prodId
        ]);

        set_flash_success("Item removed from your cart.");

        header("Location: cart.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Get Cart Items
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        ci.id AS cart_item_id,
        ci.cart_id,
        ci.product_id,
        ci.quantity,

        p.name,
        p.price,
        p.discount_price,
        p.stock_quantity,
        p.unit,

        pi.image_path AS primary_image

    FROM cart_items ci

    INNER JOIN products p
        ON ci.product_id = p.id

    LEFT JOIN product_images pi
        ON p.id = pi.product_id
        AND pi.is_primary = 1

    WHERE ci.cart_id = ?

    ORDER BY ci.id DESC
");

$stmt->execute([$cart_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Calculate Cart Totals
|--------------------------------------------------------------------------
*/

$subtotal = 0.00;
$totalDiscount = 0.00;

foreach ($cartItems as $item) {

    $regularPrice = (float) $item['price'];

    $discountPrice = !empty($item['discount_price'])
        ? (float) $item['discount_price']
        : 0.00;

    // Use discount price only if it is lower than regular price
    if (
        $discountPrice > 0 &&
        $discountPrice < $regularPrice
    ) {

        $effectivePrice = $discountPrice;

        $totalDiscount +=
            ($regularPrice - $discountPrice)
            * (int)$item['quantity'];

    } else {

        $effectivePrice = $regularPrice;
    }

    $subtotal +=
        $effectivePrice
        * (int)$item['quantity'];
}


/*
|--------------------------------------------------------------------------
| Delivery Fee
|--------------------------------------------------------------------------
*/

$freeShippingThreshold = 5000.00;
$standardDeliveryFee = 350.00;

if ($subtotal <= 0) {

    $deliveryFee = 0.00;

} elseif ($subtotal >= $freeShippingThreshold) {

    $deliveryFee = 0.00;

} else {

    $deliveryFee = $standardDeliveryFee;
}


/*
|--------------------------------------------------------------------------
| Grand Total
|--------------------------------------------------------------------------
*/

$grandTotal = $subtotal + $deliveryFee;

?>

<div class="container" style="margin: 40px auto;">

    <h1
        style="
            font-size:28px;
            font-weight:800;
            color:var(--dark);
            margin-bottom:24px;
        "
    >
        Shopping Cart
    </h1>


    <?php if (empty($cartItems)): ?>

        <!-- EMPTY CART -->

        <div
            style="
                background:var(--white);
                padding:60px;
                text-align:center;
                border-radius:var(--radius-lg);
                box-shadow:var(--shadow-sm);
            "
        >

            <i
                class="fas fa-shopping-cart"
                style="
                    font-size:64px;
                    color:var(--gray-300);
                    margin-bottom:20px;
                "
            ></i>

            <h2
                style="
                    font-size:24px;
                    font-weight:800;
                    color:var(--dark);
                    margin-bottom:10px;
                "
            >
                Your cart is currently empty
            </h2>

            <p
                style="
                    color:var(--gray-600);
                    margin-bottom:25px;
                "
            >
                Looks like you haven't added any fresh groceries
                to your cart yet.
            </p>

            <a
                href="shop.php"
                class="btn btn-primary"
                style="padding:14px 32px;"
            >
                <i class="fas fa-shopping-bag"></i>
                START SHOPPING
            </a>

        </div>


    <?php else: ?>

        <form
            action="cart.php"
            method="POST"
            id="cartForm"
        >

            <div
                style="
                    display:grid;
                    grid-template-columns:2fr 1fr;
                    gap:30px;
                "
            >

                <!-- CART ITEMS -->

                <div>

                    <table class="cart-table">

                        <thead>

                            <tr>

                                <th>Product</th>

                                <th>Price</th>

                                <th>Quantity</th>

                                <th>Subtotal</th>

                                <th>Remove</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($cartItems as $item): ?>

                                <?php

                                $img = !empty($item['primary_image'])
                                    ? $item['primary_image']
                                    : 'assets/images/products/default.jpg';

                                $regularPrice = (float)$item['price'];

                                $discountPrice = !empty($item['discount_price'])
                                    ? (float)$item['discount_price']
                                    : 0.00;

                                if (
                                    $discountPrice > 0 &&
                                    $discountPrice < $regularPrice
                                ) {

                                    $price = $discountPrice;

                                } else {

                                    $price = $regularPrice;
                                }

                                $quantity = (int)$item['quantity'];

                                $itemSubtotal =
                                    $price * $quantity;

                                ?>

                                <tr>

                                    <!-- PRODUCT -->

                                    <td>

                                        <div
                                            style="
                                                display:flex;
                                                align-items:center;
                                                gap:15px;
                                            "
                                        >

                                            <a
                                                href="product-details.php?id=<?php echo (int)$item['product_id']; ?>"
                                            >

                                                <img
                                                    src="<?php echo sanitize($img); ?>"
                                                    alt="<?php echo sanitize($item['name']); ?>"
                                                    onerror="
                                                        this.onerror=null;
                                                        this.src='assets/images/products/default.jpg';
                                                    "
                                                    style="
                                                        width:60px;
                                                        height:60px;
                                                        object-fit:contain;
                                                        border-radius:6px;
                                                        background:#fafafa;
                                                        padding:4px;
                                                        border:1px solid var(--gray-200);
                                                    "
                                                >

                                            </a>


                                            <div>

                                                <a
                                                    href="product-details.php?id=<?php echo (int)$item['product_id']; ?>"
                                                    style="
                                                        font-weight:700;
                                                        color:var(--dark);
                                                        font-size:15px;
                                                    "
                                                >
                                                    <?php
                                                    echo sanitize(
                                                        $item['name']
                                                    );
                                                    ?>
                                                </a>

                                                <p
                                                    style="
                                                        font-size:12px;
                                                        color:var(--gray-600);
                                                    "
                                                >
                                                    <?php
                                                    echo sanitize(
                                                        $item['unit']
                                                    );
                                                    ?>
                                                </p>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- PRICE -->

                                    <td>

                                        <span
                                            style="
                                                font-weight:700;
                                                font-size:15px;
                                                color:var(--dark);
                                            "
                                        >
                                            <?php
                                            echo format_price($price);
                                            ?>
                                        </span>


                                        <?php
                                        if (
                                            $discountPrice > 0 &&
                                            $discountPrice < $regularPrice
                                        ):
                                        ?>

                                            <br>

                                            <span
                                                style="
                                                    font-size:12px;
                                                    color:var(--gray-600);
                                                    text-decoration:line-through;
                                                "
                                            >
                                                <?php
                                                echo format_price(
                                                    $regularPrice
                                                );
                                                ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- QUANTITY -->

                                    <td>

                                        <div class="qty-selector">

                                            <button
                                                type="button"
                                                class="qty-btn qty-minus"
                                                aria-label="Decrease quantity"
                                            >
                                                -
                                            </button>


                                            <input
                                                type="number"
                                                name="quantities[<?php echo (int)$item['product_id']; ?>]"
                                                class="qty-input"
                                                value="<?php echo $quantity; ?>"
                                                min="1"
                                                max="<?php echo (int)$item['stock_quantity']; ?>"
                                            >


                                            <button
                                                type="button"
                                                class="qty-btn qty-plus"
                                                aria-label="Increase quantity"
                                            >
                                                +
                                            </button>

                                        </div>

                                    </td>


                                    <!-- SUBTOTAL -->

                                    <td>

                                        <span
                                            style="
                                                font-weight:800;
                                                font-size:16px;
                                                color:var(--primary);
                                            "
                                        >
                                            <?php
                                            echo format_price(
                                                $itemSubtotal
                                            );
                                            ?>
                                        </span>

                                    </td>


                                    <!-- REMOVE -->

                                    <td>

                                        <button
                                            type="submit"
                                            name="remove_item"
                                            value="1"
                                            onclick="
                                                document.getElementById('removeProductId').value =
                                                '<?php echo (int)$item['product_id']; ?>';
                                            "
                                            style="
                                                background:none;
                                                border:none;
                                                color:var(--danger);
                                                cursor:pointer;
                                                font-size:18px;
                                            "
                                            title="Remove item"
                                        >

                                            <i
                                                class="fas fa-trash-alt"
                                            ></i>

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>


                    <!-- Hidden product ID for remove -->

                    <input
                        type="hidden"
                        name="product_id"
                        id="removeProductId"
                        value=""
                    >


                    <!-- CART BUTTONS -->

                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            margin-top:20px;
                        "
                    >

                        <a
                            href="shop.php"
                            class="btn btn-outline"
                            style="
                                color:var(--dark);
                                border-color:var(--gray-300);
                            "
                        >
                            <i class="fas fa-arrow-left"></i>
                            CONTINUE SHOPPING
                        </a>


                        <button
                            type="submit"
                            name="update_cart"
                            value="1"
                            class="btn btn-primary"
                        >
                            <i class="fas fa-sync-alt"></i>
                            UPDATE CART
                        </button>

                    </div>

                </div>


                <!-- ORDER SUMMARY -->

                <div>

                    <div
                        style="
                            background:var(--white);
                            border-radius:var(--radius-lg);
                            padding:28px;
                            box-shadow:var(--shadow-sm);
                            border:1px solid var(--gray-200);
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
                            Order Summary
                        </h3>


                        <!-- SUBTOTAL -->

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                margin-bottom:12px;
                                font-size:15px;
                            "
                        >

                            <span>
                                Items Subtotal
                            </span>

                            <span style="font-weight:700;">
                                <?php
                                echo format_price($subtotal);
                                ?>
                            </span>

                        </div>


                        <!-- DISCOUNT -->

                        <?php if ($totalDiscount > 0): ?>

                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    margin-bottom:12px;
                                    font-size:15px;
                                    color:var(--success);
                                "
                            >

                                <span>
                                    Discount Saved
                                </span>

                                <span style="font-weight:700;">

                                    -
                                    <?php
                                    echo format_price(
                                        $totalDiscount
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- DELIVERY -->

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                margin-bottom:12px;
                                font-size:15px;
                            "
                        >

                            <span>
                                Delivery Fee
                            </span>

                            <span>

                                <?php if ($deliveryFee == 0): ?>

                                    <strong
                                        style="color:var(--success);"
                                    >
                                        FREE
                                    </strong>

                                <?php else: ?>

                                    <strong style="font-weight:700;">
                                        <?php
                                        echo format_price(
                                            $deliveryFee
                                        );
                                        ?>
                                    </strong>

                                <?php endif; ?>

                            </span>

                        </div>


                        <!-- FREE DELIVERY MESSAGE -->

                        <?php
                        if (
                            $subtotal > 0 &&
                            $subtotal < $freeShippingThreshold
                        ):
                        ?>

                            <div
                                style="
                                    background:var(--accent-light);
                                    padding:10px 14px;
                                    border-radius:6px;
                                    font-size:12px;
                                    color:var(--accent);
                                    margin-bottom:20px;
                                    display:flex;
                                    align-items:center;
                                    gap:8px;
                                "
                            >

                                <i class="fas fa-truck"></i>

                                <span>

                                    Add

                                    <strong>
                                        <?php
                                        echo format_price(
                                            $freeShippingThreshold
                                            - $subtotal
                                        );
                                        ?>
                                    </strong>

                                    more to get

                                    <strong>
                                        FREE delivery
                                    </strong>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!-- GRAND TOTAL -->

                        <div
                            style="
                                border-top:2px solid var(--gray-200);
                                padding-top:16px;
                                margin-top:16px;
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


                        <!-- CHECKOUT -->

                        <a
                            href="checkout.php"
                            class="btn btn-accent"
                            style="
                                width:100%;
                                padding:14px;
                                font-size:16px;
                                text-align:center;
                            "
                        >

                            PROCEED TO CHECKOUT

                            <i class="fas fa-arrow-right"></i>

                        </a>

                    </div>

                </div>

            </div>

        </form>

    <?php endif; ?>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const quantitySelectors =
        document.querySelectorAll('.qty-selector');

    quantitySelectors.forEach(function (selector) {

        const minusButton =
            selector.querySelector('.qty-minus');

        const plusButton =
            selector.querySelector('.qty-plus');

        const input =
            selector.querySelector('.qty-input');


        /*
        |--------------------------------------------------------------------------
        | Minus Button
        |--------------------------------------------------------------------------
        */

        if (minusButton) {

            minusButton.addEventListener('click', function () {

                let currentValue =
                    parseInt(input.value) || 1;

                if (currentValue > 1) {
                    input.value = currentValue - 1;
                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Plus Button
        |--------------------------------------------------------------------------
        */

        if (plusButton) {

            plusButton.addEventListener('click', function () {

                let currentValue =
                    parseInt(input.value) || 1;

                const maxValue =
                    parseInt(input.max) || 999999;

                if (currentValue < maxValue) {
                    input.value = currentValue + 1;
                } else {
                    alert(
                        'You cannot add more than the available stock.'
                    );
                }

            });

        }


        /*
        |--------------------------------------------------------------------------
        | Manual Input Validation
        |--------------------------------------------------------------------------
        */

        input.addEventListener('change', function () {

            let value =
                parseInt(input.value) || 1;

            const min =
                parseInt(input.min) || 1;

            const max =
                parseInt(input.max) || 999999;

            if (value < min) {
                value = min;
            }

            if (value > max) {
                value = max;

                alert(
                    'Quantity cannot exceed available stock.'
                );
            }

            input.value = value;

        });

    });

});
</script>


<?php

require_once __DIR__ . '/includes/footer.php';

?>
