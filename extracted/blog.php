<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start();
$currentUser = current_user();
$conn = db();

$categories = [];
$catRes = $conn->query('SELECT slug, name_fa, name_en, name_ar FROM blog_categories ORDER BY id');
while ($row = $catRes->fetch_assoc()) {
    $categories[] = ['slug' => $row['slug'], 'name' => ['fa' => $row['name_fa'], 'en' => $row['name_en'], 'ar' => $row['name_ar']]];
}

$posts = [];
$postRes = $conn->query(
    "SELECT p.slug, p.glyph, p.is_featured, p.read_time_min, p.tags,
            p.title_fa, p.title_en, p.title_ar,
            p.excerpt_fa, p.excerpt_en, p.excerpt_ar,
            p.published_at, p.created_at, c.slug AS category_slug
     FROM blog_posts p
     LEFT JOIN blog_categories c ON c.id = p.category_id
     WHERE p.status = 'published' ORDER BY p.published_at DESC"
);
while ($row = $postRes->fetch_assoc()) {
    $dateYmd = $row['published_at'] ? substr($row['published_at'], 0, 10) : substr($row['created_at'], 0, 10);
    $posts[] = [
        'slug' => $row['slug'],
        'category' => $row['category_slug'] ?: 'web',
        'glyph' => $row['glyph'] ?: '📝',
        'featured' => (bool) $row['is_featured'],
        'readTimeMin' => (int) $row['read_time_min'],
        'tags' => csv_to_chips($row['tags']),
        'title' => ['fa' => $row['title_fa'], 'en' => $row['title_en'], 'ar' => $row['title_ar']],
        'excerpt' => ['fa' => $row['excerpt_fa'], 'en' => $row['excerpt_en'], 'ar' => $row['excerpt_ar']],
        'date' => ['fa' => format_post_date($dateYmd, 'fa'), 'en' => format_post_date($dateYmd, 'en'), 'ar' => format_post_date($dateYmd, 'ar')],
    ];
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
<title data-i18n="blog.title">وبلاگ | سپهر ابجدیان — توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی</title>
<meta name="description" data-i18n-content="blog.metaDescription" content="یادداشت‌هایی درباره‌ی هوش مصنوعی، پایتون، یادگیری ماشین، React و Next.js، Three.js و معماری فول‌استک؛ از پشت‌صحنه‌ی پروژه‌های واقعی سپهر ابجدیان.">
<meta property="og:title" data-i18n-content="blog.ogTitle" content="وبلاگ | سپهر ابجدیان">
<meta property="og:description" data-i18n-content="blog.ogDescription" content="یادداشت‌هایی درباره‌ی هوش مصنوعی، پایتون و توسعه‌ی وب از پشت‌صحنه‌ی پروژه‌های واقعی.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<link rel="canonical" href="<?= e(site_url('/blog')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" data-i18n-content="blog.ogTitle" content="وبلاگ | سپهر ابجدیان">
<meta name="twitter:description" data-i18n-content="blog.ogDescription" content="یادداشت‌هایی درباره‌ی هوش مصنوعی، پایتون و توسعه‌ی وب از پشت‌صحنه‌ی پروژه‌های واقعی.">
<meta name="twitter:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<script type="application/ld+json">
<?= breadcrumb_ld(['خانه' => '/', 'وبلاگ' => '/blog']) ?>
</script>
<?php if ($posts): ?>
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    '@id' => site_url('/blog') . '#collection',
    'name' => 'وبلاگ سپهر ابجدیان',
    'url' => site_url('/blog'),
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => array_values(array_map(function ($i, $p) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => site_url('/blog/' . $p['slug']), 'name' => $p['title']['fa']];
        }, array_keys($posts), $posts)),
    ],
]) ?>
</script>
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
      <span class="theme-toggle__icon theme-toggle__icon--moon">
        <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </span>
      <span class="theme-toggle__icon theme-toggle__icon--sun">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/></svg>
      </span>
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
  <section class="page-hero">
    <div class="kicker page-hero__eyebrow">// THE JOURNAL</div>
    <h1 data-i18n="blog.heroH1">وبلاگ</h1>
    <p data-i18n="blog.heroP">یادداشت‌هایی از پشت‌صحنه‌ی پروژه‌های واقعی — هوش مصنوعی، پایتون، یادگیری ماشین، وب مدرن و تصمیم‌های فنی که پشت‌شون هستن.</p>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="glass-card blog-author reveal">
      <img class="blog-author__avatar" src="assets/brand/logo-footer.jpg" alt="سپهر ابجدیان" width="52" height="52" loading="lazy">
      <div>
        <strong data-i18n="blog.authorName">سپهر ابجدیان</strong>
        <p data-i18n="blog.authorBio">توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی؛ این‌جا درباره‌ی پروژه‌هایی که می‌سازم و چیزهایی که در مسیرشون یاد می‌گیرم می‌نویسم.</p>
      </div>
    </div>
  </section>

  <section class="pg-section">
    <div class="blog-toolbar reveal">
      <label class="blog-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" id="blogSearch" placeholder="جست‌وجو در مقاله‌ها…" aria-label="جست‌وجو در مقاله‌ها" data-i18n-placeholder="blog.searchPlaceholder" data-i18n-aria-label="blog.searchAriaLabel">
      </label>
      <div class="blog-categories" id="blogCategories"></div>
    </div>

    <div id="featuredSlot"></div>
    <div class="post-grid" id="postGrid"></div>
    <p class="post-empty" id="postEmpty" data-i18n="blog.empty">مقاله‌ای با این مشخصات پیدا نشد — عبارت جست‌وجو یا دسته را عوض کنید.</p>
    <div class="pagination" id="pagination"></div>
    <script id="blogData" type="application/json"><?= json_encode(['posts' => $posts, 'categories' => $categories], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="glass-card newsletter reveal">
      <h3 data-i18n="blog.newsletterHeading">مقاله‌های تازه رو از دست نده</h3>
      <p data-i18n="blog.newsletterP">هر چند وقت یک‌بار، یک یادداشت تازه درباره‌ی هوش مصنوعی و توسعه‌ی وب می‌فرستم — بدون اسپم.</p>
      <form>
        <input type="email" required placeholder="ایمیلت رو وارد کن" aria-label="ایمیل برای خبرنامه" data-i18n-placeholder="blog.newsletterPlaceholder" data-i18n-aria-label="blog.newsletterAriaLabel">
        <button type="submit" class="cta-btn primary" data-i18n="blog.newsletterSubmit">عضویت</button>
      </form>
      <p class="newsletter__done" data-i18n="blog.newsletterDone">✓ ثبت شد! ممنون که همراهی می‌کنی.</p>
    </div>
  </section>
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

<a class="call-fab" href="tel:+989044691979" aria-label="تماس فوری" data-i18n-aria-label="common.callAria">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
</a>

<script src="js/ambient.js?v=20260913"></script>
<script src="js/blog.js?v=20260913"></script>
<script src="js/bottom-nav.js?v=20260913"></script>
</body>
</html>
