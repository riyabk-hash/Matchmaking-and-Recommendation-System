<?php
/**
 * REFACTORED OO RECOMMENDATION SYSTEM
 * Uses Domain Models and Service Layer classes.
 */

header('Content-Type: application/json');

// Include Models and Services
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Product.php';
require_once __DIR__ . '/Services/RecommendationEngine.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => true,
        'products' => [],
        'message' => 'Login for personalized matchmaking',
        'architecture' => 'Object-Oriented (OO) v1'
    ]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // 1. Initialize Objects
    $user = new User($user_id);
    if (!$user->id) {
        throw new Exception("User data could not be loaded.");
    }

    $productModel = new Product();
    $engine = new RecommendationEngine();

    // 2. Fetch Data
    // We apply country filter if user has one set
    $products = $productModel->getActiveProducts($user->preferences['country']);
    $similar_user_ids = $user->getSimilarUserIds();

    // 3. Process Recommendations
    $recommendations = [];
    foreach ($products as $product) {
        // Skip own product in recommendations
        if ($product['seller_id'] == $user_id) {
            continue;
        }

        $jaccard = $engine->computeJaccard($user->preferences['categories'], $product);

        // Jaccard implementation: must share at least one preference tag with the product
        if (count($jaccard['intersection']) === 0) {
            continue;
        }

        $score = $engine->calculateScore($user, $product, $similar_user_ids);

        if ($score !== null && $score > 10) {
            $recommendations[] = array_merge($product, [
                'match_score' => $score,
                'jaccard_score' => $jaccard['percent'],
                'jaccard_index' => round($jaccard['index'], 4),
                'jaccard_breakdown' => [
                    'formula' => $jaccard['formula'],
                    'user_tokens' => $jaccard['user_tokens'],
                    'product_tags' => $jaccard['product_tags'],
                    'shared_tags' => $jaccard['intersection'],
                ],
                'match_reasons' => this_get_reasons($score, $jaccard, $product, $similar_user_ids),
            ]);
        }
    }

    // 4. Sort by Jaccard (primary), then overall personalized match (secondary)
    usort($recommendations, function ($a, $b) {
        $byJaccard = $b['jaccard_score'] <=> $a['jaccard_score'];
        return $byJaccard !== 0 ? $byJaccard : ($b['match_score'] <=> $a['match_score']);
    });
    $recommendations = array_slice($recommendations, 0, 20);

    echo json_encode([
        'success' => true,
        'products' => $recommendations,
        'architecture' => 'Object-Oriented (OO) v1',
        'buyer_summary' => $user->preferences
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Helper to maintain legacy reason logic for UI compatibility
 */
function this_get_reasons($score, $jaccard, $product, $similar_user_ids) {
    $reasons = ['local discovery (within 30km)', 'within your budget'];
    if (!empty($jaccard['intersection'])) {
        $reasons[] = 'Jaccard match on: ' . implode(', ', $jaccard['intersection']);
    }
    if ($jaccard['percent'] >= 50) {
        $reasons[] = 'strong category overlap (Jaccard)';
    }
    if ($score > 40) {
        $reasons[] = 'strong overall personalized match';
    }
    if (in_array($product['seller_id'], $similar_user_ids)) {
        $reasons[] = 'similar curator style';
    }
    return $reasons;
}
