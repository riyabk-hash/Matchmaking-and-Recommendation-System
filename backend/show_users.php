lo<?php
/**
 * Show all users and their preferences
 * Run: http://localhost/thrift/backend/show_users.php
 */

header('Content-Type: text/html');

echo "<h1>All Users & Preferences</h1>";

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT * FROM users");

if ($result && $result->num_rows > 0) {
    echo "<p>Total users: " . $result->num_rows . "</p>";
    
    while ($user = $result->fetch_assoc()) {
        echo "<div style='background:#f5f5f5; padding:15px; margin:10px 0; border-radius:8px;'>";
        echo "<h3>User: " . htmlspecialchars($user['username']) . "</h3>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
        echo "<p><strong>Full Name:</strong> " . htmlspecialchars($user['full_name'] ?? 'Not set') . "</p>";
        echo "<p><strong>Mode:</strong> " . htmlspecialchars($user['mode']) . "</p>";
        echo "<hr>";
        echo "<h4>🎯 Preferences:</h4>";
        echo "<p><strong>Preferred Categories:</strong> " . htmlspecialchars($user['preferred_categories'] ?? 'Not set') . "</p>";
        echo "<p><strong>Price Range:</strong> $" . $user['price_range_min'] . " - $" . $user['price_range_max'] . "</p>";
        echo "<p><strong>Location:</strong> " . htmlspecialchars($user['preferred_location'] ?? 'Not set') . "</p>";
        echo "<p><strong>Style:</strong> " . htmlspecialchars($user['style_preference'] ?? 'Not set') . "</p>";
        echo "<p><strong>Size:</strong> " . htmlspecialchars($user['size_preference'] ?? 'Not set') . "</p>";
        echo "<p><strong>Age Group:</strong> " . htmlspecialchars($user['age_group'] ?? 'Not set') . "</p>";
        echo "<p><strong>Interests:</strong> " . htmlspecialchars($user['interests'] ?? 'Not set') . "</p>";
        echo "</div>";
    }
} else {
    echo "No users found!";
}

$conn->close();
?>

