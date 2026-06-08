<?php
/**
 * Debug: Check users in database
 * Run: http://localhost/thrift/backend/debug_login.php
 */

header('Content-Type: text/html');

echo "<h1>Debug: Users in Database</h1>";

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to thrift_store<br><br>";

// Get all users
$result = $conn->query("SELECT id, username, email, password, created_at FROM users");

if ($result && $result->num_rows > 0) {
    echo "<h3>Users found: " . $result->num_rows . "</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Password Hash</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . substr($row['password'], 0, 30) . "...</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test password with first user
    $firstUser = $result->fetch_assoc();
    echo "<br><h3>Test Login:</h3>";
    echo "<p>Testing password for user: " . $firstUser['username'] . "</p>";
    
    // Try to verify (need user to provide password)
    echo "<p>Enter the password you used to register to test:</p>";
    echo "<form method='post'>";
    echo "<input type='text' name='test_password' placeholder='Enter password'>";
    echo "<button type='submit'>Test</button>";
    echo "</form>";
    
    if (isset($_POST['test_password'])) {
        $testpwd = $_POST['test_password'];
        if (password_verify($testpwd, $firstUser['password'])) {
            echo "<p style='color:green'>✅ PASSWORD MATCHES!</p>";
        } else {
            echo "<p style='color:red'>❌ PASSWORD DOES NOT MATCH</p>";
            echo "<p>Stored hash: " . $firstUser['password'] . "</p>";
            echo "<p>Your input: " . password_hash($testpwd, PASSWORD_DEFAULT) . "</p>";
        }
    }
    
} else {
    echo "❌ No users found in database!";
    echo "<br><br><a href='../register.html'>Go to Register</a>";
}

$conn->close();
?>

