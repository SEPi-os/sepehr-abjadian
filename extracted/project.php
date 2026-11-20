<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start();
$currentUser = current_user();
$conn = db();

$slug = trim($_GET['slug'] ?? '');
$item = null;
if ($slug !== '') {
    $stmt = $conn->prepare("SELECT * FROM portfolio_items WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$item) {
    http_response_code(404);
}

if ($item) {
    $titles = ['fa' => $item['title_fa'], 'en' => $item['title_en'], 'ar' => $item['title_ar']];
    $tags = ['fa' => $item['tag_fa'], 'en' => $item['tag_en'], 'ar' => $item['tag_ar']];
    $descriptions = ['fa' => $item['description_fa'], 'en' => $item['description_en'], 'ar' => $item['description_ar']];
    $techStack = csv_to_chips($item['tech_stack']);
    $canonical = site_url('/projects/' . $item['slug']);
    $ogImage = $item['cover_image'] ? site_url('/' . ltrim($item['cover_image'], '/')) : site_url('/assets/brand/logo.jpeg');

    $related = [];
    $r = $conn->prepare(
        "SELECT slug, glyph, cover_image, title_fa, title_en, title_ar FROM portfolio_items
         WHERE category = ? AND id != ? AND status = 'published' ORDER BY display_order LIMIT 3"
    );
    $r->bind_param('si', $item['category'], $item['id']);
    $r->execute();
    $related = $r->get_result()->fetch_all(MYSQLI_ASSOC);
    $r->close();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="assets/brand/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="assets/brand/apple-touch-icon.png">
<script>
  (function () {
    try {
      var th = localStorage.getItem('portfolio-theme');
      document.documentElement.setAttribute('data-theme', (th === 'dark' || th === 'light') ? th : 'dark');
      var lg = localStorage.getItem('portfolio-language');
      lg = (lg === 'fa' || lg === 'en' || lg === 'ar') ? lg : 'fa';
      document.documentElement.setAttribute('lang', lg);
      document.documentElement.setAttribute('dir', lg === 'en' ? 'ltr' : 'rtl');
    } catch (e) { }
  })();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php if ($item): ?>
<title><?= e($titles['fa']) ?> | سپهر ابجدیان</title>
<meta name="description" content="<?= e($descriptions['fa']) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:title" content="<?= e($titles['fa']) ?>">
<meta property="og:description" content="<?= e($descriptions['fa']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($titles['fa']) ?>">
<meta name="twitter:description" content="<?= e($descriptions['fa']) ?>">
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CreativeWork',
    '@id' => $canonical . '#project',
    'name' => $titles['fa'],
    'description' => $descriptions['fa'],
    'url' => $canonical,
    'image' => $ogImage,
    'dateCreated' => substr($item['created_at'], 0, 10),
    'dateModified' => substr($item['updated_at'], 0, 10),
    'keywords' => $item['tech_stack'] ?: null,
    'author' => ['@type' => 'Person', '@id' => site_url('/') . '#person', 'name' => 'Sepehr Abjadian'],
]) ?>
</script>
<script type="application/ld+json">
<?= breadcrumb_ld([
    'خانه' => '/',
    'نمونه‌کارها' => '/portfolio',
    $titles['fa'] => '/projects/' . $item['slug'],
]) ?>
</script>
<?php else: ?>
<title>پروژه پیدا نشد | سپهر ابجدیان</title>
<meta name="robots" content="noindex">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/pages-shared.css?v=20260913">
<link rel="stylesheet" href="css/portfolio.css?v=20260913">
<link rel="stylesheet" href="css/bottom-nav.css?v=20260913">
<link rel="stylesheet" href="css/site-preferences.css?v=20260913">
<script defer src="js/i18n-data.js?v=20260913"></script>
<script defer src="js/site-preferences.js?v=20260913"></script>
<style>
  .proj-wrap { max-width: 780px; margin: 0 auto; padding: 120px 20px 100px; }
  .proj-crumbs { font-size: 13px; opacity: .6; margin-bottom: 18px; }
  .proj-crumbs a { color: inherit; text-decoration: none; }
  .proj-crumbs a:hover { text-decoration: underline; }
  .proj-cover { width: 100%; border-radius: 20px; overflow: hidden; margin-bottom: 24px; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; font-size: 64px; background: linear-gradient(135deg, rgba(93,139,255,.28), rgba(11,18,48,.9)); }
  .proj-cover img { width: 100%; height: 100%; object-fit: cover; }
  .proj-tag { display: inline-block; font-size: 12.5px; opacity: .7; margin-bottom: 8px; }
  .proj-title { font-size: 28px; margin: 0 0 16px; }
  .proj-desc { font-size: 15.5px; line-height: 1.9; opacity: .85; margin-bottom: 24px; }
  .proj-stack { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
  .proj-stack .chip { font-size: 12.5px; padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); }
  .proj-cta { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 999px; background: linear-gradient(135deg,#5d8bff,#9d6bff); color: #fff; text-decoration: none; font-size: 14.5px; }
  .proj-related { margin-top: 56px; padding-top: 32px; border-top: 1px solid rgba(255,255,255,.08); }
  .proj-related h2 { font-size: 16px; margin-bottom: 16px; }
  .proj-related-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
  .proj-related-grid a { display: block; padding: 14px; border-radius: 14px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); text-decoration: none; color: inherit; font-size: 13.5px; }
</style>
</head>
<body class="pg-body">

<?php if ($item): ?>
<main class="proj-wrap">
  <nav class="proj-crumbs" aria-label="مسیر">
    <a href="/">خانه</a> / <a href="/portfolio">نمونه‌کارها</a> / <span><?= e($titles['fa']) ?></span>
  </nav>

  <div class="proj-cover">
    <?php if ($item['cover_image']): ?>
      <img src="<?= e($item['cover_image']) ?>" alt="<?= e($titles['fa']) ?>" loading="lazy">
    <?php else: ?>
      <span><?= e($item['glyph'] ?: '📦') ?></span>
    <?php endif; ?>
  </div>

  <?php if ($tags['fa']): ?><span class="proj-tag"><?= e($tags['fa']) ?></span><?php endif; ?>
  <h1 class="proj-title"><?= e($titles['fa']) ?></h1>
  <p class="proj-desc"><?= e($descriptions['fa']) ?></p>

  <?php if ($techStack): ?>
  <div class="proj-stack">
    <?php foreach ($techStack as $tech): ?><span class="chip"><?= e($tech) ?></span><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($item['project_url']): ?>
    <a class="proj-cta" href="<?= e($item['project_url']) ?>" target="_blank" rel="noopener">مشاهده پروژه ↗</a>
  <?php endif; ?>

  <?php if ($related): ?>
  <div class="proj-related">
    <h2>پروژه‌های مرتبط</h2>
    <div class="proj-related-grid">
      <?php foreach ($related as $rp): ?>
        <a href="/projects/<?= e($rp['slug']) ?>"><?= e($rp['title_fa']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php else: ?>
<main class="proj-wrap">
  <div style="text-align:center;padding:60px 0;">
    <h1 style="font-size:22px;">پروژه پیدا نشد</h1>
    <p style="opacity:.7;margin:12px 0 24px;">این پروژه ممکنه حذف شده یا آدرسش تغییر کرده باشه.</p>
    <a href="/portfolio" style="color:#5d8bff;">بازگشت به نمونه‌کارها</a>
  </div>
</main>
<?php endif; ?>

<nav class="liquid-nav" aria-label="پیمایش اصلی" data-i18n-aria-label="bottomNav.ariaMain">
  <span class="liquid-nav__indicator" aria-hidden="true"></span>
  <a href="/" class="liquid-nav__item" data-page="home">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.home">خانه</span>
  </a>
  <a href="/shop" class="liquid-nav__item" data-page="shop">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.shop">فروشگاه</span>
  </a>
  <a href="/portfolio" class="liquid-nav__item is-active" data-page="portfolio">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.portfolio">نمونه‌کار</span>
  </a>
  <a href="/blog" class="liquid-nav__item" data-page="blog">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.blog">وبلاگ</span>
  </a>
  <a href="/services" class="liquid-nav__item" data-page="services">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.services">خدمات</span>
  </a>
  <a href="/contact" class="liquid-nav__item" data-page="contact">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2z"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.contact">تماس</span>
  </a>
</nav>

<script src="js/bottom-nav.js?v=20260913"></script>
</body>
</html>
