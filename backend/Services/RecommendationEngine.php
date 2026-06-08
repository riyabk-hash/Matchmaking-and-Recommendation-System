<?php
/**
 * Recommendation Engine Service Class
 * Handles the logic for matching users with products.
 */
class RecommendationEngine {
    
    /**
     * Compute a final match score between a user and a product
     * FORMULA: S_total = (0.60 * S_LSC) + (0.20 * J) + (0.15 * S_price) + (0.05 * S_style) + Beta
     * 
     * S_LSC: Location Score (Proximity)
     * J: Jaccard Similarity (Category overlap)
     * S_price: Price Fit (Budget compatibility)
     * S_style: Style Match (Aesthetic alignment)
     * Beta: Similar User Boost
     */
    public function calculateScore($user, $product, $similar_user_ids = []) {
        $detail = $this->calculateDetailedScore($user, $product, $similar_user_ids);
        if (!$detail['is_eligible']) {
            return null;
        }
        return $detail['overall_score'];
    }

    /**
     * Compute a final match score breakdown between a user and a product
     */
    public function calculateDetailedScore($user, $product, $similar_user_ids = []) {
        $product_price = (float) $product['price'];
        $jaccard = $this->computeJaccard($user->preferences['categories'], $product);
        $jaccard_raw = $jaccard['index'];
        $product_tags = $jaccard['product_tags'];
        $product_style = strtolower(trim($product['style'] ?? ''));
        
        // 1. Proximity Check
        $distance = $this->haversineDistance(
            $user->preferences['latitude'], 
            $user->preferences['longitude'], 
            $product['latitude'], 
            $product['longitude']
        );

        $price_min = (float) ($user->preferences['price_min'] ?? 0);
        $price_max = (float) ($user->preferences['price_max'] ?? PHP_FLOAT_MAX);

        $is_eligible = true;

        // Hard filter: must be within 30 km
        if ($distance === null || $distance > 30) {
            $is_eligible = false;
        }

        // Hard filter: must be within stated budget (min–max NPR)
        if ($price_max > 0 && ($product_price < $price_min || $product_price > $price_max)) {
            $is_eligible = false;
        }

        $s_lsc_raw = 0.0;
        if ($distance !== null) {
            $s_lsc_raw = $this->calculateLocationScore($distance);
        }
        $s_lsc_weighted = $s_lsc_raw * 0.60;

        // 2. Jaccard Index — |A ∩ B| / |A ∪ B| on preference vs product tags
        $j_index_weighted = $jaccard_raw * 0.20;

        // 3. Price Score (ranking within budget only; out-of-range items are excluded above)
        $s_price_raw = $this->calculatePriceScore($price_min, $price_max, $product_price);
        $s_price_weighted = $s_price_raw * 0.15;

        // 4. Style Match
        $style_matched = ($user->preferences['style'] === $product_style);
        $s_style_raw = $style_matched ? 1.0 : 0.0;
        $s_style_weighted = $s_style_raw * 0.05;

        // 5. Total Aggregation
        $total_score_raw = $s_lsc_weighted + $j_index_weighted + $s_price_weighted + $s_style_weighted;

        // 6. Beta Boost (+10% for items from similar curators)
        $beta_boost = 0.0;
        if (in_array($product['seller_id'], $similar_user_ids)) {
            $beta_boost = 0.10;
        }

        $total_score = $total_score_raw + $beta_boost;
        $overall_score = round(min(1.0, $total_score) * 100);

        return [
            'is_eligible' => $is_eligible,
            'within_budget' => $product_price >= $price_min && $product_price <= $price_max,
            'budget_range' => ['min' => $price_min, 'max' => $price_max],
            'distance_km' => $distance !== null ? round($distance, 1) : null,
            'overall_score' => $overall_score,
            'jaccard' => $jaccard,
            'components' => [
                'location' => [
                    'raw' => round($s_lsc_raw * 100),
                    'weighted' => round($s_lsc_weighted * 100),
                    'weight' => 60
                ],
                'category' => [
                    'raw' => round($jaccard_raw * 100),
                    'weighted' => round($j_index_weighted * 100),
                    'weight' => 20
                ],
                'price' => [
                    'raw' => round($s_price_raw * 100),
                    'weighted' => round($s_price_weighted * 100),
                    'weight' => 15
                ],
                'style' => [
                    'raw' => round($s_style_raw * 100),
                    'weighted' => round($s_style_weighted * 100),
                    'weight' => 5
                ],
                'beta' => [
                    'boost' => round($beta_boost * 100),
                    'applied' => $beta_boost > 0
                ]
            ]
        ];
    }


    private function haversineDistance($lat1, $lon1, $lat2, $lon2) {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
        $earth_radius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earth_radius * $c;
    }

    private function calculateLocationScore($distance) {
        // Distance-based decay: exp(-distance / 20)
        return exp(-$distance / 20);
    }

    /**
     * Build user preference tag set (same token rules as products).
     */
    public function buildUserTokens($user_categories) {
        $user_tokens = [];
        foreach ((array) $user_categories as $cat) {
            $user_tokens = array_merge($user_tokens, Product::tokenize($cat));
        }
        return array_values(array_unique($user_tokens));
    }

    /**
     * Standard Jaccard similarity: |A ∩ B| / |A ∪ B|
     * Returns index (0–1), percent, and sets for project documentation / UI.
     */
    public function computeJaccard($user_categories, $product) {
        $user_tokens = $this->buildUserTokens($user_categories);
        $product_tags = Product::getJaccardTags(is_array($product) ? $product : ['category' => $product]);

        $intersection = array_values(array_intersect($user_tokens, $product_tags));
        $union = array_values(array_unique(array_merge($user_tokens, $product_tags)));
        $index = count($union) > 0 ? count($intersection) / count($union) : 0.0;

        return [
            'index' => $index,
            'percent' => (int) round($index * 100),
            'user_tokens' => $user_tokens,
            'product_tags' => $product_tags,
            'intersection' => $intersection,
            'union' => $union,
            'formula' => '|A ∩ B| / |A ∪ B|',
        ];
    }

    /** @deprecated Use computeJaccard(); kept for callers that pass pre-built tag arrays */
    public function jaccardSimilarity($user_categories, $product_tags) {
        $user_tokens = $this->buildUserTokens($user_categories);
        $product_tags = array_values(array_unique((array) $product_tags));
        $intersection = count(array_intersect($user_tokens, $product_tags));
        $union = count(array_unique(array_merge($user_tokens, $product_tags)));
        return $union > 0 ? $intersection / $union : 0;
    }

    private function calculatePriceScore($min, $max, $price) {
        $variance_low = max(0, $min - $price) / max(1, $min);
        $variance_high = max(0, $price - $max) / max(1, $max);
        $variance = $variance_low + $variance_high;
        return max(0, exp(-5 * $variance));
    }
}
?>
