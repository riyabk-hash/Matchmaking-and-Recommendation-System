<?php
session_start();
header('Content-Type: application/json');

// Include Models and Services
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Product.php';
require_once __DIR__ . '/Services/RecommendationEngine.php';

$product_id = $_GET['id'] ?? 0;

if (!$product_id || !is_numeric($product_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}


// Check if user is logged in
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Simple connection
$conn = new mysqli('localhost', 'root', '', 'thrift_store');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Get product with images
$stmt = $conn->prepare("
    SELECT p.*, 
           u.username as seller_name, 
           u.email as seller_email,
           u.phone as seller_phone,
           u.address as seller_address
    FROM products p
    JOIN users u ON p.seller_id = u.id
    WHERE p.id = ? AND p.status = 'active'
");

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found. ID: ' . $product_id]);
    exit;
}

$product = $result->fetch_assoc();

// Determine if we should show contact info
$show_contact = false;
$is_owner = false;

if ($current_user_id) {
    $is_owner = ($current_user_id == $product['seller_id']);
    // Show contact info to logged in users (but not to the seller themselves)
    $show_contact = !$is_owner;
}

// Get images
$img_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY upload_order ASC");
$img_stmt->bind_param("i", $product_id);
$img_stmt->execute();
$images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Use product image_path if no images in product_images
if (empty($images) && !empty($product['image_path'])) {
    $images = [['image_path' => $product['image_path']]];
}

// Interaction states
$is_liked = false;
$is_saved = false;

if ($current_user_id) {
    // Check Liked
    $check_lk = $conn->prepare("SELECT 1 FROM product_likes WHERE user_id = ? AND product_id = ?");
    $check_lk->bind_param("ii", $current_user_id, $product_id);
    $check_lk->execute();
    $is_liked = $check_lk->get_result()->num_rows > 0;

    // Check Saved
    $check_sv = $conn->prepare("SELECT 1 FROM product_saves WHERE user_id = ? AND product_id = ?");
    $check_sv->bind_param("ii", $current_user_id, $product_id);
    $check_sv->execute();
    $is_saved = $check_sv->get_result()->num_rows > 0;
}

$recommendation_breakdown = null;
if ($current_user_id) {
    try {
        $user = new User($current_user_id);
        if ($user->id && !empty($user->preferences['categories'])) {
            $similar_user_ids = $user->getSimilarUserIds();
            $engine = new RecommendationEngine();
            $recommendation_breakdown = $engine->calculateDetailedScore($user, $product, $similar_user_ids);
        }
    } catch (Exception $e) {
        // Silent catch to prevent breaking product detail payload
    }
}

echo json_encode([
    'success' => true,
    'product' => $product,
    'images' => $images,
    'is_liked' => $is_liked,
    'is_saved' => $is_saved,
    'show_contact' => $show_contact,
    'is_owner' => $is_owner,
    'current_user_id' => $current_user_id,
    'recommendation_breakdown' => $recommendation_breakdown
]);

$conn->close();
?>
