<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $query = "SELECT p.*, u.username as seller_name,
                     (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
              FROM products p
              JOIN product_saves ps ON p.id = ps.product_id
              JOIN users u ON p.seller_id = u.id
              WHERE ps.user_id = ? AND p.status = 'active'
              ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
