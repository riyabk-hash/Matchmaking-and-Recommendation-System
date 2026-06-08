<?php
require_once 'backend/Models/User.php';
require_once 'backend/Models/Product.php';
require_once 'backend/Services/RecommendationEngine.php';

try {
    $user_id = 1; // Try user ID 1
    $product_id = 8; // Try product ID 8 (pant)
    
    $user = new User($user_id);
    echo "User Loaded: " . $user->username . "\n";
    print_r($user->preferences);
    
    $conn = new mysqli('localhost', 'root', '', 'thrift_store');
    $stmt = $conn->prepare("SELECT p.* FROM products p WHERE p.id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if ($product) {
        echo "Product Loaded: " . $product['title'] . "\n";
        $similar_user_ids = $user->getSimilarUserIds();
        $engine = new RecommendationEngine();
        $breakdown = $engine->calculateDetailedScore($user, $product, $similar_user_ids);
        echo "Breakdown:\n";
        print_r($breakdown);
    } else {
        echo "Product $product_id not found!\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
