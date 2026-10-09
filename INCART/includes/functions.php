<?php
// InCart Global Helper Functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

require_once __DIR__ . '/../config/database.php';

// Format currency as LKR
function format_price($amount) {
    return 'LKR ' . number_format((float)$amount, 2, '.', ',');
}

// XSS Sanitization
function sanitize($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Get Valid Logged In User ID (returns int user_id if valid in users.id table, else null)
function get_valid_logged_in_user_id() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    if ($user_id <= 0) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
        return null;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            return (int)$user['id'];
        } else {
            // Invalid session user_id (not found in users table). Clear session.
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
            return null;
        }
    } catch (Exception $e) {
        return null;
    }
}

// Auth Helpers
function is_logged_in() {
    return get_valid_logged_in_user_id() !== null;
}

function is_admin() {
    $uid = get_valid_logged_in_user_id();
    return $uid !== null && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function current_user() {
    $uid = get_valid_logged_in_user_id();
    if ($uid === null) return null;
    return [
        'id' => $uid,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'customer'
    ];
}

function require_login() {
    if (!is_logged_in()) {

        $_SESSION['flash_error'] = 'Please log in to continue.';

        $returnUrl = $_SERVER['REQUEST_URI'];

        header('Location: login.php?return=' . urlencode($returnUrl));
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Access denied. Administrator privileges required.';
        header('Location: ../login.php');
        exit;
    }
}

// Get or Create Cart ID (by valid user_id or session_id)
// $create_if_not_exists = false will NOT insert a new cart record if one does not exist
function get_cart_id($create_if_not_exists = true) {
    $db = getDB();
    $user_id = get_valid_logged_in_user_id();
    $session_id = session_id();

    if ($user_id !== null) {
        // 1. Check if cart already exists for this valid user_id
        $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch();

        if ($cart) {
            return (int)$cart['id'];
        }

        // 2. Check if a guest cart exists for current session_id to associate with valid user_id
        if (!empty($session_id)) {
            $stmtGuest = $db->prepare("SELECT id FROM carts WHERE session_id = ? AND user_id IS NULL");
            $stmtGuest->execute([$session_id]);
            $guestCart = $stmtGuest->fetch();

            if ($guestCart) {
                $db->prepare("UPDATE carts SET user_id = ? WHERE id = ?")->execute([$user_id, $guestCart['id']]);
                return (int)$guestCart['id'];
            }
        }

        // 3. Do not create if not requested
        if (!$create_if_not_exists) {
            return null;
        }

        // 4. Create new cart for valid user_id
        $stmtNew = $db->prepare("INSERT INTO carts (user_id, session_id) VALUES (?, ?)");
        $stmtNew->execute([$user_id, $session_id]);
        return (int)$db->lastInsertId();

    } else {
        // Guest User (user_id is NULL)
        if (empty($session_id)) {
            return null;
        }

        // 1. Check if guest cart exists for session_id
        $stmt = $db->prepare("SELECT id FROM carts WHERE session_id = ? AND user_id IS NULL");
        $stmt->execute([$session_id]);
        $cart = $stmt->fetch();

        if ($cart) {
            return (int)$cart['id'];
        }

        // 2. Do not create if not requested
        if (!$create_if_not_exists) {
            return null;
        }

        // 3. Create new guest cart with NULL user_id
        $stmtNew = $db->prepare("INSERT INTO carts (user_id, session_id) VALUES (NULL, ?)");
        $stmtNew->execute([$session_id]);
        return (int)$db->lastInsertId();
    }
}

// Cart Quantity Counter
function get_cart_count() {
    $cart_id = get_cart_id(false); // Do not create empty cart rows simply to fetch header count
    if (!$cart_id) {
        return 0;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT SUM(quantity) as total_qty FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cart_id]);
    $res = $stmt->fetch();
    return (int)($res['total_qty'] ?? 0);
}

// Wishlist Counter
function get_wishlist_count() {
    $user_id = get_valid_logged_in_user_id();
    if ($user_id === null) return 0;
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wishlist_items wi JOIN wishlists w ON wi.wishlist_id = w.id WHERE w.user_id = ?");
    $stmt->execute([$user_id]);
    $res = $stmt->fetch();
    return (int)($res['count'] ?? 0);
}

// Check if product in wishlist
function is_in_wishlist($product_id) {
    $user_id = get_valid_logged_in_user_id();
    if ($user_id === null) return false;
    $db = getDB();
    $stmt = $db->prepare("SELECT wi.id FROM wishlist_items wi JOIN wishlists w ON wi.wishlist_id = w.id WHERE w.user_id = ? AND wi.product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    return (bool)$stmt->fetch();
}

// Order Number Generator
function generate_order_number() {
    return 'INC-' . strtoupper(substr(uniqid(), -6));
}

// Render Rating Stars
function render_rating_stars($rating) {
    $rating = round($rating * 2) / 2; // Round to nearest 0.5
    $html = '<div class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($rating == $i - 0.5) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star"></i>';
        }
    }
    $html .= '</div>';
    return $html;
}

// Flash Message Helpers
function set_flash_success($msg) {
    $_SESSION['flash_success'] = $msg;
}

function set_flash_error($msg) {
    $_SESSION['flash_error'] = $msg;
}

function display_flash_messages() {
    $output = '';
    if (isset($_SESSION['flash_success'])) {
        $output .= '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . sanitize($_SESSION['flash_success']) . '</div>';
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        $output .= '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . sanitize($_SESSION['flash_error']) . '</div>';
        unset($_SESSION['flash_error']);
    }
    return $output;
}
