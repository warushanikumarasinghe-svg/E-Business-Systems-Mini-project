<?php
$adminTitle = "Order Management";
$adminPage = "orders";

require_once __DIR__ . '/includes/header.php';

/*
|--------------------------------------------------------------------------
| Admin Task B - View Orders & Update Status
|--------------------------------------------------------------------------
| Order Status Workflow:
| Pending -> Paid -> Processing -> Shipped -> Completed
|
| Payment Status is kept separately as:
| Pending / Paid / Failed
|--------------------------------------------------------------------------
*/

// Allowed order statuses for Admin Task B
$allowedOrderStatuses = [
    'Pending',
    'Paid',
    'Processing',
    'Shipped',
    'Completed'
];

// Allowed payment statuses
$allowedPaymentStatuses = [
    'Pending',
    'Paid',
    'Failed'
];

/*
|--------------------------------------------------------------------------
| Handle Order Status Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {

    $order_id = (int)($_POST['order_id'] ?? 0);
    $order_status = $_POST['order_status'] ?? 'Pending';
    $payment_status = $_POST['payment_status'] ?? 'Pending';

    // Validate submitted values
    if (
        $order_id <= 0 ||
        !in_array($order_status, $allowedOrderStatuses, true) ||
        !in_array($payment_status, $allowedPaymentStatuses, true)
    ) {
        set_flash_error("Invalid order status information.");
        header('Location: orders.php');
        exit;
    }

    try {
        $db->beginTransaction();

        // Make sure the order exists
        $checkOrder = $db->prepare("SELECT id FROM orders WHERE id = ?");
        $checkOrder->execute([$order_id]);

        if (!$checkOrder->fetch()) {
            throw new Exception("Order not found.");
        }

        // Update order status and payment status in database
        $updateOrder = $db->prepare("
            UPDATE orders
            SET order_status = ?, payment_status = ?
            WHERE id = ?
        ");

        $updateOrder->execute([
            $order_status,
            $payment_status,
            $order_id
        ]);

        // Keep delivery status synchronized with order status
        $updateDelivery = $db->prepare("
            UPDATE deliveries
            SET status = ?
            WHERE order_id = ?
        ");

        $updateDelivery->execute([
            $order_status,
            $order_id
        ]);

        $db->commit();

        set_flash_success(
            "Order #{$order_id} updated successfully. Status: {$order_status}."
        );

    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        set_flash_error(
            "Order update failed: " . $e->getMessage()
        );
    }

    header('Location: orders.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Search Orders
|--------------------------------------------------------------------------
| Search by:
| - Order number
| - Customer first name
| - Customer last name
| - Customer email
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $searchTerm = '%' . $search . '%';

    $stmtOrders = $db->prepare("
        SELECT
            o.*,
            u.first_name AS user_fname,
            u.last_name AS user_lname,
            u.email AS user_email,
            d.tracking_number
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE
            o.order_number LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        ORDER BY o.created_at DESC
    ");

    $stmtOrders->execute([
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);

    $orders = $stmtOrders->fetchAll();

} else {

    $orders = $db->query("
        SELECT
            o.*,
            u.first_name AS user_fname,
            u.last_name AS user_lname,
            u.email AS user_email,
            d.tracking_number
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        ORDER BY o.created_at DESC
    ")->fetchAll();
}

/*
|--------------------------------------------------------------------------
| View Selected Order
|--------------------------------------------------------------------------
*/

$selectedOrder = null;
$selectedItems = [];

