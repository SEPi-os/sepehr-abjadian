<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start();
$currentUser = current_user();
$conn = db();

$slug = trim($_GET['slug'] ?? '');
$post = null;
if ($slug !== '') {
    $stmt = $conn->prepare(
        "SELECT p.*, c.slug AS category_slug, c.name_fa AS cat_fa, c.name_en AS cat_en, c.name_ar AS cat_ar
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = 'published' LIMIT 1"
    );
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$post) {
    http_response_code(404);
}

if ($post) {
    $dateYmd = $post['published_at'] ? substr($post['published_at'], 0, 10) : substr($post['created_at'], 0, 10);
    $dates = ['fa' => format_post_date($dateYmd, 'fa'), 'en' => format_post_date($dateYmd, 'en'), 'ar' => format_post_date($dateYmd, 'ar')];
    $titles = ['fa' => $post['title_fa'], 'en' => $post['title_en'], 'ar' => $post['title_ar']];
    $metaTitles = [
        'fa' => $post['meta_title_fa'] ?: $post['title_fa'],
        'en' => $post['meta_title_en'] ?: $post['title_en'],
        'ar' => $post['meta_title_ar'] ?: $post['title_ar'],
    ];
    $metaDescs = [
        'fa' => $post['meta_description_fa'] ?: $post['excerpt_fa'],
        'en' => $post['meta_description_en'] ?: $post['excerpt_en'],
        'ar' => $post['meta_description_ar'] ?: $post['excerpt_ar'],
    ];
    $bodies = ['fa' => $post['content_fa'], 'en' => $post['content_en'], 'ar' => $post['content_ar']];
    $videoEmbed = video_embed_html($post['video_url'] ?? null);
    $featuredImage = $post['featured_image'] ?? null;
    $tags = csv_to_chips($post['tags']);
    $canonical = site_url('/blog/' . $post['slug']);

    // Related posts: same category, excluding this one.
    $related = [];
    if ($post['category_id']) {
        $r = $conn->prepare(
            "SELECT slug, glyph, title_fa, title_en, title_ar FROM blog_posts
             WHERE category_id = ? AND id != ? AND status = 'published' ORDER BY published_at DESC LIMIT 3"
        );
        $r->bind_param('ii', $post['category_id'], $post['id']);
        $r->execute();
        $related = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();
    }
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
<?php if ($post): ?>
<title id="pageTitle"><?= e($metaTitles['fa']) ?> | سپهر ابجدیان</title>
<meta id="metaDescription" name="description" content="<?= e($metaDescs['fa']) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:title" content="<?= e($metaTitles['fa']) ?>">
<meta property="og:description" content="<?= e($metaDescs['fa']) ?>">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:image" content="<?= e($featuredImage ? site_url('/' . ltrim($featuredImage, '/')) : site_url('/assets/brand/logo.jpeg')) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="article:published_time" content="<?= e($dateYmd) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($metaTitles['fa']) ?>">
<meta name="twitter:description" content="<?= e($metaDescs['fa']) ?>">
<meta name="twitter:image" content="<?= e($featuredImage ? site_url('/' . ltrim($featuredImage, '/')) : site_url('/assets/brand/logo.jpeg')) ?>">
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $titles['fa'],
    'description' => $metaDescs['fa'],
    'datePublished' => $dateYmd,
    'dateModified' => substr($post['updated_at'], 0, 10),
    'author' => ['@type' => 'Person', 'name' => 'Sepehr Abjadian', 'url' => site_url('/')],
    'publisher' => ['@type' => 'Person', 'name' => 'Sepehr Abjadian'],
    'mainEntityOfPage' => $canonical,
]) ?>
</script>
<script type="application/ld+json">
<?= breadcrumb_ld(['خانه' => '/', 'وبلاگ' => '/blog', $titles['fa'] => '/blog/' . $post['slug']]) ?>
</script>
<?php else: ?>
<title>مقاله پیدا نشد | سپهر ابجدیان</title>
<meta name="robots" content="noindex">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/pages-shared.css?v=20260913">
<link rel="stylesheet" href="css/blog.css?v=20260913">
<link rel="stylesheet" href="css/bottom-nav.css?v=20260913">
<link rel="stylesheet" href="css/site-preferences.css?v=20260913">
<script defer src="js/i18n-data.js?v=20260913"></script>
<script defer src="js/site-preferences.js?v=20260913"></script>
</head>
<body>

