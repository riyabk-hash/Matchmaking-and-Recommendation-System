<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

$product_id = $_GET['product_id'] ?? 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

$stmt = $conn->prepare("SELECT r.rating, r.comment, r.created_at, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode(['success' => true, 'reviews' => $reviews]);

$conn->close();
?>
