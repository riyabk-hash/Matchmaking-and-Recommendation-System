<?php
/**
 * Run once: http://localhost/thrift/backend/migrate_category_column.php
 * Widens products.category for multi-select values.
 */
require_once __DIR__ . '/../config/db_config.php';

$conn = getDBConnection();
$sql = "ALTER TABLE products MODIFY COLUMN category VARCHAR(255) NULL";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'products.category widened to VARCHAR(255)']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
$conn->close();
