<?php
/**
 * Direct Login Test
 * Run: http://localhost/thrift/backend/test_login_direct.php?username=YOUR_USERNAME&password=YOUR_PASSWORD
 */

header('Content-Type: text/html');

echo "<h1>Direct Login Test</h1>";

$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

if (empty($username) || empty($password)) {
    echo "<p>Please add ?username=YOUR_USERNAME&password=YOUR_PASSWORD to the URL</p>";
    echo "<p>Example: test_login_direct.php?username=john&password=secret123</p>";
    exit;
}

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Testing login for: " . htmlspecialchars($username) . "</h3>";

// Step 1: Find user
$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ User not found in database!<br>";
    echo "<br><h3>All users in database:</h3>";
    $all = $conn->query("SELECT username, email FROM users");
    while ($u = $all->fetch_assoc()) {
        echo "- " . $u['username'] . " (" . $u['email'] . ")<br>";
    }
} else {
    $user = $result->fetch_assoc();
    echo "✅ User found: ID=" . $user['id'] . ", Username=" . $user['username'] . "<br>";
    echo "Stored password hash: " . substr($user['password'], 0, 40) . "...<br><br>";
    
    // Step 2: Check password
    if (password_verify($password, $user['password'])) {
        echo "✅ <strong>PASSWORD VERIFIED! Login should work!</strong>";
    } else {
        echo "❌ <strong>PASSWORD DOES NOT MATCH!</strong><br>";
        echo "The password you entered ('$password') doesn't match the stored hash.";
    }
}

$conn->close();
?>