<div class="noise"></div>
<div class="bg-orbs"><span class="orb-1"></span><span class="orb-2"></span><span class="orb-3"></span></div>
<div class="scroll-progress"></div>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>
<div id="ripple-layer"></div>

<div class="pref-bar">
  <?= render_auth_button($currentUser) ?>
  <div class="pref-controls" id="prefControls">
    <button type="button" class="theme-toggle" id="themeToggle" aria-pressed="false" aria-label="تغییر به حالت روشن">
      <span class="theme-toggle__icon theme-toggle__icon--moon"><svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
      <span class="theme-toggle__icon theme-toggle__icon--sun"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/></svg></span>
    </button>
    <div class="lang-switch" id="langSwitch">
      <button type="button" class="lang-switch__trigger" id="langTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="langMenu" aria-label="انتخاب زبان">
        <span id="langCode">فا</span>
        <svg class="lang-switch__chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="lang-switch__menu" id="langMenu" role="menu">
        <button type="button" class="lang-switch__option is-active" role="menuitem" data-lang="fa">فارسی<svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></button>
        <button type="button" class="lang-switch__option" role="menuitem" data-lang="en">English<svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></button>
        <button type="button" class="lang-switch__option" role="menuitem" data-lang="ar">العربية<svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></button>
      </div>
    </div>
  </div>
</div>

<main>
<?php if (!$post): ?>
  <section class="page-hero">
    <div class="kicker page-hero__eyebrow">// 404</div>
    <h1>مقاله پیدا نشد</h1>
    <p>این مقاله یا حذف شده یا هیچ‌وقت وجود نداشته. برگرد به <a href="/blog" style="text-decoration:underline;">وبلاگ</a>.</p>
  </section>
