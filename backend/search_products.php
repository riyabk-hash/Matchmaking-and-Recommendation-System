<?php
/**
 * FIXED RESPONSIVE SEARCH WITH PREFERENCE COMPATIBILITY RANKING
 * Added: Error handling, LIKE fallback, relaxed filters, uses get_user_preferences.php
 * ?q=query&limit=10
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Product.php';
require_once __DIR__ . '/Services/RecommendationEngine.php';

try {
    $conn = Database::getInstance()->getConnection();
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
        $user_id = $_SESSION['user_id'] ?? 0;
        $user = $user_id ? new User($user_id) : null;
        $prefs_available = ($user && !empty($user->preferences['categories']));


        // 1. Try FULLTEXT
        $raw_products = [];
        $product_ids = [];

        try {
            $stmt_ft = $conn->prepare("
                SELECT p.*, u.username as seller_name,
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) as image_path,
                MATCH(p.title, p.description, p.category) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM products p JOIN users u ON p.seller_id = u.id
                WHERE MATCH(p.title, p.description, p.category) AGAINST(? IN NATURAL LANGUAGE MODE) 
                AND p.status = 'active' 
                LIMIT 50
            ");
            if ($stmt_ft) {
                $stmt_ft->bind_param("ss", $q, $q);
                $stmt_ft->execute();
                $ft_results = $stmt_ft->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach($ft_results as $row) {
                    if (!isset($product_ids[$row['id']])) {
                        $raw_products[] = $row;
                        $product_ids[$row['id']] = true;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore FULLTEXT errors (e.g., missing index) and just rely on the LIKE fallback
        }

        // 2. ALWAYS run LIKE to catch substrings like 'shoes' inside 'women_shoes' or 'hoodie'
        $term = '%' . $q . '%';
        $stmt_like = $conn->prepare("
            SELECT p.*, u.username as seller_name,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND is_primary = 1 LIMIT 1) as image_path,
            0.5 as relevance
            FROM products p JOIN users u ON p.seller_id = u.id 
            WHERE (p.title LIKE ? OR p.description LIKE ? OR p.category LIKE ?)
            AND p.status = 'active'
            LIMIT 50
        ");
        if ($stmt_like) {
            $stmt_like->bind_param("sss", $term, $term, $term);
            $stmt_like->execute();
            $like_results = $stmt_like->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach($like_results as $row) {
                if (!isset($product_ids[$row['id']])) {
                    $raw_products[] = $row;
                    $product_ids[$row['id']] = true;
                }
            }
        }

        // Initialize Recommendation Engine
        $engine = new RecommendationEngine();
        $similar_user_ids = $user ? $user->getSimilarUserIds() : [];


        // Compute compatibility score and rank
        $products = [];
        foreach ($raw_products as $p) {
            $relevance = (float) ($p['relevance'] ?? 1.0);
            
            // Calculate standardized compatibility score using RecommendationEngine
            // If not logged in, we use a default high compatibility or just relevance
            $compat_score = 50; // Default 50% match
            if ($user) {
                $score = $engine->calculateScore($user, $p, $similar_user_ids);
                if ($score !== null) {
                    $compat_score = $score;
                }
                $jaccard = $engine->computeJaccard($user->preferences['categories'], $p);
                $p['jaccard_score'] = $jaccard['percent'];
                $p['jaccard_breakdown'] = [
                    'shared_tags' => $jaccard['intersection'],
                    'user_tokens' => $jaccard['user_tokens'],
                    'product_tags' => $jaccard['product_tags'],
                ];
            }

            // Final Search Rank: 70% Search Relevance + 30% User Compatibility
            // This ensures search intent is prioritized while still being personalized
            $p['total_score'] = ($relevance * 70) + ($compat_score * 0.30);
            $p['match_score'] = $compat_score; // For UI display
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
        'prefs_used' => $prefs_available,
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


