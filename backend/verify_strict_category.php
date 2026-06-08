<?php
require_once '../config/db_config.php';
$conn = getDBConnection();

// Mock session for User 1 (women, shoes)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;

// Capture output
ob_start();
include 'get_recommended_products.php';
$output = ob_get_clean();

$data = json_decode($output, true);

echo "--- STRICT CATEGORY FILTER TEST (User 1) ---\n";
echo "Buyer Categories: women, shoes\n";

$found_lamp = false;
$found_loafer = false;

if (isset($data['products'])) {
    foreach ($data['products'] as $p) {
        if ($p['id'] == 2) $found_lamp = true; // Boho Lamp
        if ($p['id'] == 1) $found_loafer = true; // Loafer
    }
}

if (!$found_lamp) {
    echo "SUCCESS: Boho Lamp (Home Decor) was SKIPPED.\n";
} else {
    echo "FAILURE: Boho Lamp was still recommended.\n";
}

if ($found_loafer) {
    echo "SUCCESS: Loafer (Women Shoes) was RECOMMENDED.\n";
} else {
    echo "FAILURE: Loafer was missed.\n";
}
?>
