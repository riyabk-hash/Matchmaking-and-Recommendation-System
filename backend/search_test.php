<html>
<head><title>Search Debug</title></head>
<body>
<h1>Search Debug</h1>
<pre>
<?php
require_once 'config/db_config.php';
$conn = getDBConnection();
$q = $_GET['q'] ?? 'shoes';
echo "Query: $q\n";

$stmt = $conn->prepare("SELECT p.id, p.title, p.category, MATCH(p.title, p.description, p.category) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
FROM products p WHERE p.status = 'active'");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

echo "Results:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>
</pre>
<a href="search_test.php?q=shoes">Test shoes</a>
</body>
</html>
