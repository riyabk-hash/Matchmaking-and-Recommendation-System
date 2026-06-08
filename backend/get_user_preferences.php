<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_in' => false, 'success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT username, email, preferred_categories, price_range_min, price_range_max, preferred_location, style_preference, size_preference FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $prefs = $result->fetch_assoc();
    $prefs['preferred_categories'] = json_decode($prefs['preferred_categories'], true) ?? [];
    echo json_encode([
        'logged_in' => true,
        'success' => true,
        'user_id' => $user_id,
        'username' => $prefs['username'],
        'email' => $prefs['email'],
        'preferences' => $prefs
    ]);
} else {
    echo json_encode(['logged_in' => false, 'success' => false, 'message' => 'User preferences not found']);
}

$conn->close();
?>
