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

if (empty($title) || empty($price) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Title and valid price are required']);
    exit;
}

$valid_conditions = ['new', 'like_new', 'good', 'fair', 'poor'];
if (!in_array($condition_enum, $valid_conditions)) {
    $condition_enum = 'good';
}

$stmt = $conn->prepare("INSERT INTO products (seller_id, title, description, category, price, condition_enum, location, country, city, latitude, longitude, style, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
$stmt->bind_param("isssdsssssss", $user_id, $title, $description, $full_category, $price, $condition_enum, $location, $country, $city, $latitude, $longitude, $style);

if ($stmt->execute()) {
    $product_id = $conn->insert_id;

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = '../uploads/';
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

                    $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_primary, upload_order) VALUES (?, ?, ?, ?)");
                    $img_stmt->bind_param("isii", $product_id, $relative_path, $is_primary, $upload_order);
                    $img_stmt->execute();
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product uploaded successfully',
        'product_id' => $product_id,
        'categories' => $cat_check['categories'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload product: ' . $conn->error]);
}

$conn->close();
