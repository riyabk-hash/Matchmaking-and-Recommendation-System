<?php
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$product_id = (int) ($input['product_id'] ?? ($_POST['product_id'] ?? 0));

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

// Verify ownership
$stmt = $conn->prepare('SELECT seller_id FROM products WHERE id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
$stmt->bind_result($seller_id);
if (!$stmt->fetch() || (int) $seller_id !== $user_id) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Not authorized to delete this product']);
    exit;
}
$stmt->close();

/**
 * Delete rows from a table when it exists and has a product_id column.
 */
function deleteByProductId($conn, $table, $product_id) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return;
    }

    $check = $conn->query("SHOW TABLES LIKE '$safeTable'");
    if (!$check || $check->num_rows === 0) {
        return;
    }

    $colCheck = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE 'product_id'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        return;
    }

    $del = $conn->prepare("DELETE FROM `$safeTable` WHERE product_id = ?");
    if ($del) {
        $del->bind_param('i', $product_id);
        $del->execute();
        $del->close();
    }
}

$conn->begin_transaction();

try {
    // Clear dependent rows before product delete
    foreach (['product_likes', 'product_saves', 'comments', 'orders', 'reviews'] as $table) {
        deleteByProductId($conn, $table, $product_id);
    }

    // Collect image paths (avoid get_result() for mysqlnd compatibility)
    $image_paths = [];
    $imgStmt = $conn->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    if ($imgStmt) {
        $imgStmt->bind_param('i', $product_id);
        $imgStmt->execute();
        $imgStmt->bind_result($image_path);
        while ($imgStmt->fetch()) {
            $image_paths[] = $image_path;
        }
        $imgStmt->close();
    }

    deleteByProductId($conn, 'product_images', $product_id);

    $delProdStmt = $conn->prepare('DELETE FROM products WHERE id = ? AND seller_id = ?');
    if (!$delProdStmt) {
        throw new Exception('Could not prepare delete statement');
    }
    $delProdStmt->bind_param('ii', $product_id, $user_id);
    if (!$delProdStmt->execute() || $delProdStmt->affected_rows === 0) {
        $delProdStmt->close();
        throw new Exception('Failed to delete product: ' . $conn->error);
    }
    $delProdStmt->close();

    $conn->commit();

    // Remove files after DB commit
    foreach ($image_paths as $path) {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/');
        if (is_file($file)) {
            @unlink($file);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
