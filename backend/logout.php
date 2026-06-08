<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy the session
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
