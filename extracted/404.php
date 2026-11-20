<?php
// Reached either directly or via Apache's ErrorDocument 404 directive
// (see .htaccess). Explicitly set here too so the status is correct
// regardless of how the page was reached.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="icon" href="favicon.ico" sizes="any">
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
<title data-i18n="notFound.title">صفحه پیدا نشد (۴۰۴) | سپهر ابجدیان</title>
<meta name="description" data-i18n-content="notFound.metaDescription" content="صفحه‌ای که دنبالش بودی پیدا نشد. از منوی زیر به صفحه اصلی، نمونه‌کارها یا وبلاگ برو.">
<meta name="robots" content="noindex, follow">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/pages-shared.css?v=20260913">
<link rel="stylesheet" href="css/bottom-nav.css?v=20260913">
<link rel="stylesheet" href="css/site-preferences.css?v=20260913">
<script defer src="js/i18n-data.js?v=20260913"></script>
<script defer src="js/site-preferences.js?v=20260913"></script>
<style>
  .nf-wrap { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 120px 20px 100px; }
  .nf-card { max-width: 480px; text-align: center; padding: 40px 32px; border-radius: 24px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(20px); }
  .nf-code { font-family: 'Space Grotesk', sans-serif; font-size: 72px; font-weight: 700; line-height: 1; background: linear-gradient(135deg,#5d8bff,#9d6bff); -webkit-background-clip: text; background-clip: text; color: transparent; margin: 0 0 12px; }
  .nf-card h1 { font-size: 20px; margin: 0 0 10px; }
  .nf-card p { opacity: .75; margin: 0 0 28px; font-size: 14.5px; line-height: 1.8; }
  .nf-links { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
  .nf-links a { padding: 10px 18px; border-radius: 999px; text-decoration: none; font-size: 14px; border: 1px solid rgba(255,255,255,.12); color: inherit; transition: transform .15s ease, background .15s ease; }
  .nf-links a:hover { transform: translateY(-2px); background: rgba(255,255,255,.06); }
  .nf-links a.primary { background: linear-gradient(135deg,#5d8bff,#9d6bff); border: none; color: #fff; }
</style>
</head>
<body class="pg-body">

<div class="nf-wrap">
  <div class="nf-card">
    <div class="nf-code">404</div>
    <h1 data-i18n="notFound.heading">این صفحه پیدا نشد</h1>
    <p data-i18n="notFound.body">لینکی که دنبالش کردی جابه‌جا شده یا وجود نداره. از این‌جا می‌تونی به بخش‌های اصلی سایت برگردی.</p>
    <div class="nf-links">
      <a href="/" class="primary" data-i18n="bottomNav.home">خانه</a>
      <a href="/portfolio" data-i18n="bottomNav.portfolio">نمونه‌کار</a>
      <a href="/blog" data-i18n="bottomNav.blog">وبلاگ</a>
      <a href="/contact" data-i18n="bottomNav.contact">تماس</a>
    </div>
  </div>
</div>

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

<script src="js/bottom-nav.js?v=20260913"></script>
</body>
</html>
