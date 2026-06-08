<?php
/**
 * User-to-User Matchmaking
 * Calculates similarity based on (Common Preferences) / (Total Preferences)
 * Evaluates Categories, Style, Location, and Price factor overlaps.
 */
header('Content-Type: application/json');
require_once '../config/db_config.php';

$conn = getDBConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $_SESSION['user_id'] ?? null;

if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// 1. Fetch current user preferences
$stmt = $conn->prepare("SELECT id, username, preferred_categories, price_range_min, price_range_max, preferred_location, style_preference_id, style_preference FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

if (!$current_user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$userA = $current_user;

// 2. Fetch all other users
$stmt_all = $conn->prepare("SELECT id, username, preferred_categories, price_range_min, price_range_max, preferred_location, style_preference_id, style_preference FROM users WHERE id != ?");
$stmt_all->bind_param("i", $current_user_id);
$stmt_all->execute();
$other_users = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);

$similar_users = [];

function tokenize_cats($cats_json) {
    $cats = json_decode($cats_json ?? '[]', true) ?: [];
    $tokens = [];
    foreach ($cats as $c) {
        $parts = array_filter(array_map('trim', explode('_', strtolower($c))));
        $tokens = array_merge($tokens, $parts);
    }
    return array_unique($tokens);
}

$catA_tokens = tokenize_cats($userA['preferred_categories']);

foreach ($other_users as $userB) {
    $common = 0;
    $total = 0;
    
    // Categories Match
    $catB_tokens = tokenize_cats($userB['preferred_categories']);
    
    $common_cat = count(array_intersect($catA_tokens, $catB_tokens));
    $total_cat = count(array_unique(array_merge($catA_tokens, $catB_tokens)));
    
    $common += $common_cat;
    $total += $total_cat;
    
    // Style Match
    // Handle both `style_preference` and `style_preference_id` just in case
    $styleA = strtolower(trim($userA['style_preference'] ?? $userA['style_preference_id'] ?? ''));
    $styleB = strtolower(trim($userB['style_preference'] ?? $userB['style_preference_id'] ?? ''));
    
    if ($styleA !== '' || $styleB !== '') {
        if ($styleA !== '' && $styleB !== '' && $styleA === $styleB) {
            $common += 1;
            $total += 1;
        } else {
            $total += ($styleA !== '' ? 1 : 0) + ($styleB !== '' ? 1 : 0);
        }
    }
    
    // Location Match
    $locA = strtolower(trim($userA['preferred_location'] ?? ''));
    $locB = strtolower(trim($userB['preferred_location'] ?? ''));
    if ($locA !== '' || $locB !== '') {
        if ($locA !== '' && $locB !== '' && (strpos($locA, $locB) !== false || strpos($locB, $locA) !== false)) {
            $common += 1;
            $total += 1;
        } else {
            $total += ($locA !== '' ? 1 : 0) + ($locB !== '' ? 1 : 0);
        }
    }
    
    // Price Match
    $minA = (float)($userA['price_range_min'] ?? 0);
    $maxA = (float)($userA['price_range_max'] ?? INF);
    if ($maxA == 0 && $minA == 0) $maxA = 999999; 
    
    $minB = (float)($userB['price_range_min'] ?? 0);
    $maxB = (float)($userB['price_range_max'] ?? INF);
    if ($maxB == 0 && $minB == 0) $maxB = 999999;
    
    $overlap = max(0, min($maxA, $maxB) - max($minA, $minB));
    
    if ($overlap > 0 || ($minA == $minB && $maxA == $maxB && $minA > 0)) {
        $common += 1;
        $total += 1;
    } else {
        $total += 2; // disjoint bounds meaning distinct preferences
    }
    
    // Formula: Similarity = Common / Total
    $similarity = $total > 0 ? $common / $total : 0;
    
    if ($similarity > 0) {
        $similar_users[] = [
            'id' => $userB['id'],
            'username' => $userB['username'],
            'score' => round($similarity * 100),
            'common_traits' => $common,
            'total_traits' => $total
        ];
    }
}

// 3. Sort Users by Similarity score DESC
usort($similar_users, fn($a, $b) => $b['score'] <=> $a['score']);
// Top 5 similar users
$similar_users = array_slice($similar_users, 0, 5);

echo json_encode([
    'success' => true,
    'similar_users' => $similar_users,
    'formula' => 'Jaccard (Intersection over Union) equivalent implemented across attributes.',
    'attributes' => ['Categories', 'Style', 'Location', 'Price']
]);

$conn->close();
?>
