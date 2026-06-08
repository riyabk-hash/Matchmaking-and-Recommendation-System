<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

$seller_id = $_GET['seller_id'] ?? 0;

if (!$seller_id || !is_numeric($seller_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid seller ID']);
    exit;
}

// Get basic public seller info
$stmt = $conn->prepare("SELECT id, username, address, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller_result = $stmt->get_result();

if ($seller_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Seller not found']);
    exit;
}

$seller = $seller_result->fetch_assoc();

// Get active products for this seller
$prod_stmt = $conn->prepare("
    SELECT p.id, p.title, p.description, p.category, p.price, p.condition_enum, p.location, p.created_at,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
    FROM products p
    WHERE p.seller_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC
");
$prod_stmt->bind_param("i", $seller_id);
$prod_stmt->execute();
$products_result = $prod_stmt->get_result();

$products = [];
while ($row = $products_result->fetch_assoc()) {
    if (empty($row['image_path'])) {
        $img_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY upload_order ASC LIMIT 1");
        $img_stmt->bind_param("i", $row['id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        if ($img_row = $img_result->fetch_assoc()) {
            $row['image_path'] = $img_row['image_path'];
        }
    }
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'seller' => [
        'id' => $seller['id'],
        'username' => $seller['username'],
        'location' => $seller['address'] ?: 'Not specified',
        'joined' => date('M Y', strtotime($seller['created_at']))
    ],
    'products' => $products
]);

$conn->close();
?>
