<?php
header('Content-Type: application/json');
require_once 'config/db_config.php';

$conn = getDBConnection();

// Get all products to see their categories
$query = "SELECT p.id, p.title, p.category, p.status FROM products p ORDER BY p.created_at DESC LIMIT 30";

$result = $conn->query($query);

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Also test what happens with women_shoes filter
$test_cat = 'women_shoes';
$test_query = "SELECT id, title, category FROM products p WHERE p.status = 'active' AND p.category = ?";
$stmt = $conn->prepare($test_query);
$stmt->bind_param("s", $test_cat);
$stmt->execute();
$test_result = $stmt->get_result();

$filtered_products = [];
while ($row = $test_result->fetch_assoc()) {
    $filtered_products[] = $row;
}

echo json_encode([
    'all_products' => $products,
    'filtered_by_women_shoes' => $filtered_products,
    'total_products' => count($products),
    'filtered_count' => count($filtered_products)
], JSON_PRETTY_PRINT);

$conn->close();
?>

