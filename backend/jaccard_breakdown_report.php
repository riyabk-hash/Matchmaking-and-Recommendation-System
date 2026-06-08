<?php
/**
 * One-off report: Jaccard breakdown for pant/loafer vs user preferences.
 * Open in browser: http://localhost/thrift/backend/jaccard_breakdown_report.php
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/Models/Product.php';
require_once __DIR__ . '/Services/RecommendationEngine.php';

$conn = getDBConnection();
$engine = new RecommendationEngine();

// Users with women + home_decor + accessories (or show all logged-in candidates)
$users = $conn->query("
    SELECT id, username, preferred_categories, style_preference
    FROM users
    ORDER BY id ASC
")->fetch_all(MYSQLI_ASSOC);

$products = $conn->query("
    SELECT id, title, category, description, price
    FROM products
    WHERE status = 'active'
    AND (LOWER(title) LIKE '%pant%' OR LOWER(title) LIKE '%loafer%'
         OR LOWER(title) LIKE '%shoe%' OR LOWER(category) LIKE '%pant%'
         OR LOWER(category) LIKE '%shoe%')
    ORDER BY id ASC
")->fetch_all(MYSQLI_ASSOC);

if (empty($products)) {
    $products = $conn->query("
        SELECT id, title, category, description, price
        FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
}

function renderJaccardTable($engine, $userRow, $products) {
    $cats = json_decode($userRow['preferred_categories'] ?? '[]', true) ?: [];
    $userTokens = $engine->buildUserTokens($cats);

    echo '<h2>User #' . (int)$userRow['id'] . ' — ' . htmlspecialchars($userRow['username']) . '</h2>';
    echo '<p><strong>Style preference (not in Jaccard):</strong> ' . htmlspecialchars($userRow['style_preference'] ?? '') . '</p>';
    echo '<p><strong>Your preference categories (raw):</strong> ' . htmlspecialchars(json_encode($cats)) . '</p>';
    echo '<p><strong>Set A — user_tokens:</strong> <code>' . htmlspecialchars(implode(', ', $userTokens) ?: '(empty)') . '</code></p>';

    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin-bottom:32px;width:100%;max-width:900px;">';
    echo '<tr style="background:#f5f5f5;"><th>Product</th><th>Category (DB)</th><th>Set B — product_tags</th><th>Shared (A ∩ B)</th><th>Union (A ∪ B)</th><th>Jaccard</th></tr>';

    foreach ($products as $p) {
        $j = $engine->computeJaccard($cats, $p);
        $inter = $j['intersection'];
        $union = $j['union'];
        $pct = $j['percent'];
        $frac = count($union) > 0 ? count($inter) . ' ÷ ' . count($union) : '0';
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($p['title']) . '</strong><br><small>ID ' . (int)$p['id'] . '</small></td>';
        echo '<td><code>' . htmlspecialchars($p['category'] ?? '') . '</code></td>';
        echo '<td><code>' . htmlspecialchars(implode(', ', $j['product_tags']) ?: '(empty)') . '</code></td>';
        echo '<td><code>' . htmlspecialchars(implode(', ', $inter) ?: '—') . '</code></td>';
        echo '<td><code>' . htmlspecialchars(implode(', ', $union) ?: '—') . '</code> (' . count($union) . ' tags)</td>';
        echo '<td><strong>' . $pct . '%</strong><br><small>' . $frac . ' = |A∩B|/|A∪B|</small></td>';
        echo '</tr>';
    }
    echo '</table>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Jaccard Breakdown Report</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 24px; max-width: 960px; margin: 0 auto; }
        h1 { font-size: 1.4rem; }
        code { background: #eee; padding: 2px 6px; border-radius: 4px; }
        .note { background: #fff8e6; padding: 12px; border-radius: 8px; margin-bottom: 24px; }
    </style>
</head>
<body>
    <h1>Jaccard breakdown (your database)</h1>
    <div class="note">
        <strong>Formula:</strong> Jaccard = |A ∩ B| / |A ∪ B| &nbsp;•&nbsp; Max = <strong>100%</strong> when A and B are the same set.<br>
        <strong>A</strong> = tags from your preference checkboxes. <strong>B</strong> = product category + title/description keywords.
    </div>
<?php
$shown = 0;
foreach ($users as $u) {
    $cats = json_decode($u['preferred_categories'] ?? '[]', true) ?: [];
    $hasWomen = in_array('women', $cats, true);
    $hasHome = in_array('home_decor', $cats, true);
    $hasAcc = in_array('accessories', $cats, true);
    if ($hasWomen && $hasHome && $hasAcc) {
        renderJaccardTable($engine, $u, $products);
        $shown++;
    }
}

if ($shown === 0) {
    echo '<p><em>No user found with exactly women + home_decor + accessories. Showing all users:</em></p>';
    foreach ($users as $u) {
        renderJaccardTable($engine, $u, $products);
    }
}

if (empty($products)) {
    echo '<p>No products found.</p>';
} else {
    echo '<h3>Products included in this report</h3><ul>';
    foreach ($products as $p) {
        echo '<li>' . htmlspecialchars($p['title']) . ' — <code>' . htmlspecialchars($p['category'] ?? '') . '</code></li>';
    }
    echo '</ul>';
}
?>
</body>
</html>