if (isset($_GET['view'])) {

    $viewId = (int)$_GET['view'];

    $stmt = $db->prepare("
        SELECT
            o.*,
            u.first_name AS user_fname,
            u.last_name AS user_lname,
            u.email AS user_email,
            d.tracking_number
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.id = ?
    ");

    $stmt->execute([$viewId]);
    $selectedOrder = $stmt->fetch();

    if ($selectedOrder) {

        $stmtItems = $db->prepare("
            SELECT *
            FROM order_items
            WHERE order_id = ?
        ");

        $stmtItems->execute([$viewId]);
        $selectedItems = $stmtItems->fetchAll();
    }
}
?>

<?php if ($selectedOrder): ?>

<!-- =========================================================
     ORDER DETAILS
========================================================= -->
<div class="admin-card" style="border:2px solid var(--admin-primary);">

    <div class="admin-card-title">

        <span>
            <i class="fas fa-file-invoice"
               style="color:var(--admin-primary);"></i>

            Order Details:
            <strong>
                <?php echo sanitize($selectedOrder['order_number']); ?>
            </strong>
        </span>

        <a
            href="orders.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>"
            class="btn btn-outline"
            style="font-size:12px; color:var(--dark); border-color:#ccc;"
        >
            Close Details
        </a>

    </div>

    <!-- CUSTOMER / SHIPPING / PAYMENT -->
    <div style="
        display:grid;
        grid-template-columns:1fr 1fr 1fr;
        gap:20px;
        font-size:14px;
        margin-bottom:20px;
    ">

        <div>

            <h4 style="font-weight:700; margin-bottom:6px;">
                Customer Info
            </h4>

            <p>
                Name:
                <strong>
                    <?php
                    echo sanitize(
                        $selectedOrder['shipping_first_name'] . ' ' .
                        $selectedOrder['shipping_last_name']
                    );
                    ?>
                </strong>
            </p>

            <p>
                Email:
                <?php echo sanitize($selectedOrder['shipping_email']); ?>
            </p>

            <p>
                Phone:
                <?php echo sanitize($selectedOrder['shipping_phone']); ?>
            </p>

        </div>

        <div>

            <h4 style="font-weight:700; margin-bottom:6px;">
                Shipping Address
            </h4>

            <p>
                <?php echo sanitize($selectedOrder['shipping_address']); ?>
            </p>

            <p>
                <?php echo sanitize($selectedOrder['shipping_city']); ?>

                <?php
                echo sanitize(
                    $selectedOrder['shipping_postal_code'] ?? ''
                );
                ?>
            </p>

            <p>
                Type:
                <strong>
                    <?php echo sanitize($selectedOrder['delivery_type']); ?>
                    Delivery
                </strong>
            </p>

        </div>

        <div>

            <h4 style="font-weight:700; margin-bottom:6px;">
                Payment & Summary
            </h4>

            <p>
                Method:
                <strong>
                    <?php echo sanitize($selectedOrder['payment_method']); ?>
                </strong>
            </p>

            <p>
                Payment Status:
                <strong>
                    <?php echo sanitize($selectedOrder['payment_status']); ?>
                </strong>
            </p>

            <p>
                Order Status:
                <strong>
                    <?php echo sanitize($selectedOrder['order_status']); ?>
                </strong>
            </p>

            <p>
                Grand Total:
                <strong
                    style="
                        color:var(--admin-primary);
                        font-size:16px;
                    "
                >
                    <?php echo format_price($selectedOrder['grand_total']); ?>
                </strong>
            </p>

        </div>

    </div>

    <!-- ORDER ITEMS -->
    <h4 style="font-weight:700; margin-bottom:10px;">
        Ordered Grocery Items
    </h4>

    <div class="table-responsive">

        <table
            class="data-table"
            style="margin-bottom:20px;"
        >

            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!empty($selectedItems)): ?>

                <?php foreach ($selectedItems as $it): ?>

                    <tr>

                        <td>
                            <strong>
                                <?php
                                echo sanitize(
                                    $it['product_name'] ?? ''
                                );
                                ?>
                            </strong>
                        </td>

                        <td>
                            <?php
                            echo format_price(
                                $it['price'] ?? 0
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo (int)($it['quantity'] ?? 0);
                            ?>
                        </td>

                        <td>
                            <strong>
                                <?php
                                echo format_price(
                                    $it['subtotal'] ?? 0
                                );
                                ?>
                            </strong>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4" style="text-align:center;">
                        No order items found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- =====================================================
         UPDATE STATUS FORM
    ====================================================== -->
    <div
        style="
            background:#f8f9fa;
            padding:20px;
            border-radius:8px;
            margin-top:10px;
        "
    >

        <h4 style="margin-bottom:15px;">
            <i class="fas fa-sync-alt"></i>
            Update Order Status
        </h4>

        <form
            action="orders.php"
            method="POST"
            style="
                display:flex;
                gap:20px;
                align-items:end;
                flex-wrap:wrap;
            "
        >

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int)$selectedOrder['id']; ?>"
            >

            <!-- ORDER STATUS -->
            <div style="flex:1; min-width:200px;">

                <label
                    style="
                        display:block;
                        font-weight:600;
                        font-size:13px;
                        margin-bottom:4px;
                    "
                >
                    Order Status
                </label>

                <select
                    name="order_status"
                    class="form-control"
                    required
                >

                    <?php foreach ($allowedOrderStatuses as $st): ?>

                        <option
                            value="<?php echo sanitize($st); ?>"
                            <?php
                            echo (
                                $selectedOrder['order_status'] === $st
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >
                            <?php echo sanitize($st); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- PAYMENT STATUS -->
            <div style="flex:1; min-width:200px;">

                <label
                    style="
                        display:block;
                        font-weight:600;
                        font-size:13px;
                        margin-bottom:4px;
                    "
                >
                    Payment Status
                </label>

                <select
                    name="payment_status"
                    class="form-control"
                    required
                >

                    <?php foreach ($allowedPaymentStatuses as $ps): ?>

                        <option
                            value="<?php echo sanitize($ps); ?>"
                            <?php
                            echo (
                                $selectedOrder['payment_status'] === $ps
                            )
                                ? 'selected'
                                : '';
                            ?>
                        >
                            <?php echo sanitize($ps); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button
                type="submit"
                name="update_order_status"
                class="btn btn-primary"
                style="padding:10px 20px;"
            >
                <i class="fas fa-save"></i>
                UPDATE STATUS
            </button>

        </form>

        <!-- WORKFLOW DISPLAY -->
        <div style="
            margin-top:20px;
            padding-top:15px;
            border-top:1px solid #ddd;
        ">

            <strong>
                Status Workflow:
            </strong>

            <span style="margin-left:10px;">
                Pending
                <i class="fas fa-arrow-right"></i>
                Paid
                <i class="fas fa-arrow-right"></i>
                Processing
                <i class="fas fa-arrow-right"></i>
                Shipped
                <i class="fas fa-arrow-right"></i>
                Completed
            </span>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- =========================================================
     SEARCH ORDERS
========================================================= -->
<div class="admin-card">

    <div class="admin-card-title">

        <span>
            <i class="fas fa-search"></i>
            Search Orders
        </span>

    </div>

    <form
        action="orders.php"
        method="GET"
        style="
            display:flex;
            gap:10px;
            align-items:center;
        "
    >

        <input
            type="text"
            name="search"
            class="form-control"
            placeholder="Search by Order ID, customer name or email..."
            value="<?php echo sanitize($search); ?>"
            style="flex:1;"
        >

        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="fas fa-search"></i>
            SEARCH
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="orders.php"
                class="btn btn-outline"
                style="color:var(--dark); border-color:#ccc;"
            >
                CLEAR
            </a>

        <?php endif; ?>

    </form>

</div>


<!-- =========================================================
     ORDERS LIST TABLE
========================================================= -->
<div class="admin-card">

    <div class="admin-card-title">

        <span>
            <i class="fas fa-shopping-cart"></i>

            <?php if ($search !== ''): ?>

                Search Results
                (<?php echo count($orders); ?>)

            <?php else: ?>

                All Store Orders
                (<?php echo count($orders); ?>)

            <?php endif; ?>

        </span>

    </div>

    <div class="table-responsive">

        <table class="data-table">

            <thead>

                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if (!empty($orders)): ?>

                <?php foreach ($orders as $ord): ?>

                    <?php
                    $orderStatus = $ord['order_status'] ?? 'Pending';

                    $statusClass = 'status-pending';

                    if ($orderStatus === 'Paid') {
                        $statusClass = 'status-delivered';
                    } elseif ($orderStatus === 'Processing') {
                        $statusClass = 'status-processing';
                    } elseif ($orderStatus === 'Shipped') {
                        $statusClass = 'status-delivered';
                    } elseif ($orderStatus === 'Completed') {
                        $statusClass = 'status-delivered';
                    }
                    ?>

                    <tr>

                        <!-- ORDER ID -->
                        <td>
                            <strong>
                                #<?php
                                echo sanitize(
                                    $ord['order_number']
                                );
                                ?>
                            </strong>

                            <br>

                            <span
                                style="
                                    font-size:11px;
                                    color:#777;
                                "
                            >
                                ID:
                                <?php
                                echo (int)$ord['id'];
                                ?>
                            </span>
                        </td>

                        <!-- CUSTOMER -->
                        <td>

                            <?php
                            echo sanitize(
                                $ord['shipping_first_name'] . ' ' .
                                $ord['shipping_last_name']
                            );
                            ?>

                            <br>

                            <span
                                style="
                                    font-size:11px;
                                    color:#777;
                                "
                            >
                                <?php
                                echo sanitize(
                                    $ord['shipping_email']
                                );
                                ?>
                            </span>

                        </td>

                        <!-- TOTAL -->
                        <td>
                            <strong>
                                <?php
                                echo format_price(
                                    $ord['grand_total']
                                );
                                ?>
                            </strong>
                        </td>

                        <!-- PAYMENT -->
                        <td>

                            <span
                                class="status-badge
                                <?php
                                echo (
                                    ($ord['payment_status'] ?? '') === 'Paid'
                                )
                                    ? 'status-delivered'
                                    : 'status-pending';
                                ?>"
                            >
                                <?php
                                echo sanitize(
                                    $ord['payment_status'] ?? 'Pending'
                                );
                                ?>
                            </span>

                        </td>

                        <!-- ORDER STATUS -->
                        <td>

                            <span
                                class="status-badge <?php echo $statusClass; ?>"
                            >
                                <?php
                                echo sanitize($orderStatus);
                                ?>
                            </span>

                        </td>

                        <!-- DATE -->
                        <td>
                            <?php
                            echo date(
                                'M d, Y',
                                strtotime($ord['created_at'])
                            );
                            ?>
                        </td>

                        <!-- ACTION -->
                        <td>

                            <a
                                href="orders.php?view=<?php echo (int)$ord['id']; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                class="btn btn-primary"
                                style="
                                    padding:4px 10px;
                                    font-size:12px;
                                "
                            >
                                <i class="fas fa-eye"></i>
                                View / Update
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="7"
                        style="
                            text-align:center;
                            padding:30px;
                        "
                    >
                        <i class="fas fa-box-open"></i>

                        <?php if ($search !== ''): ?>

                            No orders found for
                            "<strong><?php echo sanitize($search); ?></strong>".

                        <?php else: ?>

                            No orders found.

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
