<?php
/**
 * api/home-highlights.php — read-only JSON for the two new homepage
 * "highlights" sections (latest articles, top portfolio items). index.html
 * is a static file (no PHP), so these two dynamic strips are fetched
 * client-side by js/home-highlights.js rather than rendered server-side —
 * keeps the homepage itself a fast static file while still surfacing live
 * content. Same explicit-column style as blog.php/portfolio.php: no
 * SELECT *, no article body text pulled for what's only ever a small card.
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$conn = db();

$posts = [];
$postsRes = $conn->query(
    "SELECT slug, glyph, title_fa, title_en, title_ar,
            excerpt_fa, excerpt_en, excerpt_ar, published_at
     FROM blog_posts WHERE status = 'published'
     ORDER BY published_at DESC LIMIT 3"
);
if ($postsRes) {
    while ($row = $postsRes->fetch_assoc()) {
        $posts[] = [
            'slug' => $row['slug'],
            'glyph' => $row['glyph'] ?: '📝',
            'title' => ['fa' => $row['title_fa'], 'en' => $row['title_en'], 'ar' => $row['title_ar']],
            'excerpt' => ['fa' => $row['excerpt_fa'], 'en' => $row['excerpt_en'], 'ar' => $row['excerpt_ar']],
        ];
    }
}

$items = [];
$itemsRes = $conn->query(
    "SELECT category, glyph, cover_image,
            title_fa, title_en, title_ar,
            tag_fa, tag_en, tag_ar
     FROM portfolio_items WHERE status = 'published'
     ORDER BY display_order, id LIMIT 6"
);
if ($itemsRes) {
    while ($row = $itemsRes->fetch_assoc()) {
        $cover = $row['cover_image'];
        $items[] = [
            'category' => $row['category'],
            'glyph' => $row['glyph'] ?: '📦',
            'coverImage' => ($cover && $cover !== '0') ? $cover : null,
            'title' => ['fa' => $row['title_fa'], 'en' => $row['title_en'], 'ar' => $row['title_ar']],
            'tag' => ['fa' => $row['tag_fa'], 'en' => $row['tag_en'], 'ar' => $row['tag_ar']],
        ];
    }
}

echo json_encode(['posts' => $posts, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
