<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

// Get all active products with their primary images
// This endpoint is public and doesn't require login
$query = "SELECT p.id, p.title, p.description, p.category, p.price, p.condition_enum, p.location, p.created_at,
                 u.username as seller_name,
                 (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
          FROM products p
          JOIN users u ON p.seller_id = u.id
          WHERE p.status = 'active' AND u.is_banned = 0
          ORDER BY p.created_at DESC";

$result = $conn->query($query);

$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // If no primary image, get the first image
        if (empty($row['image_path'])) {
            $img_query = "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY upload_order ASC LIMIT 1";
            $img_stmt = $conn->prepare($img_query);
            $img_stmt->bind_param("i", $row['id']);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            if ($img_row = $img_result->fetch_assoc()) {
                $row['image_path'] = $img_row['image_path'];
            }
        }
        $products[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'products' => $products
]);

$conn->close();
?>

