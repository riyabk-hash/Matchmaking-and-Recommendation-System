<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to report content']);
    exit;
}

$reporter_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$reported_user_id = $data['reported_user_id'] ?? null;
$product_id = $data['product_id'] ?? null;
$report_type = $data['report_type'] ?? 'other';
$report_reason = $data['report_reason'] ?? '';

if (!$reported_user_id || empty($report_reason)) {
    echo json_encode(['success' => false, 'message' => 'Missing required report information']);
    exit;
}

if ($reporter_id == $reported_user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot report yourself']);
    exit;
}

// Start transaction to ensure data integrity
$conn->begin_transaction();

try {
    // 1. Insert report record
    $stmt = $conn->prepare("INSERT INTO reports (reported_user_id, reporter_id, product_id, report_type, report_reason) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $reported_user_id, $reporter_id, $product_id, $report_type, $report_reason);
    $stmt->execute();

    // 2. Increment report count for the target user
    $stmt = $conn->prepare("UPDATE users SET report_count = report_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $reported_user_id);
    $stmt->execute();

    // 3. Check for auto-ban threshold
    $stmt = $conn->prepare("SELECT report_count FROM users WHERE id = ?");
    $stmt->bind_param("i", $reported_user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $new_count = $result['report_count'] ?? 0;

    $banned = false;
    if ($new_count >= 5) {
        $stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
        $stmt->bind_param("i", $reported_user_id);
        $stmt->execute();
        $banned = true;
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Report submitted successfully. Thank you for helping keep the community safe.',
        'auto_banned' => $banned
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
