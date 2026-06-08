<?php
require_once __DIR__ . '/Models/Product.php';

/**
 * Build category string from POST (multi-select categories[] or legacy section).
 */
function resolve_product_categories_from_request() {
    if (!empty($_POST['categories']) && is_array($_POST['categories'])) {
        return Product::encodeCategories($_POST['categories']);
    }
    if (!empty($_POST['section'])) {
        return Product::encodeCategories([$_POST['section']]);
    }
    if (!empty($_POST['category']) && strpos($_POST['category'], ',') !== false) {
        return Product::encodeCategories(explode(',', $_POST['category']));
    }
    return '';
}

function validate_product_categories_field($full_category) {
    $list = Product::parseCategoryField($full_category);
    if (count($list) === 0) {
        return ['ok' => false, 'message' => 'Select at least one discovery category'];
    }
    return ['ok' => true, 'categories' => $list, 'encoded' => Product::encodeCategories($list)];
}
