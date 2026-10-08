<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

$user_id = get_valid_logged_in_user_id();

if ($user_id === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please log in to save items to your wishlist.',
        'require_login' => true
    ]);
    exit;
}

$db = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'toggle') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID.', 'require_login' => false]);
        exit;
    }

    // Verify product exists
    $pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ?");
    $pStmt->execute([$product_id]);
    $product = $pStmt->fetch();

    if (!$product) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found.', 'require_login' => false]);
        exit;
    }

    // Get or Create Wishlist ID for user
    $wStmt = $db->prepare("SELECT id FROM wishlists WHERE user_id = ?");
    $wStmt->execute([$user_id]);
    $wishlist = $wStmt->fetch();

    if ($wishlist) {
        $wishlist_id = (int)$wishlist['id'];
    } else {
        $insW = $db->prepare("INSERT INTO wishlists (user_id) VALUES (?)");
        $insW->execute([$user_id]);
        $wishlist_id = (int)$db->lastInsertId();
    }

    // Check if item is already in wishlist
    $itemStmt = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?");
    $itemStmt->execute([$wishlist_id, $product_id]);
    $existingItem = $itemStmt->fetch();

    if ($existingItem) {
        // Remove
        $del = $db->prepare("DELETE FROM wishlist_items WHERE id = ?");
        $del->execute([$existingItem['id']]);
        $is_in_wishlist = false;
        $message = htmlspecialchars($product['name']) . ' removed from your wishlist.';
    } else {
        // Add
        $ins = $db->prepare("INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)");
        $ins->execute([$wishlist_id, $product_id]);
        $is_in_wishlist = true;
        $message = htmlspecialchars($product['name']) . ' saved to your wishlist!';
    }

    $wishlist_count = get_wishlist_count();

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'is_in_wishlist' => $is_in_wishlist,
        'wishlist_count' => $wishlist_count,
        'require_login' => false
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.', 'require_login' => false]);
exit;
