<?php
header('Content-Type: application/json');

// Test if PHP is working
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';

echo json_encode([
    'success' => true,
    'message' => 'PHP is working! Received username: ' . $username,
    'data' => $data
]);
?>
