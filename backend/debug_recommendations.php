<?php
/**
 * Debug: Test the PRIORITY-BASED recommendation algorithm
 * Run: http://localhost/thrift/backend/debug_recommendations.php
 * (Must be logged in first!)
 */

header('Content-Type: text/html');

echo "<h1>🔍 Priority-Based Matchmaking Debug</h1>";

$conn = new mysqli("localhost", "root", "", "thrift_store");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red'>⚠️ You are not logged in!</p>";
    echo "<p>Please <a href='../login.html'>login</a> first, then come back.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current user's profile
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

echo "<h2>👤 YOUR Profile</h2>";
echo "<div style='background:#e3f2fd; padding:15px; border-radius:8px; border-left:4px solid #2196F3;'>";
echo "<p><strong>Username:</strong> " . $current_user['username'] . "</p>";
echo "<p><strong>Location:</strong> " . ($current_user['preferred_location'] ?: 'NOT SET') . "</p>";

$preferred_categories = json_decode($current_user['preferred_categories'] ?? '[]', true) ?? [];
echo "<p><strong>Preferred Categories:</strong> " . implode(", ", $preferred_categories) . "</p>";
echo "<p><strong>Style:</strong> " . ($current_user['style_preference'] ?: 'NOT SET') . "</p>";
echo "<p><strong>Size:</strong> " . ($current_user['size_preference'] ?: 'NOT SET') . "</p>";
echo "<p><strong>Price Range:</strong> $" . $current_user['price_range_min'] . " - $" . $current_user['price_range_max'] . "</p>";
echo "</div>";

// Determine user's tokens
function tokenize_categories($categories) {
    $tokens = [];
    foreach ((array)$categories as $cat) {
        $parts = array_filter(array_map('trim', explode('_', strtolower($cat))));
        $tokens = array_merge($tokens, $parts);
    }
    return array_unique($tokens);
}

function jaccard_similarity($set1, $set2) {
    $intersection = count(array_intersect($set1, $set2));
    $union = count(array_unique(array_merge($set1, $set2)));
    return $union > 0 ? $intersection / $union : 0;
}

function location_similarity($loc1, $loc2) {
    if (empty($loc1) || empty($loc2)) return 0;
    $l1 = strtolower($loc1);
    $l2 = strtolower($loc2);
    if ($l1 === $l2) return 1;
    return (stripos($l1, $loc2) !== false || stripos($l2, $loc1) !== false) ? 0.5 : 0;
}

function price_fit_score($b_min, $b_max, $p_price) {
    if ($p_price >= $b_min && $p_price <= $b_max) return 1.0;
    $variance = $p_price < $b_min ? ($b_min - $p_price) / max(1, $b_min) : ($p_price - $b_max) / max(1, $b_max);
    return max(0, exp(-5 * $variance));
}

$user_tokens = tokenize_categories($preferred_categories);
echo "<p><strong>Your Tokens (dynamic):</strong> " . implode(", ", $user_tokens) . "</p>";

echo "<hr>";
echo "<h2>📋 MATH COMPATIBILITY RULES</h2>";
echo "<div style='background:#fff3e0; padding:15px; border-radius:8px;'>";
echo "<p>Standardized Hybrid Matchmaking Model (Equation 3):</p>";
echo "<ol>";
echo "<li><strong>Location Score (S_LSC - 60%):</strong> Proximity-based exponential decay</li>";
echo "<li><strong>Category Score (J - 20%):</strong> Jaccard Similarity index of tokenized tags</li>";
echo "<li><strong>Price Score (S_price - 15%):</strong> Budget range compatibility</li>";
echo "<li><strong>Style Score (S_style - 5%):</strong> Aesthetic alignment</li>";
echo "</ol>";
echo "<p><em><strong>BETA BOOST:</strong> +10% if the seller is a 'Similar Curator'</em></p>";
echo "</div>";

echo "<hr>";
echo "<h2>📦 Testing All Active Products</h2>";

$user_location = strtolower(trim($current_user['preferred_location'] ?? ''));
$user_price_min = floatval($current_user['price_range_min']);
$user_price_max = floatval($current_user['price_range_max']);
$user_style = strtolower($current_user['style_preference'] ?? '');

$compatible_count = 0;

$all_products_query = $conn->query("SELECT p.*, u.username as seller FROM products p JOIN users u ON p.seller_id = u.id WHERE p.status = 'active' ORDER BY p.created_at DESC");

echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%;'>";
echo "<tr style='background:#f5f5f5;'><th>Product</th><th>Tags</th><th>S_LSC (60%)</th><th>J (20%)</th><th>S_price (15%)</th><th>S_style (5%)</th><th>Total Score</th><th>Result</th></tr>";

while ($p = $all_products_query->fetch_assoc()) {
    $p_tags = tokenize_categories([$p['category']]);
    $p_price = floatval($p['price']);
    $p_style = strtolower(trim($p['style'] ?? ''));
    $p_loc = strtolower(trim($p['location'] ?? ''));
    
    // Calculate raw scores (0-1)
    $raw_lsc = location_similarity($user_location, $p_loc);
    $raw_j = jaccard_similarity($user_tokens, $p_tags);
    $raw_price = price_fit_score($user_price_min, $user_price_max, $p_price);
    $raw_style = (!empty($user_style) && $user_style === $p_style ? 1.0 : 0);
    
    // Apply Weights
    $s_lsc = $raw_lsc * 0.60;
    $j_score = $raw_j * 0.20;
    $s_price = $raw_price * 0.15;
    $s_style = $raw_style * 0.05;
    
    $total_score = $s_lsc + $j_score + $s_price + $s_style;
    $total_pct = round($total_score * 100, 1);

    
    if ($total_pct > 15) {
        $result_text = "<strong><span style='color:green'>✅ RECOMMENDED</span></strong>";
        $compatible_count++;
    } else {
        $result_text = "<span style='color:red'>❌ REJECTED</span>";
    }
    
    echo "<tr>";
    echo "<td>" . $p['title'] . " (\$" . $p['price'] . ") - by " . $p['seller'] . "</td>";
    echo "<td>" . implode(", ", $p_tags) . "</td>";
    echo "<td>" . round($s_lsc * 100, 1) . "%</td>";
    echo "<td>" . round($j_score * 100, 1) . "%</td>";
    echo "<td>" . round($s_price * 100, 1) . "%</td>";
    echo "<td>" . round($s_style * 100, 1) . "%</td>";

    echo "<td><strong>{$total_pct}%</strong></td>";
    echo "<td>$result_text</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

if ($compatible_count == 0) {
    echo "<p style='color:orange; font-size:18px;'>⚠️ NO RECOMMENDATIONS YET</p>";
    echo "<p><strong>Reason:</strong> Your preferences don't match closely with anything.</p>";
} else {
    echo "<p style='color:green; font-size:18px;'>✅ Found {$compatible_count} products matching your preferences mathematically!</p>";
}

echo "<hr>";
echo "<h2>💡 Summary</h2>";
echo "<ul>";
echo "<li><strong>Your Tokens:</strong> " . implode(", ", $user_tokens) . "</li>";
echo "<li><strong>Compatible Products Found:</strong> $compatible_count</li>";
echo "</ul>";

$conn->close();
?>

