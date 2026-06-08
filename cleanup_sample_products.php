<?php
header('Content-Type: application/json');
require_once 'config/db_config.php';

$conn = getDBConnection();

// Delete sample products (those without images uploaded by users)
// We'll keep products that have at least one image in product_images table
$query = "
    DELETE p FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id 
    WHERE pi.id IS NULL
";

if ($conn->query($query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Sample products removed successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $conn->error
    ]);
}

$conn->close();
?>
</parameter>
</create_file>
