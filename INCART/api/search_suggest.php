<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$q = trim($_GET['q'] ?? '');

if ($q === '' || strlen($q) < 1) {
    echo json_encode([
        'status' => 'success',
        'query' => '',
        'is_similar' => false,
        'results' => []
    ]);
    exit;
}

// 1. Search for matching active products
$searchTerm = '%' . $q . '%';
$prefixTerm = $q . '%';
$wordTerm   = '% ' . $q . '%';

if (strlen($q) <= 2) {
    // For 1-2 characters: prioritize product name, category or brand
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.discount_price, p.weight, p.unit,
               c.name as category_name, b.name as brand_name, pi.image_path as primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_active = 1 AND (p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ?)
        ORDER BY 
            (CASE 
                WHEN p.name LIKE ? THEN 0
                WHEN p.name LIKE ? THEN 1
                WHEN p.name LIKE ? THEN 2
                ELSE 3
            END), 
            p.name ASC
        LIMIT 6
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $prefixTerm, $wordTerm, $searchTerm]);
} else {
    // For 3+ characters: check name, category, brand, and short/full description
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.discount_price, p.weight, p.unit,
               c.name as category_name, b.name as brand_name, pi.image_path as primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_active = 1 AND (p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR b.name LIKE ?)
        ORDER BY 
            (CASE 
                WHEN p.name LIKE ? THEN 0
                WHEN p.name LIKE ? THEN 1
                WHEN p.name LIKE ? THEN 2
                WHEN c.name LIKE ? THEN 3
                WHEN b.name LIKE ? THEN 4
                ELSE 5
            END), 
            p.name ASC
        LIMIT 6
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $prefixTerm, $wordTerm, $searchTerm, $prefixTerm, $prefixTerm]);
}
$matches = $stmt->fetchAll();

$isSimilar = false;

// 2. If no direct matches found, search for similar products
if (empty($matches)) {
    $isSimilar = true;
    $matches = get_similar_products($q, 5);
}

$formatted = [];
foreach ($matches as $prod) {
    $img = !empty($prod['primary_image']) ? $prod['primary_image'] : 'assets/images/products/default.jpg';
    $hasDiscount = !empty($prod['discount_price']) && $prod['discount_price'] < $prod['price'];
    $discountPercent = $hasDiscount ? round((($prod['price'] - $prod['discount_price']) / $prod['price']) * 100) : 0;

    $formatted[] = [
        'id' => (int)$prod['id'],
        'name' => $prod['name'],
        'price' => format_price($hasDiscount ? $prod['discount_price'] : $prod['price']),
        'regular_price' => $hasDiscount ? format_price($prod['price']) : null,
        'discount_percent' => $discountPercent,
        'category' => $prod['category_name'] ?? 'Groceries',
        'image' => $img,
        'url' => 'product-details.php?id=' . (int)$prod['id']
    ];
}

echo json_encode([
    'status' => 'success',
    'query' => $q,
    'is_similar' => $isSimilar,
    'count' => count($formatted),
    'results' => $formatted
]);
exit;
