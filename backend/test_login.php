<?php
/**
 * Simple Login Test
 * Access: http://localhost/thrift/backend/test_login.php
 */

header('Content-Type: text/html');

echo "<h1>Login System Test</h1>";

// Test 1: Check PHP
echo "<h3>1. PHP:</h3>";
echo "✅ PHP Version: " . phpversion() . "<br>";

// Test 2: Check MySQL
echo "<h3>2. MySQL:</h3>";
try {
    $conn = new mysqli("localhost", "root", "");
    if ($conn->connect_error) {
        echo "❌ MySQL Error: " . $conn->connect_error . "<br>";
    } else {
        echo "✅ MySQL Connected: " . $conn->server_info . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Check Database
echo "<h3>3. Database 'thrift_store':</h3>";
$conn->select_db("thrift_store");
if ($conn->error) {
    echo "❌ Database missing: " . $conn->error . "<br>";
    echo "<p><strong>Fix:</strong> Go to phpMyAdmin, create database 'thrift_store', import database/quick_setup.sql</p>";
} else {
    echo "✅ Database exists<br>";
}

// Test 4: Check Users Table
echo "<h3>4. Users Table:</h3>";
$result = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Users table works! Total users: " . $row['cnt'] . "<br>";
} else {
    echo "❌ Users table error: " . $conn->error . "<br>";
}

$conn->close();

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>Make sure MySQL is running in XAMPP</li>";
echo "<li>Make sure database 'thrift_store' exists in phpMyAdmin</li>";
echo "<li>Try registering a new user at register.html</li>";
echo "<li>Then try logging in at login.html</li>";
echo "</ul>";
?>

