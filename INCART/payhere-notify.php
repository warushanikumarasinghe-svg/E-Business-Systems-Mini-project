<?php

// ============================================================
// PayHere Instant Payment Notification (IPN) Listener
// ============================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/payhere.php';


// ============================================================
// ONLY ACCEPT POST REQUEST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo "Method Not Allowed";

    exit;
}


// ============================================================
// GET PAYHERE DATA
// ============================================================

$merchant_id      = $_POST['merchant_id'] ?? '';
$order_id         = $_POST['order_id'] ?? '';
$payment_id       = $_POST['payment_id'] ?? '';
$payhere_amount   = $_POST['payhere_amount'] ?? '';
$payhere_currency = $_POST['payhere_currency'] ?? '';
$status_code      = $_POST['status_code'] ?? '';
$md5sig           = $_POST['md5sig'] ?? '';


// custom_1 contains actual database order ID
$custom_1 = $_POST['custom_1'] ?? '';


// ============================================================
// VERIFY PAYHERE HASH
// ============================================================

if (
    !verify_payhere_hash(
        $merchant_id,
        $order_id,
        $payhere_amount,
        $payhere_currency,
        $status_code,
        $md5sig
    )
) {

    http_response_code(400);

    echo "Invalid Signature Hash";

    exit;
}


// ============================================================
// PAYMENT SUCCESS
// PayHere status_code = 2
// ============================================================

if ($status_code == 2) {

    $db = getDB();


    try {

        // ====================================================
        // START TRANSACTION
        // ====================================================

        $db->beginTransaction();


        // ====================================================
        // FIND DATABASE ORDER
        // ====================================================

        $orderStmt = $db->prepare("
            SELECT
                id,
                payment_status,
                order_status
            FROM orders
            WHERE id = ?
               OR order_number = ?
            LIMIT 1
        ");


        $orderStmt->execute([
            $custom_1,
            $order_id
        ]);


        $order = $orderStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$order) {

            throw new Exception(
                "Order not found."
            );
        }


        $dbOrderId = $order['id'];


        // ====================================================
        // IMPORTANT:
        // PREVENT DUPLICATE STOCK DEDUCTION
        //
        // If already Paid, this notification was already
        // processed. Do not decrease stock again.
        // ====================================================

        if ($order['payment_status'] === 'Paid') {

            $db->commit();

            http_response_code(200);

            echo "OK - Payment already processed";

            exit;
        }


        // ====================================================
        // UPDATE ORDER PAYMENT STATUS
        // ====================================================

        $stmt = $db->prepare("
            UPDATE orders
            SET
                payment_status = 'Paid',
                order_status = 'Processing'
            WHERE id = ?
        ");


        $stmt->execute([
            $dbOrderId
        ]);


        // ====================================================
        // UPDATE PAYMENT RECORD
        // ====================================================

        $payStmt = $db->prepare("
            UPDATE payments
            SET
                status = 'Paid',
                transaction_id = ?
            WHERE order_id = ?
        ");


        $payStmt->execute([
            $payment_id,
            $dbOrderId
        ]);


        // ====================================================
        // GET ORDER ITEMS
        // ====================================================

        $itemStmt = $db->prepare("
            SELECT
                product_id,
                quantity
            FROM order_items
            WHERE order_id = ?
        ");


        $itemStmt->execute([
            $dbOrderId
        ]);


        $orderItems =
            $itemStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        if (empty($orderItems)) {

            throw new Exception(
                "No order items found for this order."
            );
        }


        // ====================================================
        // DECREASE PRODUCT STOCK
        //
        // ONLY HAPPENS AFTER SUCCESSFUL CARD PAYMENT
        // ====================================================

        $stockStmt = $db->prepare("
            UPDATE products
            SET
                stock_quantity =
                    stock_quantity - ?
            WHERE id = ?
              AND stock_quantity >= ?
        ");


        foreach ($orderItems as $item) {

            $quantity =
                (int)$item['quantity'];

            $productId =
                (int)$item['product_id'];


            $stockStmt->execute([
                $quantity,
                $productId,
                $quantity
            ]);


            // ------------------------------------------------
            // If no row was updated, stock is insufficient
            // ------------------------------------------------

            if ($stockStmt->rowCount() !== 1) {

                throw new Exception(
                    "Insufficient stock for product ID: " .
                    $productId
                );
            }
        }


        // ====================================================
        // COMMIT ALL CHANGES
        // ====================================================

        $db->commit();


        // ====================================================
        // SUCCESS RESPONSE TO PAYHERE
        // ====================================================

        http_response_code(200);

        echo "OK";

        exit;


    } catch (Exception $e) {

        // ====================================================
        // ROLLBACK IF SOMETHING FAILED
        // ====================================================

        if ($db->inTransaction()) {

            $db->rollBack();
        }


        http_response_code(500);

        echo
            "Payment processing failed: " .
            $e->getMessage();

        exit;
    }
}


// ============================================================
// PAYMENT FAILED / CANCELLED
// ============================================================

$db = getDB();


try {

    $stmt = $db->prepare("
        UPDATE orders
        SET payment_status = 'Failed'
        WHERE id = ?
           OR order_number = ?
    ");


    $stmt->execute([
        $custom_1,
        $order_id
    ]);


    http_response_code(200);

    echo "Status Not Completed: " . $status_code;

    exit;


} catch (Exception $e) {

    http_response_code(500);

    echo
        "Failed to update order status: " .
        $e->getMessage();

    exit;
}
?>