<?php
/**
 * Product Model Class
 */
require_once __DIR__ . '/../../config/Database.php';

class Product {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Fetch all active products
     */
    public function getActiveProducts($country_filter = null) {
        $where_clause = "WHERE p.status = 'active' AND u.is_banned = 0";
        if ($country_filter) {
            $where_clause .= " AND (p.country = ? OR p.country IS NULL OR p.country = '')";
        }

        $stmt = $this->conn->prepare("
            SELECT p.*, u.username as seller_username,
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image_path
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            $where_clause
            ORDER BY p.created_at DESC
        ");

        if ($country_filter) {
            $stmt->bind_param("s", $country_filter);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Static helper to tokenize category strings (e.g. "women_shoes" -> ["women", "shoes"])
     */
    /** All valid discovery category slugs (must match preferences + upload form). */
    public static $VALID_CATEGORIES = [
        'women', 'men', 'kids', 'designer', 'home_decor', 'books', 'shoes', 'accessories',
    ];

    /** Category slugs stored as a single token (not split on underscore). */
    private static $COMPOUND_CATEGORIES = ['home_decor'];

    /**
     * Normalize category list from upload/edit (checkbox array or legacy section).
     */
    public static function normalizeCategoryList($input) {
        $list = [];
        if (is_array($input)) {
            $list = $input;
        } elseif (is_string($input) && $input !== '') {
            $list = [$input];
        }
        $valid = [];
        foreach ($list as $cat) {
            $cat = strtolower(trim((string) $cat));
            if (in_array($cat, self::$VALID_CATEGORIES, true)) {
                $valid[] = $cat;
            }
        }
        return array_values(array_unique($valid));
    }

    /** Store multiple categories as comma-separated (e.g. women,home_decor,accessories). */
    public static function encodeCategories(array $categories) {
        $categories = self::normalizeCategoryList($categories);
        return implode(',', $categories);
    }

    /**
     * Parse DB category field: CSV, JSON array, or legacy women_shoes slug.
     */
    public static function parseCategoryField($category_string) {
        $s = trim((string) $category_string);
        if ($s === '') {
            return [];
        }
        if ($s[0] === '[') {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return self::normalizeCategoryList($decoded);
            }
        }
        if (strpos($s, ',') !== false) {
            return self::normalizeCategoryList(explode(',', $s));
        }
        return self::normalizeCategoryList(self::tokenize($s));
    }

    public static function tokenize($category_string) {
        $lower = strtolower(trim((string) $category_string));
        if ($lower === '') {
            return [];
        }
        if (in_array($lower, self::$COMPOUND_CATEGORIES, true)) {
            return [$lower];
        }
        $parts = array_filter(array_map('trim', explode('_', $lower)));
        return array_values(array_unique($parts));
    }

    /**
     * Build tag set used for Jaccard similarity: DB category + item-type hints from title/description.
     * Aligns with preference values (women, men, shoes, accessories, etc.).
     */
    public static function getJaccardTags(array $product) {
        // Explicit seller-selected categories (supports multi-select upload)
        $tags = self::parseCategoryField($product['category'] ?? '');

        $text = strtolower(trim(($product['title'] ?? '') . ' ' . ($product['description'] ?? '')));
        if ($text === '') {
            return $tags;
        }

        // Optional item-type hints from title (do not duplicate seller-selected slugs)
        $item_keyword_map = [
            'shoes'       => ['shoe', 'shoes', 'loafer', 'loafers', 'sneaker', 'sneakers', 'boot', 'boots', 'heel', 'heels', 'sandal', 'sandals', 'footwear', 'slipper'],
            'pants'       => ['pant', 'pants', 'trouser', 'trousers', 'jean', 'jeans', 'denim', 'chino', 'chinos', 'legging', 'leggings'],
            'accessories' => ['bag', 'bags', 'purse', 'belt', 'watch', 'jewelry', 'jewellery', 'scarf', 'hat', 'cap', 'sunglass', 'wallet'],
            'books'       => ['book', 'books', 'novel', 'paperback', 'hardcover'],
        ];

        foreach ($item_keyword_map as $tag => $words) {
            if (in_array($tag, $tags, true)) {
                continue;
            }
            foreach ($words as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return array_values(array_unique($tags));
    }
}
?>
