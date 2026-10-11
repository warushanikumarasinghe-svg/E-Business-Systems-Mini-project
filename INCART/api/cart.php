<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity   = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID.']);
        exit;
    }

    // Fetch product
    $stmt = $db->prepare("SELECT id, name, stock_quantity, is_active FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product || (int)$product['is_active'] !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Product is unavailable or out of stock.']);
        exit;
    }

    $cart_id = get_cart_id(true);
    if (!$cart_id) {
        echo json_encode(['status' => 'error', 'message' => 'Could not initialize shopping cart session.']);
        exit;
    }

    // Check existing item in cart
    $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
    $checkStmt->execute([$cart_id, $product_id]);
    $existing = $checkStmt->fetch();

    $existing_qty = $existing ? (int)$existing['quantity'] : 0;
    $new_qty = $existing_qty + $quantity;

    if ($new_qty > (int)$product['stock_quantity']) {
        $avail = (int)$product['stock_quantity'] - $existing_qty;
        if ($avail <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Maximum stock limit already reached in your cart.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Only {$avail} more unit(s) available in stock."]);
        }
        exit;
    }

    if ($existing) {
        $upd = $db->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?");
        $upd->execute([$quantity, $existing['id']]);
    } else {
        $ins = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
        $ins->execute([$cart_id, $product_id, $quantity]);
    }

    $cart_count = get_cart_count();

    echo json_encode([
        'status' => 'success',
        'message' => htmlspecialchars($product['name']) . ' added to your cart!',
        'cart_count' => $cart_count
    ]);
    exit;

} elseif ($action === 'update') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity   = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $cart_id    = get_cart_id(false);

    if ($cart_id && $product_id > 0) {
        $upd = $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
        $upd->execute([$quantity, $cart_id, $product_id]);
    }

    $cart_count = get_cart_count();
    echo json_encode([
        'status' => 'success',
        'message' => 'Cart updated.',
        'cart_count' => $cart_count
    ]);
    exit;

} elseif ($action === 'remove') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $cart_id    = get_cart_id(false);

    if ($cart_id && $product_id > 0) {
        $del = $db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $del->execute([$cart_id, $product_id]);
    }

    $cart_count = get_cart_count();
    echo json_encode([
        'status' => 'success',
        'message' => 'Item removed from cart.',
        'cart_count' => $cart_count
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit;
