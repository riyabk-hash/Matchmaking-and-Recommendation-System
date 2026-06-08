<?php
/**
 * FIXED PRODUCTS ENDPOINT WITH ERROR HANDLING
 * Category browsing + basic search + prefs boost
 */

header('Content-Type: application/json');
require_once '../config/db_config.php';

try {
    $conn = getDBConnection();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get params
    $category = $_GET['category'] ?? 'all';
    $q = trim($_GET['q'] ?? '');

    // Base query
    $query = "SELECT p.*, u.username as seller_name,
                     (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
              FROM products p JOIN users u ON p.seller_id = u.id
              WHERE p.status = 'active'";

    $params = [];
    $types = '';

    if (!empty($q)) {
        $term = '%' . $q . '%';
        $query .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
        $params = [$term, $term, $term];
        $types = 'sss';
    }

    // Category filter
    if ($category && $category !== 'all') {
        $category = strtolower($category);
        if (in_array($category, ['lamps', 'furniture', 'walldecor'])) {
            $query .= " AND (p.category LIKE ? OR p.category LIKE ?)";
            $params[] = 'home_decor_' . $category . '%';
            $params[] = '%' . $category . '%';
            $types .= "ss";
        } else {
            // Strict prefix match to avoid 'men' matching 'women'
            $query .= " AND (p.category = ? OR p.category LIKE ?)";
            $params[] = $category;
            $params[] = $category . '_%';
            $types .= "ss";
        }
    }

    $query .= " ORDER BY p.created_at DESC LIMIT 50";

    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $score = (int) ($row['views'] ?? 0);
        if (!empty($q)) {
            $score += 50; // Keyword boost
        }
        
        // Safe prefs boost
        if (isset($_SESSION['user_id'])) {
            $prefs_stmt = $conn->prepare("SELECT preferred_categories FROM users WHERE id = ?");
            if ($prefs_stmt) {
                $prefs_stmt->bind_param("i", $_SESSION['user_id']);
                $prefs_stmt->execute();
                $prefs_row = $prefs_stmt->get_result()->fetch_assoc();
                $prefs_cats = json_decode($prefs_row['preferred_categories'] ?? '[]', true) ?: [];
                
                $cat_lower = strtolower($row['category'] ?? '');
                foreach ($prefs_cats as $pc) {
                    if (stripos($cat_lower, strtolower($pc)) !== false) {
                        $score += 30;
                        break;
                    }
                }
                $prefs_stmt->close();
            }
        }
        
        $row['score'] = $score;
        $products[] = $row;
    }

    usort($products, fn($a, $b) => $b['score'] <=> $a['score']);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'category' => $category,
        'query' => $q
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'category' => $_GET['category'] ?? 'all',
        'query' => $_GET['q'] ?? ''
    ]);
} finally {
    if (isset($conn)) $conn->close();
}
?>


