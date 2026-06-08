<?php
/**
 * Interaction Model Class
 * Handles Likes, Saves, and Reports.
 */
require_once __DIR__ . '/../../config/Database.php';

class Interaction {
    protected $conn;
    protected $user_id;
    protected $product_id;

    public function __construct($user_id, $product_id) {
        $this->conn = Database::getInstance()->getConnection();
        $this->user_id = (int)$user_id;
        $this->product_id = (int)$product_id;
    }

    /**
     * Toggles a 'Like' on a product
     */
    public function toggleLike() {
        $check = $this->conn->prepare("SELECT 1 FROM product_likes WHERE user_id = ? AND product_id = ?");
        $check->bind_param("ii", $this->user_id, $this->product_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $stmt = $this->conn->prepare("DELETE FROM product_likes WHERE user_id = ? AND product_id = ?");
            $this->conn->query("UPDATE products SET likes_count = likes_count - 1 WHERE id = {$this->product_id}");
            $action = 'unliked';
        } else {
            $stmt = $this->conn->prepare("INSERT INTO product_likes (user_id, product_id) VALUES (?, ?)");
            $this->conn->query("UPDATE products SET likes_count = likes_count + 1 WHERE id = {$this->product_id}");
            $action = 'liked';
        }
        $stmt->bind_param("ii", $this->user_id, $this->product_id);
        $stmt->execute();

        $count_res = $this->conn->query("SELECT likes_count FROM products WHERE id = {$this->product_id}");
        $new_count = $count_res->fetch_assoc()['likes_count'];

        return ['action' => $action, 'new_count' => $new_count];
    }

    /**
     * Toggles a 'Save' on a product
     */
    public function toggleSave() {
        $check = $this->conn->prepare("SELECT 1 FROM product_saves WHERE user_id = ? AND product_id = ?");
        $check->bind_param("ii", $this->user_id, $this->product_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $stmt = $this->conn->prepare("DELETE FROM product_saves WHERE user_id = ? AND product_id = ?");
            $action = 'unsaved';
        } else {
            $stmt = $this->conn->prepare("INSERT INTO product_saves (user_id, product_id) VALUES (?, ?)");
            $action = 'saved';
        }
        $stmt->bind_param("ii", $this->user_id, $this->product_id);
        $stmt->execute();

        return ['action' => $action];
    }

    /**
     * Submits a report for a product
     */
    public function report($reason) {
        // Fetch seller info
        $s_stmt = $this->conn->prepare("SELECT seller_id FROM products WHERE id = ?");
        $s_stmt->bind_param("i", $this->product_id);
        $s_stmt->execute();
        $product_info = $s_stmt->get_result()->fetch_assoc();
        
        if (!$product_info) throw new Exception("Product not found");
        $seller_id = $product_info['seller_id'];

        // Check for duplicates
        $check = $this->conn->prepare("SELECT 1 FROM reports WHERE reporter_id = ? AND product_id = ? AND report_type = ?");
        $check->bind_param("iis", $this->user_id, $this->product_id, $reason);
        $check->execute();
        if ($check->get_result()->num_rows > 0) throw new Exception("Already reported.");

        // Insert report
        $stmt = $this->conn->prepare("INSERT INTO reports (reported_user_id, reporter_id, product_id, report_type, report_reason) VALUES (?, ?, ?, ?, ?)");
        $report_text = "Flagged as " . $reason . " by community member (OO).";
        $stmt->bind_param("iiiss", $seller_id, $this->user_id, $this->product_id, $reason, $report_text);
        $stmt->execute();

        // Check for auto-ban (Threshold: 5 unique reports)
        $this->checkAutoBan($seller_id, $reason);

        return true;
    }

    private function checkAutoBan($seller_id, $reason) {
        $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT reporter_id) as count FROM reports WHERE product_id = ? AND report_type = ?");
        $stmt->bind_param("is", $this->product_id, $reason);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];

        if ($count >= 5) {
            $this->conn->query("UPDATE users SET is_banned = 1 WHERE id = $seller_id");
            $this->conn->query("UPDATE products SET status = 'removed' WHERE seller_id = $seller_id");
        }
    }
}
?>
