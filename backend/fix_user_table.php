<?php
/**
 * Fix: Create users table if missing columns
 * Run: http://localhost/thrift/backend/fix_user_table.php
 */

header('Content-Type: text/html');

echo "<h1>Fix Users Table</h1>";

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    echo "❌ 'users' table does not exist. Creating it now...<br><br>";
    
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        mode ENUM('customer', 'seller', 'both') DEFAULT 'both',
        active_mode ENUM('customer', 'seller') DEFAULT 'customer',
        preferred_categories TEXT,
        price_range_min DECIMAL(10,2) DEFAULT 0,
        price_range_max DECIMAL(10,2) DEFAULT 10000,
        preferred_location VARCHAR(100),
        style_preference VARCHAR(50),
        size_preference VARCHAR(20),
        age_group VARCHAR(20),
        interests TEXT,
        is_banned BOOLEAN DEFAULT FALSE,
        report_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_banned (is_banned)
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql)) {
        echo "✅ Users table created successfully!<br>";
    } else {
        echo "❌ Error creating table: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Users table exists<br><br>";
    
    // Check columns
    $result = $conn->query("DESCRIBE users");
    echo "<h3>Current columns in users table:</h3><ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
    }
    echo "</ul>";
}

$conn->close();

echo "<br><p><a href='test_login.php'>Test Login</a></p>";
?>

