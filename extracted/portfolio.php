<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start();
$currentUser = current_user();
$conn = db();
$items = $conn->query(
    "SELECT slug, category, title_fa, title_en, title_ar, tag_fa, tag_en, tag_ar,
            description_fa, description_en, description_ar, tech_stack,
            cover_image, glyph, project_url
     FROM portfolio_items WHERE status='published' ORDER BY display_order, id"
);
$itemsData = [];
while ($row = $items->fetch_assoc()) {
    $itemsData[] = [
        'slug' => $row['slug'],
        'category' => $row['category'],
        'title' => ['fa' => $row['title_fa'], 'en' => $row['title_en'], 'ar' => $row['title_ar']],
        'tag' => ['fa' => $row['tag_fa'], 'en' => $row['tag_en'], 'ar' => $row['tag_ar']],
        'description' => ['fa' => $row['description_fa'], 'en' => $row['description_en'], 'ar' => $row['description_ar']],
        'techStack' => csv_to_chips($row['tech_stack']),
        'coverImage' => $row['cover_image'],
        'glyph' => $row['glyph'],
        'projectUrl' => $row['project_url'],
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
<title data-i18n="portfolio.title">نمونه‌کارها | سپهر ابجدیان — توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی</title>
<meta name="description" data-i18n-content="portfolio.metaDescription" content="نمونه‌کارهای سپهر ابجدیان: دستیار هوش مصنوعی تلگرام، دستیار دسکتاپ آتلاس، سامانه‌ی پیش‌بینی فوتبال، تحلیل معاملاتی طلا، و پروژه‌های وب فول‌استک برای کسب‌وکارهای واقعی.">
<link rel="canonical" href="<?= e(site_url('/portfolio')) ?>">
<meta property="og:title" data-i18n-content="portfolio.ogTitle" content="نمونه‌کارها | سپهر ابجدیان">
<meta property="og:description" data-i18n-content="portfolio.ogDescription" content="ایجنت‌های هوش مصنوعی، اپلیکیشن‌های دسکتاپ و وب‌سایت‌های فول‌استک — نمونه‌کارهای منتخب.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:url" content="<?= e(site_url('/portfolio')) ?>">
<meta property="og:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" data-i18n-content="portfolio.ogTitle" content="نمونه‌کارها | سپهر ابجدیان">
<meta name="twitter:description" data-i18n-content="portfolio.ogDescription" content="ایجنت‌های هوش مصنوعی، اپلیکیشن‌های دسکتاپ و وب‌سایت‌های فول‌استک — نمونه‌کارهای منتخب.">
<meta name="twitter:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
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
<script type="application/ld+json">
<?= breadcrumb_ld(['خانه' => '/', 'نمونه‌کارها' => '/portfolio']) ?>
</script>
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    '@id' => site_url('/portfolio') . '#collection',
    'name' => 'نمونه‌کارهای سپهر ابجدیان',
    'url' => site_url('/portfolio'),
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => array_values(array_map(function ($i, $item) {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => site_url('/projects/' . $item['slug']),
                'name' => $item['title']['fa'],
            ];
        }, array_keys($itemsData), $itemsData)),
    ],
]) ?>
</script>
</head>
<body>

<div class="noise"></div>
<div class="bg-orbs"><span class="orb-1"></span><span class="orb-2"></span><span class="orb-3"></span></div>
<div class="scroll-progress"></div>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>
<div id="ripple-layer"></div>

