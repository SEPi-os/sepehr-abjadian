<?php
/**
 * sitemap.php — served at /sitemap.xml via the .htaccess rewrite (see
 * robots.txt). Static pages are listed by hand (there are only 5, they
 * rarely change); blog posts and portfolio items are pulled live from
 * the database so a newly published one appears here automatically.
 * Only canonical, indexable, public clean URLs go in this file — no
 * .php paths, no /admin, /api, /account, /dashboard, no query strings.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo.php';
$conn = db();

header('Content-Type: application/xml; charset=utf-8');

$staticPages = [
    ['loc' => site_url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => site_url('/portfolio'), 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => site_url('/services'), 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => site_url('/blog'), 'changefreq' => 'daily', 'priority' => '0.8'],
    ['loc' => site_url('/contact'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => site_url('/shop'), 'changefreq' => 'daily', 'priority' => '0.9'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPages as $p) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($p['loc'], ENT_XML1) . "</loc>\n";
    echo '    <changefreq>' . $p['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $p['priority'] . "</priority>\n";
    echo "  </url>\n";
}

$posts = $conn->query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'");
while ($row = $posts->fetch_assoc()) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars(site_url('/blog/' . $row['slug']), ENT_XML1) . "</loc>\n";
    echo '    <lastmod>' . substr($row['updated_at'], 0, 10) . "</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

$projects = $conn->query("SELECT slug, updated_at FROM portfolio_items WHERE status = 'published'");
while ($row = $projects->fetch_assoc()) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars(site_url('/projects/' . $row['slug']), ENT_XML1) . "</loc>\n";
    echo '    <lastmod>' . substr($row['updated_at'], 0, 10) . "</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

// ---- Store: categories + products (only public, indexable ones; a missing store schema never breaks the sitemap)
try {
    $cats = $conn->query("SELECT slug, updated_at FROM shop_categories WHERE is_active = 1 AND robots_index = 1");
    while ($cats && ($row = $cats->fetch_assoc())) {
        echo "  <url>\n";
        echo '    <loc>' . htmlspecialchars(site_url('/shop/category/' . $row['slug']), ENT_XML1) . "</loc>\n";
        echo '    <lastmod>' . substr($row['updated_at'], 0, 10) . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
    $prods = $conn->query("SELECT p.slug, p.updated_at FROM shop_products p JOIN shop_categories c ON c.id = p.category_id
        WHERE p.status = 'published' AND p.is_active = 1 AND p.deleted_at IS NULL AND p.robots_index = 1 AND c.is_active = 1");
    while ($prods && ($row = $prods->fetch_assoc())) {
        echo "  <url>\n";
        echo '    <loc>' . htmlspecialchars(site_url('/shop/' . $row['slug']), ENT_XML1) . "</loc>\n";
        echo '    <lastmod>' . substr($row['updated_at'], 0, 10) . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
} catch (Throwable $e) {
    // store tables not installed yet -> simply no store URLs
}

echo '</urlset>' . "\n";
