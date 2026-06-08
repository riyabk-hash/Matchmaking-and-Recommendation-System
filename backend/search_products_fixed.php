<?php
/**
 * FIXED RESPONSIVE SEARCH WITH PREFERENCE COMPATIBILITY RANKING
 * Added: Error handling, LIKE fallback, relaxed filters, uses get_user_preferences.php
 * ?q=query&limit=10
 */

header('Content-Type: application/json');
require_once '../config/db_config.php';

try {
    $conn = getDBConnection();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $q = trim($_GET['q'] ?? '');
    $limit = (int) ($_GET['limit'] ?? 20);

    if (empty($q)) {
        // Empty: Popular products
        $stmt = $conn->prepare("
            SELECT p.*, u.username as seller_name,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
            FROM products p JOIN users u ON p.seller_id = u.id 
            WHERE p.status = 'active' ORDER BY p.views DESC LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Get user preferences via dedicated endpoint
        $prefs = ['categories' => [], 'style' => '', 'location' => '', 'price_min' => 0, 'price_max' => PHP_FLOAT_MAX];
        $user_id = $_SESSION['user_id'] ?? 0;
        if ($user_id) {
            $prefs_resp = file_get_contents('http://localhost/thrift/backend/get_user_preferences.php');
            $prefs_data = json_decode($prefs_resp, true);
            if ($prefs_data && $prefs_data['logged_in']) {
                $prefs = [
                    'categories' => $prefs_data['preferences']['preferred_categories'] ?? [],
                    'style' => strtolower(trim($prefs_data['preferences']['style_preference'] ?? '')),
                    'location' => strtolower(trim($prefs_data['preferences']['preferred_location'] ?? '')),
                    'price_min' => (float) ($prefs_data['preferences']['price_range_min'] ?? 0),
                    'price_max' => (float) ($prefs_data['preferences']['price_range_max'] ?? PHP_FLOAT_MAX)
                ];
            }
        }

        // Try FULLTEXT first
        $raw_products = [];
        $stmt = $conn->prepare("
            SELECT p.*, u.username as seller_name,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) as image_path,
            MATCH(p.title, p.description, p.category) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
            FROM products p JOIN users u ON p.seller_id = u.id 
            WHERE MATCH(p.title, p.description, p.category) AGAINST(? IN NATURAL LANGUAGE MODE)
            AND p.status = 'active' 
            ORDER BY relevance DESC, p.created_at DESC 
            LIMIT 50
        ");
        if ($stmt) {
            $stmt->bind_param("ss", $q, $q);
            $stmt->execute();
            $raw_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // Fallback to LIKE if FULLTEXT empty or failed
        if (empty($raw_products)) {
            $term = '%' . $q . '%';
            $stmt = $conn->prepare("
                SELECT p.*, u.username as seller_name,
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) as image_path,
                1 as relevance
                FROM products p JOIN users u ON p.seller_id = u.id 
                WHERE (p.title LIKE ? OR p.description LIKE ? OR p.category LIKE ?)
                AND p.status = 'active'
                ORDER BY p.created_at DESC 
                LIMIT 50
            ");
            $stmt->bind_param("sss", $term, $term, $term);
            $stmt->execute();
            $raw_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        // Compute compatibility score and rank
        $products = [];
        foreach ($raw_products as $p) {
            $p_price = (float) $p['price'];
            $p_tags = array_filter(array_map('trim', explode('_', strtolower($p['category'] ?? ''))));
            $p_sections = [strtolower(reset($p_tags)) ?? ''];
            $p_style = strtolower(trim($p['style'] ?? ''));
            $p_loc = strtolower(trim($p['location'] ?? ''));

            // Vector compatibility scores (0-1)
            $section_compat = count(array_intersect($prefs['categories'], $p_sections)) / max(1, count($prefs['categories']) + 1);
            $cat_compat = count(array_intersect($prefs['categories'], $p_tags)) / max(1, count(array_unique(array_merge($prefs['categories'], $p_tags))) + 1);
            $style_compat = ($prefs['style'] === $p_style) ? 1.0 : 0.0;
            $loc_compat = (strpos(strtolower($prefs['location']), $p_loc) !== false || strpos($p_loc, strtolower($prefs['location'])) !== false) ? 1.0 : 0.0;
            $price_compat = 1.0;  // Relaxed - no strict price filter

            $compat_score = $section_compat * 0.4 + $cat_compat * 0.3 + $style_compat * 0.15 + $loc_compat * 0.1 + $price_compat * 0.05;
            $relevance = (float) ($p['relevance'] ?? 1.0);
            $p['total_score'] = $relevance * 0.6 + $compat_score * 0.4;
            $p['search_method'] = !empty($p['relevance']) && $p['relevance'] > 0 ? 'FULLTEXT' : 'LIKE';

            $products[] = $p;
        }

        // Sort by total score DESC
        usort($products, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $products = array_slice($products, 0, $limit);
    }

    echo json_encode([
        'success' => true,
        'query' => $q,
        'products' => $products,
        'prefs_used' => !empty($prefs['categories']),
        'count' => count($products),
        'search_method_used' => isset($products[0]) ? $products[0]['search_method'] ?? 'popular' : 'none'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'query' => $q ?? ''
    ]);
} finally {
    if (isset($conn)) $conn->close();
}
?>

