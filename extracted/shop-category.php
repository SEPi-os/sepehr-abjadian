<?php
require_once __DIR__ . '/includes/store-listing.php';
$slug = (string) ($_GET['slug'] ?? '');
$category = preg_match('/^[a-zA-Z0-9-]{1,160}$/', $slug) ? store_category_by_slug($slug) : null;
if (!$category) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
store_render_listing($category);
