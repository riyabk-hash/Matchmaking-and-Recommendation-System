<?php
require_once 'config/db_config.php';

$conn = getDBConnection();
echo "Database connection successful!<br>";

$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "Users table exists!<br>";
} else {
    echo "Users table does not exist. Please run setup.sql<br>";
}

$conn->close();
?>
