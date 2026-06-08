<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? 0;
$rating = $data['rating'] ?? 0;
$comment = $data['comment'] ?? '';

if (!$product_id || !$rating || !$comment) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating']);
    exit;
}

// Check if user has already reviewed this product
$stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
    exit;
}

// Insert review
$stmt = $conn->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $_SESSION['user_id'], $product_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
}

$conn->close();
?>
