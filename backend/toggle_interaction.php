<?php
/**
 * REFACTORED OO TOGGLE INTERACTION
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Models/Interaction.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; 
$product_id = (int) ($data['product_id'] ?? 0);

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

try {
    $interaction = new Interaction($user_id, $product_id);
    $result = ['success' => true];

    switch ($type) {
        case 'like':
            $res = $interaction->toggleLike();
            $result = array_merge($result, $res);
            break;

        case 'save':
            $res = $interaction->toggleSave();
            $result = array_merge($result, $res);
            break;

        case 'report':
            $reason = $data['reason'] ?? 'other';
            $interaction->report($reason);
            $result['message'] = 'Report submitted successfully. Community safety is our priority.';
            break;

        default:
            throw new Exception("Invalid interaction type");
    }

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