<!-- No top .glass-nav on this page (only index.php has one) — this small
     floating pill carries the same controls in the same screen position,
     plus a direct call button, styled with the same glass tokens. -->
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
    <div class="kicker page-hero__eyebrow">// PORTFOLIO</div>
    <h1 data-i18n="portfolio.heroH1">نمونه‌کارها</h1>
    <p data-i18n="portfolio.heroP">از یک دستیار هوش مصنوعی روی تلگرام تا یک لندینگ سینمایی سه‌بعدی — این‌ها چیزهایی‌ان که واقعاً ساختم، نه فقط پروژه‌ی آموزشی.</p>
  </section>

  <section class="pg-section">
    <article class="glass-card bot-feature reveal">
      <div class="bot-feature__visual" aria-hidden="true">
        <span class="bot-feature__badge">
          <span class="bot-feature__dot"></span>
          <span data-i18n="portfolio.botBadgeLive">فعال · پاسخ‌گوی آنی</span>
        </span>
        <div class="bot-feature__glyph">
          <svg viewBox="0 0 240 240" width="120" height="120">
            <circle cx="120" cy="120" r="118" fill="url(#tgGrad)"/>
            <path fill="#fff" d="M167.5 72.8 51.1 118.4c-6.6 2.6-6.5 6.2-1.2 7.8l29.7 9.3 68.9-43.4c3.2-2.1 6.2-.9 3.8 1.4l-55.8 50.4h-.1l2 29.4c2.9 0 4.2-1.3 5.8-2.9l14-13.5 29 21.4c5.3 2.9 9.2 1.4 10.5-5l19-89.6c1.9-7.8-3-11.4-9.2-8.9z"/>
            <defs><linearGradient id="tgGrad" x1="0" y1="0" x2="240" y2="240"><stop offset="0" stop-color="#4fc7ff"/><stop offset="1" stop-color="#229ed9"/></linearGradient></defs>
          </svg>
        </div>
      </div>
      <div class="bot-feature__body">
        <div class="kicker" data-i18n="portfolio.botKicker">// FEATURED</div>
        <h2 data-i18n="portfolio.botTitle">دستیار هوش مصنوعی من روی تلگرام</h2>
        <p data-i18n="portfolio.botDesc">یک ایجنت هوش مصنوعی دوزبانه که با n8n و Gemini Flash Lite ساختمش تا مستقیم با مخاطب‌ها گفت‌وگو کنه — درباره‌ی خدمات، تعرفه‌ها، نمونه‌کارها و هر سوالی که درباره‌ی من دارن. هر گفت‌وگو یک نشست اختصاصیه؛ کافیه پیام بدی و شروع کنی.</p>
        <ul class="bot-feature__list">
          <li data-i18n="portfolio.botBullet1">پایگاه دانش اختصاصی با بیش از ۵۰۰ رکورد از بیوگرافی و تعرفه‌ها</li>
          <li data-i18n="portfolio.botBullet2">حافظه‌ی گفت‌وگو برای هر کاربر، با یک System Prompt کاملاً خوداتکا</li>
          <li data-i18n="portfolio.botBullet3">ساخته‌شده کاملاً روی n8n — بدون سرور یا بک‌اند جداگانه</li>
        </ul>
        <div class="bot-feature__actions">
          <a class="cta-btn" href="https://t.me/sepehrworld_bot" target="_blank" rel="noopener">
            <span data-i18n="portfolio.botCta">شروع گفت‌وگو با دستیار</span>
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M7 17 17 7M9 7h8v8"/></svg>
          </a>
          <span class="bot-feature__note" data-i18n="portfolio.botNote">در تلگرام باز می‌شه</span>
        </div>
      </div>
    </article>
  </section>

  <section class="pg-section">
    <div class="pg-section__head reveal">
      <div class="kicker" data-i18n="portfolio.gridKicker">// SELECTED WORKS</div>
      <h2 data-i18n-html="portfolio.gridHeadingHtml">نمونه‌کارهای <span class="grad">منتخب</span></h2>
      <p data-i18n="portfolio.gridP">یک نمونه از پروژه‌های واقعی — از ایجنت‌های هوش مصنوعی و مدل‌های یادگیری ماشین تا وب‌سایت‌های فول‌استک برای کسب‌وکارهای واقعی.</p>
    </div>

    <div class="work-filters reveal" role="tablist" aria-label="فیلتر نمونه‌کارها">
      <button type="button" class="chip is-active" data-filter="all" data-i18n="portfolio.filterAll">همه</button>
      <button type="button" class="chip" data-filter="ai" data-i18n="portfolio.filterAi">هوش مصنوعی و اتوماسیون</button>
      <button type="button" class="chip" data-filter="web" data-i18n="portfolio.filterWeb">وب و فرانت‌اند</button>
    </div>

    <div class="works-grid" id="worksGrid">
      <!-- Rendered client-side by js/portfolio.js from the JSON below —
           this is what makes the grid respond instantly to the language
           switcher, the same as every other page, instead of reloading. -->
    </div>
    <script id="portfolioData" type="application/json"><?= json_encode($itemsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  </section>

  <section class="pg-section">
    <article class="glass-card cta-card reveal">
      <h2 data-i18n="portfolio.ctaHeading">پروژه‌ی بعدی، پروژه‌ی توئه</h2>
      <p data-i18n="portfolio.ctaP">اگه دنبال چیزی شبیه یکی از این‌ها هستی — یا کاملاً متفاوت — بیا درباره‌ش حرف بزنیم.</p>
      <div class="cta-card__actions">
        <a class="cta-btn" href="/contact" data-i18n="portfolio.ctaBtn1">درخواست پروژه</a>
        <a class="cta-btn ghost" href="/services" data-i18n="portfolio.ctaBtn2">دیدن تعرفه‌ها</a>
      </div>
    </article>
  </section>
</main>

<footer class="pg-footer">
  <div class="pg-footer__inner">
    <div>
      <div class="pg-footer__brand">
        <img src="assets/brand/logo-footer.jpg" alt="سپهر ابجدیان" width="36" height="36">
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

<a class="call-fab" href="tel:+989044691979" aria-label="تماس فوری" data-i18n-aria-label="common.callAria">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
</a>

<script src="js/ambient.js?v=20260913"></script>
<script src="js/bottom-nav.js?v=20260913"></script>
<script src="js/ui.js?v=20260913"></script>
<script src="js/portfolio.js?v=20260913"></script>
</body>
</html>
