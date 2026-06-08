<?php
/**
 * Check exact table names in thrift_store
 * Run: http://localhost/thrift/backend/check_tables.php
 */

header('Content-Type: text/html');

echo "<h1>Table Check</h1>";

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to thrift_store<br><br>";

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<h3>Tables found:</h3><ul>";
    while ($row = $result->fetch_array()) {
        echo "<li><strong>" . $row[0] . "</strong></li>";
    }
    echo "</ul>";
}

echo "<br><h3>Checking 'users' vs 'user':</h3>";
$test1 = $conn->query("SELECT COUNT(*) FROM users");
$test2 = $conn->query("SELECT COUNT(*) FROM user");

if ($test1) echo "✅ 'users' table exists<br>";
else echo "❌ 'users' table NOT found<br>";

if ($test2) echo "✅ 'user' table exists<br>";
else echo "❌ 'user' table NOT found<br>";

$conn->close();
?>

