<?php
/**
 * LOCATION BACKFILL UTILITY
 * This script updates existing users and products with GPS coordinates (lat/lng), 
 * city, and country based on their text-based address fields.
 * 
 * RUNNING THIS SCRIPT: 
 * Open this file in your browser: http://localhost/thrift/backend/backfill_locations.php
 */

require_once '../config/db_config.php';
$conn = getDBConnection();

$apiKey = 'AIzaSyCN426ck_UUlthVZqtl75_XdtVBs9IvwrU';

echo "<h1>Location Backfill Started</h1>";
flush();

// 1. BACKFILL USERS
echo "<h2>1. Processing Users...</h2>";
$users = $conn->query("SELECT id, address FROM users WHERE (country IS NULL OR country = '') AND (address IS NOT NULL AND address != '')");

while ($user = $users->fetch_assoc()) {
    $data = geocodeAddress($user['address'], $apiKey);
    if ($data) {
        $stmt = $conn->prepare("UPDATE users SET country = ?, city = ?, latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $data['country'], $data['city'], $data['lat'], $data['lng'], $user['id']);
        $stmt->execute();
        echo "Updated User ID {$user['id']}: {$data['city']}, {$data['country']}<br>";
    } else {
        echo "Failed to geocode User ID {$user['id']}: {$user['address']}<br>";
    }
    flush();
}

// 2. BACKFILL PRODUCTS
echo "<h2>2. Processing Products...</h2>";
$products = $conn->query("SELECT id, location FROM products WHERE (country IS NULL OR country = '') AND (location IS NOT NULL AND location != '')");

while ($product = $products->fetch_assoc()) {
    $data = geocodeAddress($product['location'], $apiKey);
    if ($data) {
        $stmt = $conn->prepare("UPDATE products SET country = ?, city = ?, latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $data['country'], $data['city'], $data['lat'], $data['lng'], $product['id']);
        $stmt->execute();
        echo "Updated Product ID {$product['id']}: {$data['city']}, {$data['country']}<br>";
    } else {
        echo "Failed to geocode Product ID {$product['id']}: {$product['location']}<br>";
    }
    flush();
}

echo "<h2>Backfill Complete!</h2>";

/**
 * Helper function to call Google Geocoding API
 */
function geocodeAddress($address, $key) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $key;
    $response = file_get_contents($url);
    $json = json_decode($response, true);

    if ($json['status'] === 'OK') {
        $result = $json['results'][0];
        $lat = $result['geometry']['location']['lat'];
        $lng = $result['geometry']['location']['lng'];
        
        $city = '';
        $country = '';
        
        foreach ($result['address_components'] as $component) {
            if (in_array('locality', $component['types'])) {
                $city = $component['long_name'];
            }
            if (in_array('country', $component['types'])) {
                $country = $component['long_name'];
            }
        }
        
        return [
            'lat' => $lat,
            'lng' => $lng,
            'city' => $city,
            'country' => $country
        ];
    }
    return null;
}
?>
