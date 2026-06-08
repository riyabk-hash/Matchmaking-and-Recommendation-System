<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$preferred_categories = json_encode($data['preferred_categories'] ?? []);
$price_range_min = $data['price_range_min'] ?? 0;
$price_range_max = $data['price_range_max'] ?? 10000;
$preferred_location = $data['preferred_location'] ?? '';
$country = $data['country'] ?? '';
$city = $data['city'] ?? '';
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$style_preference = $data['style_preference'] ?? '';
$size_preference = $data['size_preference'] ?? '';

$stmt = $conn->prepare("UPDATE users SET preferred_categories = ?, price_range_min = ?, price_range_max = ?, preferred_location = ?, country = ?, city = ?, latitude = ?, longitude = ?, style_preference = ?, size_preference = ? WHERE id = ?");
$stmt->bind_param("sddsssssssi", $preferred_categories, $price_range_min, $price_range_max, $preferred_location, $country, $city, $latitude, $longitude, $style_preference, $size_preference, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update preferences']);
}

$conn->close();
?>
