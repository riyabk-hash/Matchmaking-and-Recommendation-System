<?php
/**
 * Script to add 'style' column to products table
 * Run this once to update the database schema
 */
require_once 'config/db_config.php';

$conn = getDBConnection();

// Check if style column already exists
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'style'");

if ($result->num_rows == 0) {
    // Add style column
    $sql = "ALTER TABLE products ADD COLUMN style VARCHAR(50) AFTER location";
    
    if ($conn->query($sql)) {
        echo "SUCCESS: 'style' column added to products table";
    } else {
        echo "ERROR: " . $conn->error;
    }
} else {
    echo "INFO: 'style' column already exists in products table";
}

$conn->close();
?>

