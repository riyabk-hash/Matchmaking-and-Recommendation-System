<?php
/**
 * User Model Class
 */
require_once __DIR__ . '/../../config/Database.php';

class User {
    private $conn;
    public $id;
    public $username;
    public $email;
    public $preferences;

    public function __construct($user_id = null) {
        $this->conn = Database::getInstance()->getConnection();
        if ($user_id) {
            $this->load($user_id);
        }
    }

    /**
     * Load user data from DB
     */
    public function load($id) {
        $stmt = $this->conn->prepare("SELECT id, username, email, preferred_categories, price_range_min, price_range_max, preferred_location, country, city, latitude, longitude, style_preference FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data) {
            $this->id = $data['id'];
            $this->username = $data['username'];
            $this->email = $data['email'];
            
            // Map preferences
            $this->preferences = [
                'categories' => json_decode($data['preferred_categories'] ?? '[]', true) ?: [],
                'price_min' => (float)($data['price_range_min'] ?? 0),
                'price_max' => (float)($data['price_range_max'] ?? 999999),
                'location' => strtolower(trim($data['preferred_location'] ?? '')),
                'country' => strtolower(trim($data['country'] ?? '')),
                'city' => strtolower(trim($data['city'] ?? '')),
                'latitude' => $data['latitude'] ? (float)$data['latitude'] : null,
                'longitude' => $data['longitude'] ? (float)$data['longitude'] : null,
                'style' => strtolower(trim($data['style_preference'] ?? ''))
            ];
            return true;
        }
        return false;
    }

    /**
     * Get similar users based on shared preferences (Simulates Collaborative Filtering)
     */
    public function getSimilarUserIds() {
        $stmt_users = $this->conn->prepare("SELECT id, preferred_categories, style_preference FROM users WHERE id != ?");
        $stmt_users->bind_param("i", $this->id);
        $stmt_users->execute();
        $all_other_users = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);

        $similar_ids = [];
        $buyer_tokens = $this->tokenize($this->preferences['categories']);

        foreach ($all_other_users as $other) {
            $common = 0; $total = 0;
            $other_tokens = $this->tokenize(json_decode($other['preferred_categories'] ?? '[]', true));
            
            $common += count(array_intersect($buyer_tokens, $other_tokens));
            $total += count(array_unique(array_merge($buyer_tokens, $other_tokens)));

            $similarity = ($total > 0) ? ($common / $total) : 0;
            if ($similarity >= 0.25) {
                $similar_ids[] = $other['id'];
            }
        }
        return $similar_ids;
    }

    private function tokenize($categories) {
        $tokens = [];
        foreach ((array)$categories as $cat) {
            $parts = array_filter(array_map('trim', explode('_', strtolower($cat))));
            $tokens = array_merge($tokens, $parts);
        }
        return array_unique($tokens);
    }
}
?>
