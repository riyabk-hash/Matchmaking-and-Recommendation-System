<?php
/**
 * Rebuild Corrupted Tables - FIXED
 * Run: http://localhost/thrift/backend/rebuild_tables.php
 */

header('Content-Type: text/html');

echo "<h1>Rebuild Corrupted Tables</h1>";

$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop database completely and recreate
echo "<h3>Dropping database...</h3>";
$conn->query("DROP DATABASE IF EXISTS thrift_store");
echo "✅ Dropped thrift_store database<br>";

echo "<h3>Creating fresh database...</h3>";
$conn->query("CREATE DATABASE thrift_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "✅ Created thrift_store database<br>";

$conn->select_db("thrift_store");

echo "<h3>Creating tables...</h3>";

// Create users table
$sql = "CREATE TABLE users (
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

if ($conn->query($sql)) echo "✅ Created users table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create products table
$sql = "CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    condition_enum ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'good',
    location VARCHAR(100),
    style VARCHAR(50),
    status ENUM('active', 'sold', 'removed') DEFAULT 'active',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_title (title)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created products table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create product_images table
$sql = "CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    upload_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created product_images table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create orders table
$sql = "CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    customer_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_customer (customer_id),
    INDEX idx_seller (seller_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created orders table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create comments table
$sql = "CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created comments table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create reports table
$sql = "CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reported_user_id INT NOT NULL,
    reporter_id INT NOT NULL,
    product_id INT NULL,
    report_type ENUM('scam', 'fake_product', 'inappropriate', 'other') DEFAULT 'scam',
    report_reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_reported_user (reported_user_id),
    INDEX idx_reporter (reporter_id),
    INDEX idx_status (status)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created reports table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

// Create contact_messages table
$sql = "CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    product_id INT NULL,
    message_text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB";

if ($conn->query($sql)) echo "✅ Created contact_messages table<br>";
else echo "❌ Error: " . $conn->error . "<br>";

echo "<h3>Verifying tables...</h3>";
$result = $conn->query("SHOW TABLES");
echo "<ul>";
while ($row = $result->fetch_array()) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";

echo "<h3>✅ Done! Now try:</h3>";
echo "<p>1. <a href='../login.html'>Test Login</a></p>";
echo "<p>2. <a href='../register.html'>Register a new user</a></p>";

$conn->close();
?>

