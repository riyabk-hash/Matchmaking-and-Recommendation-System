<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';
require_once __DIR__ . '/category_utils.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$product_id = $_POST['product_id'] ?? null;
if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

// Verify ownership
$stmt = $conn->prepare('SELECT seller_id FROM products WHERE id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch() || $seller_id != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authorized to edit this product']);
    exit;
}
$stmt->close();

// Gather fields (use existing values if not provided)
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$condition_enum = $_POST['condition'] ?? 'good';
$price = $_POST['price'] ?? 0;
$location = $_POST['location'] ?? '';
$country = $_POST['country'] ?? '';
$city = $_POST['city'] ?? '';
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$style = $_POST['style'] ?? '';

$full_category = resolve_product_categories_from_request();
$cat_check = validate_product_categories_field($full_category);
if (!$cat_check['ok']) {
    echo json_encode(['success' => false, 'message' => $cat_check['message']]);
    exit;
}
$full_category = $cat_check['encoded'];

// Validate required fields
if (empty($title) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Title and valid price are required']);
    exit;
}

$valid_conditions = ['new', 'like_new', 'good', 'fair', 'poor'];
if (!in_array($condition_enum, $valid_conditions)) {
    $condition_enum = 'good';
}

// Update main product record
$update_sql = "UPDATE products SET title = ?, description = ?, category = ?, price = ?, condition_enum = ?, location = ?, country = ?, city = ?, latitude = ?, longitude = ?, style = ? WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param('sssdsssssssi', $title, $description, $full_category, $price, $condition_enum, $location, $country, $city, $latitude, $longitude, $style, $product_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update product: ' . $stmt->error]);
    exit;
}
$stmt->close();

// Replace images when new uploads are provided
if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    $imgStmt = $conn->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $imgStmt->bind_param('i', $product_id);
    $imgStmt->execute();
    $imgStmt->bind_result($image_path);
    while ($imgStmt->fetch()) {
        $file = dirname(__DIR__) . '/' . ltrim($image_path, '/');
        if (is_file($file)) {
            @unlink($file);
        }
    }
    $imgStmt->close();

    $delStmt = $conn->prepare('DELETE FROM product_images WHERE product_id = ?');
    $delStmt->bind_param('i', $product_id);
    $delStmt->execute();
    $delStmt->close();

    $upload_dir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
            $file_type = $_FILES['images']['type'][$key];
            if (!in_array($file_type, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])) {
                continue;
            }
            $filename = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
            $filepath = $upload_dir . $filename;
            if (move_uploaded_file($tmp_name, $filepath)) {
                $relative_path = 'uploads/' . $filename;
                $is_primary = ($key === 0) ? 1 : 0;
                $upload_order = $key;
                $img_stmt = $conn->prepare('INSERT INTO product_images (product_id, image_path, is_primary, upload_order) VALUES (?, ?, ?, ?)');
                $img_stmt->bind_param('isii', $product_id, $relative_path, $is_primary, $upload_order);
                $img_stmt->execute();
                $img_stmt->close();
            }
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
$conn->close();
?>
