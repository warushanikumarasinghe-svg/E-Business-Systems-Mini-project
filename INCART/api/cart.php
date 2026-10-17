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
    $weight     = isset($_POST['weight']) ? trim($_POST['weight']) : '';

    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID.']);
        exit;
    }

    // Fetch product
    $stmt = $db->prepare("SELECT id, name, stock_quantity, is_active, category_id, subcategory_id, weight FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product || (int)$product['is_active'] !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Product is unavailable or out of stock.']);
        exit;
    }

    // Determine weight for vegetable products
    $selectedWeight = null;
    if (is_vegetable_product($product)) {
        $allowedWeights = get_vegetable_weights();
        if (in_array($weight, $allowedWeights, true)) {
            $selectedWeight = $weight;
        } else {
            $selectedWeight = '500g'; // Default vegetable weight
        }
    }

    $cart_id = get_cart_id(true);
    if (!$cart_id) {
        echo json_encode(['status' => 'error', 'message' => 'Could not initialize shopping cart session.']);
        exit;
    }

    // Check existing item in cart matching both product_id and selected_weight
    if ($selectedWeight !== null) {
        $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND selected_weight = ?");
        $checkStmt->execute([$cart_id, $product_id, $selectedWeight]);
    } else {
        $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (selected_weight IS NULL OR selected_weight = '')");
        $checkStmt->execute([$cart_id, $product_id]);
    }
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
        $ins = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, selected_weight) VALUES (?, ?, ?, ?)");
        $ins->execute([$cart_id, $product_id, $quantity, $selectedWeight]);
    }

    $cart_count = get_cart_count();
    $itemLabel = htmlspecialchars($product['name']) . ($selectedWeight ? " ({$selectedWeight})" : "");

    echo json_encode([
        'status' => 'success',
        'message' => $itemLabel . ' added to your cart!',
        'cart_count' => $cart_count
    ]);
    exit;

} elseif ($action === 'update') {
    $cart_item_id = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
    $product_id   = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity     = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $cart_id      = get_cart_id(false);

    if ($cart_id) {
        if ($cart_item_id > 0) {
            $upd = $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND id = ?");
            $upd->execute([$quantity, $cart_id, $cart_item_id]);
        } elseif ($product_id > 0) {
            $upd = $db->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $upd->execute([$quantity, $cart_id, $product_id]);
        }
    }

    $cart_count = get_cart_count();
    echo json_encode([
        'status' => 'success',
        'message' => 'Cart updated.',
        'cart_count' => $cart_count
    ]);
    exit;

} elseif ($action === 'remove') {
    $cart_item_id = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
    $product_id   = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $cart_id      = get_cart_id(false);

    if ($cart_id) {
        if ($cart_item_id > 0) {
            $del = $db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND id = ?");
            $del->execute([$cart_id, $cart_item_id]);
        } elseif ($product_id > 0) {
            $del = $db->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
            $del->execute([$cart_id, $product_id]);
        }
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