<?php else: ?>
  <article>
    <section class="page-hero article-hero">
      <div class="kicker page-hero__eyebrow" id="articleCategory">// <?= e($post['cat_fa'] ?? 'JOURNAL') ?></div>
      <h1 id="articleTitle"><?= e($titles['fa']) ?></h1>
      <div class="article-hero__meta">
        <span id="articleDate"><?= e($dates['fa']) ?></span>
        <span>·</span>
        <span id="articleReadTime"><?= (int) $post['read_time_min'] ?> دقیقه مطالعه</span>
      </div>
      <div class="post-card__tags" style="justify-content:center;margin-top:14px;">
        <?php foreach ($tags as $tag): ?><span class="chip"><?= e($tag) ?></span><?php endforeach; ?>
      </div>
    </section>

    <section class="pg-section pg-section--tight">
      <?php if ($videoEmbed): ?>
        <div class="article-video reveal"><?= $videoEmbed ?></div>
      <?php endif; ?>

      <div class="glass-card article-body reveal" id="articleBody">
        <?php
        $paragraphs = explode("\n\n", $bodies['fa']);
        $midpoint = (int) ceil(count($paragraphs) / 2);
        foreach ($paragraphs as $i => $para):
        ?>
          <p><?= e(trim($para)) ?></p>
          <?php if ($featuredImage && $i === $midpoint - 1): ?>
            <figure class="article-inline-image">
              <img src="<?= e($featuredImage) ?>" alt="<?= e($titles['fa']) ?>" loading="lazy">
            </figure>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="glass-card blog-author reveal" style="margin-top:24px;">
        <img class="blog-author__avatar" src="assets/brand/logo-footer.jpg" alt="سپهر ابجدیان" width="52" height="52" loading="lazy">
        <div>
          <strong data-i18n="blog.authorName">سپهر ابجدیان</strong>
          <p data-i18n="blog.authorBio">توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی؛ این‌جا درباره‌ی پروژه‌هایی که می‌سازم و چیزهایی که در مسیرشون یاد می‌گیرم می‌نویسم.</p>
        </div>
      </div>

      <?php if ($related): ?>
      <div style="margin-top:48px;">
        <h3 style="font-size:18px;font-weight:800;margin-bottom:20px;">مقاله‌های مرتبط</h3>
        <div class="post-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
          <?php foreach ($related as $r): ?>
            <a class="glass-card post-card reveal" href="/blog/<?= urlencode($r['slug']) ?>">
              <div class="post-card__glyph"><?= $r['glyph'] ?: '📝' ?></div>
              <h3><?= e($r['title_fa']) ?></h3>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <a href="/blog" class="cta-btn ghost" style="margin-top:40px;display:inline-flex;">← بازگشت به وبلاگ</a>
    </section>
  </article>

  <script id="articleI18n" type="application/json"><?= json_encode([
      'title' => $titles, 'metaTitle' => $metaTitles, 'metaDescription' => $metaDescs,
      'date' => $dates, 'body' => $bodies,
      'category' => ['fa' => $post['cat_fa'] ?? '', 'en' => $post['cat_en'] ?? '', 'ar' => $post['cat_ar'] ?? ''],
      'featuredImage' => $featuredImage, 'imageAlt' => $titles,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
</main>

<footer class="pg-footer">
  <div class="pg-footer__inner">
    <div class="pg-footer__brand">
      <div class="pg-footer__logo">
        <img src="assets/brand/logo-footer.jpg" alt="سپهر ابجدیان" width="36" height="36" loading="lazy">
        <span>Sepehr Abjadian</span>
      </div>
      <p data-i18n="footer.tagline">توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی — ساخت ابزارهای هوشمند، اتوماسیون و اپلیکیشن‌های وب.</p>
    </div>
    <div>
      <h4 data-i18n="footer.pagesHeading">صفحات</h4>
      <ul>
        <li><a href="/" data-i18n="bottomNav.home">خانه</a></li>
        <li><a href="/shop" data-i18n="bottomNav.shop">فروشگاه</a></li>
        <li><a href="/portfolio" data-i18n="bottomNav.portfolio">نمونه‌کار</a></li>
        <li><a href="/blog" data-i18n="bottomNav.blog">وبلاگ</a></li>
        <li><a href="/services" data-i18n="bottomNav.services">خدمات</a></li>
        <li><a href="/contact" data-i18n="bottomNav.contact">تماس</a></li>
      </ul>
    </div>
    <div>
      <h4 data-i18n="footer.contactHeading">ارتباط</h4>
      <ul>
        <li><a href="mailto:i88020023@gmail.com">i88020023@gmail.com</a></li>
        <li><a href="tel:+989044691979" dir="ltr">+98 904 469 1979</a></li>
        <li><a href="https://www.instagram.com/s_abjadian" target="_blank" rel="noopener">Instagram · s_abjadian</a></li>
        <li><a href="https://t.me/sepehrworld_bot" target="_blank" rel="noopener" data-i18n="contact.cardTelegramLabel">دستیار تلگرام</a></li>
      </ul>
    </div>
  </div>
  <div class="pg-footer__bottom">
    <span data-i18n="footer.copyright">© ۱۴۰۵ سپهر ابجدیان. تمامی حقوق محفوظ است.</span>
  </div>
</footer>

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
  <a href="/portfolio" class="liquid-nav__item" data-page="portfolio">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.portfolio">نمونه‌کار</span>
  </a>
  <a href="/blog" class="liquid-nav__item is-active" data-page="blog">
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

<a class="call-fab" href="tel:+989044691979" aria-label="تماس فوری" data-i18n-aria-label="common.callAria">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
</a>

<script src="js/ambient.js?v=20260913"></script>
<script src="js/bottom-nav.js?v=20260913"></script>
<?php if ($post): ?><script src="js/blog-post.js?v=20260913"></script><?php endif; ?>
</body>
</html>
