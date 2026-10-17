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
        try {
            $db->beginTransaction();

            // Find the logged-in user's existing cart.
            $stmt = $db->prepare(
                "SELECT id FROM carts WHERE user_id = ? LIMIT 1"
            );
            $stmt->execute([$user_id]);
            $userCart = $stmt->fetch();

            // Find the guest cart from the current session.
            $guestCart = null;

            if ($session_id !== '') {
                $stmt = $db->prepare(
                    "SELECT id FROM carts
                     WHERE session_id = ? AND user_id IS NULL
                     LIMIT 1"
                );
                $stmt->execute([$session_id]);
                $guestCart = $stmt->fetch();
            }

            // If the user has no cart, convert the guest cart
            // into the user's cart.
            if (!$userCart && $guestCart) {
                $stmt = $db->prepare(
                    "UPDATE carts SET user_id = ? WHERE id = ?"
                );
                $stmt->execute([$user_id, $guestCart['id']]);

                $cartId = (int)$guestCart['id'];
                $db->commit();

                return $cartId;
            }

            // If both carts exist, merge guest items into user cart.
            if ($userCart && $guestCart) {
                $userCartId = (int)$userCart['id'];
                $guestCartId = (int)$guestCart['id'];

                if ($userCartId !== $guestCartId) {
                    $stmt = $db->prepare(
                        "SELECT product_id, quantity
                         FROM cart_items WHERE cart_id = ?"
                    );
                    $stmt->execute([$guestCartId]);
                    $guestItems = $stmt->fetchAll();

                    foreach ($guestItems as $item) {
                        $stmt = $db->prepare(
                            "SELECT id, quantity FROM cart_items
                             WHERE cart_id = ? AND product_id = ?
                             LIMIT 1"
                        );
                        $stmt->execute([
                            $userCartId,
                            $item['product_id']
                        ]);
                        $existingItem = $stmt->fetch();

                        if ($existingItem) {
                            $stmt = $db->prepare(
                                "UPDATE cart_items
                                 SET quantity = quantity + ?
                                 WHERE id = ?"
                            );
                            $stmt->execute([
                                (int)$item['quantity'],
                                $existingItem['id']
                            ]);
                        } else {
                            $stmt = $db->prepare(
                                "INSERT INTO cart_items
                                 (cart_id, product_id, quantity)
                                 VALUES (?, ?, ?)"
                            );
                            $stmt->execute([
                                $userCartId,
                                $item['product_id'],
                                (int)$item['quantity']
                            ]);
                        }
                    }

                    // Remove the old guest cart after merging.
                    $db->prepare(
                        "DELETE FROM cart_items WHERE cart_id = ?"
                    )->execute([$guestCartId]);

                    $db->prepare(
                        "DELETE FROM carts WHERE id = ?"
                    )->execute([$guestCartId]);
                }

                $db->commit();
                return $userCartId;
            }

            // Existing user cart, with no guest cart to merge.
            if ($userCart) {
                $cartId = (int)$userCart['id'];
                $db->commit();

                return $cartId;
            }

            // Do not create a new cart if not requested.
            if (!$create_if_not_exists) {
                $db->commit();
                return null;
            }

            // Create a cart for the logged-in user.
            $stmt = $db->prepare(
                "INSERT INTO carts (user_id, session_id)
                 VALUES (?, ?)"
            );
            $stmt->execute([$user_id, $session_id]);

            $cartId = (int)$db->lastInsertId();
            $db->commit();

            return $cartId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Cart merge error: ' . $e->getMessage());
            return null;
        }
    }

    // Guest user: find or create the session cart.
    if ($session_id === '') {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT id FROM carts
         WHERE session_id = ? AND user_id IS NULL
         LIMIT 1"
    );
    $stmt->execute([$session_id]);
    $cart = $stmt->fetch();

    if ($cart) {
        return (int)$cart['id'];
    }

    if (!$create_if_not_exists) {
        return null;
    }

    $stmt = $db->prepare(
        "INSERT INTO carts (user_id, session_id)
         VALUES (NULL, ?)"
    );
    $stmt->execute([$session_id]);

    return (int)$db->lastInsertId();
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

// ==========================================
// Vegetable Weight & Grams Selling Helpers
// ==========================================
function get_vegetable_weights() {
    return ['100g', '250g', '500g', '1kg'];
}

function parse_weight_in_grams($weightStr) {
    if (empty($weightStr)) return 500;
    $str = strtolower(trim($weightStr));
    if (strpos($str, 'kg') !== false) {
        $num = (float)str_replace(['kg', ' '], '', $str);
        return $num > 0 ? (int)($num * 1000) : 1000;
    }
    if (strpos($str, 'g') !== false) {
        $num = (float)str_replace(['g', ' '], '', $str);
        return $num > 0 ? (int)$num : 500;
    }
    return 500;
}

function is_vegetable_product($product) {
    if (!$product) return false;
    $subcatId = (int)($product['subcategory_id'] ?? 0);
    $subcatName = strtolower($product['subcategory_name'] ?? '');
    $prodName = strtolower($product['name'] ?? '');
    
    if ($subcatId === 2 || strpos($subcatName, 'vegetable') !== false) {
        return true;
    }
    
    $vegNames = ['tomato', 'carrot', 'potato', 'onion', 'capsicum'];
    foreach ($vegNames as $vn) {
        if (strpos($prodName, $vn) !== false) {
            return true;
        }
    }
    return false;
}

function calculate_weight_price($basePrice, $baseWeightGrams, $targetWeightGrams) {
    if ($baseWeightGrams <= 0) $baseWeightGrams = 500;
    return round(((float)$basePrice / $baseWeightGrams) * $targetWeightGrams, 2);
}

function get_product_pricing($product, $selectedWeight = null) {
    $regularPrice = (float)($product['price'] ?? 0);
    $discountPrice = !empty($product['discount_price']) ? (float)$product['discount_price'] : 0.00;
    
    if (is_vegetable_product($product) && !empty($selectedWeight)) {
        $baseGrams = parse_weight_in_grams($product['weight'] ?? '500g');
        $targetGrams = parse_weight_in_grams($selectedWeight);
        $regularPrice = calculate_weight_price($regularPrice, $baseGrams, $targetGrams);
        if ($discountPrice > 0) {
            $discountPrice = calculate_weight_price($discountPrice, $baseGrams, $targetGrams);
        }
    }
    
    $effectivePrice = ($discountPrice > 0 && $discountPrice < $regularPrice) ? $discountPrice : $regularPrice;
    
    return [
        'regular_price' => $regularPrice,
        'discount_price' => $discountPrice,
        'effective_price' => $effectivePrice,
        'has_discount' => ($discountPrice > 0 && $discountPrice < $regularPrice)
    ];
}
