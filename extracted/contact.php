<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start(); // same default session contact.php's csrf_token() always used — now also carries user login state
$currentUser = current_user();

$conn = db();
$settingsRows = $conn->query('SELECT setting_key, setting_value FROM site_settings');
$settings = [];
while ($row = $settingsRows->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$phone = $settings['contact_phone'] ?? '+989044691979';
$phoneDisplay = preg_replace('/^\+98/', '+98 ', $phone); // light formatting only, value itself comes from the DB
$email = $settings['contact_email'] ?? 'i88020023@gmail.com';
$instagramUrl = $settings['contact_instagram'] ?? 'https://www.instagram.com/s_abjadian';
$instagramHandle = preg_replace('#^https?://(www\.)?instagram\.com/#', '', rtrim($instagramUrl, '/'));
$telegramBot = $settings['contact_telegram_bot'] ?? 'https://t.me/sepehrworld_bot';
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
<title data-i18n="contact.title">تماس | سپهر ابجدیان — توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی</title>
<meta name="description" data-i18n-content="contact.metaDescription" content="برای شروع یک پروژه‌ی جدید در حوزه‌ی هوش مصنوعی، توسعه‌ی وب یا اتوماسیون، با سپهر ابجدیان در تماس باش.">
<meta property="og:title" data-i18n-content="contact.ogTitle" content="تماس | سپهر ابجدیان">
<meta property="og:description" data-i18n-content="contact.ogDescription" content="برای شروع پروژه‌ی بعدیت با سپهر ابجدیان در تماس باش.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<link rel="canonical" href="<?= e(site_url('/contact')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" data-i18n-content="contact.ogTitle" content="تماس | سپهر ابجدیان">
<meta name="twitter:description" data-i18n-content="contact.ogDescription" content="برای شروع پروژه‌ی بعدیت با سپهر ابجدیان در تماس باش.">
<meta name="twitter:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<script type="application/ld+json">
<?= breadcrumb_ld(['خانه' => '/', 'تماس' => '/contact']) ?>
</script>
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'ContactPage',
    'url' => site_url('/contact'),
    'about' => ['@type' => 'Person', '@id' => site_url('/') . '#person', 'name' => 'Sepehr Abjadian'],
]) ?>
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/pages-shared.css?v=20260913">
<link rel="stylesheet" href="css/contact.css?v=20260913">
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
    <div class="kicker page-hero__eyebrow">// LET'S TALK</div>
    <h1 data-i18n-html="contact.heroH1Html">بیایید پروژه بعدی رو <span class="grad">بسازیم</span></h1>
    <p data-i18n="contact.heroP">برای هوش مصنوعی، اتوماسیون یا یک وب‌سایت فول‌استک تازه، فرم زیر رو پر کن یا مستقیم ایمیل بزن.</p>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="contact-layout">
      <div class="glass-card contact-form reveal">
        <form novalidate data-csrf="<?= e(csrf_token()) ?>" data-endpoint="api/contact-submit.php">
          <!-- honeypot: hidden from real visitors via CSS (see contact.css), left blank by humans, filled in by bots -->
          <div class="field field--honeypot" aria-hidden="true">
            <label for="cf-website">Website</label>
            <input type="text" id="cf-website" name="website" tabindex="-1" autocomplete="off">
          </div>
          <div class="contact-form__grid">
            <div class="field">
              <label for="cf-name" data-i18n="contact.fName">نام و نام‌خانوادگی</label>
              <input type="text" id="cf-name" data-required>
              <span class="field__error" data-i18n="contact.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="field">
              <label for="cf-email" data-i18n="contact.fEmail">ایمیل</label>
              <input type="email" id="cf-email" data-required>
              <span class="field__error" data-i18n="contact.errEmail">یک ایمیل معتبر وارد کن</span>
            </div>
            <div class="field">
              <label for="cf-phone" data-i18n="contact.fPhone">تلفن (اختیاری)</label>
              <input type="tel" id="cf-phone">
            </div>
            <div class="field">
              <label for="cf-company" data-i18n="contact.fCompany">شرکت / کسب‌وکار (اختیاری)</label>
              <input type="text" id="cf-company">
            </div>
            <div class="field">
              <label for="cf-budget" data-i18n="contact.fBudget">بودجه‌ی تقریبی پروژه</label>
              <select id="cf-budget">
                <option value="small" data-i18n="contact.budgetSmall">کوچک</option>
                <option value="medium" data-i18n="contact.budgetMedium">متوسط</option>
                <option value="large" data-i18n="contact.budgetLarge">بزرگ</option>
                <option value="unknown" data-i18n="contact.budgetUnknown">هنوز مشخص نیست</option>
              </select>
            </div>
            <div class="field">
              <label for="cf-type" data-i18n="contact.fType">نوع پروژه</label>
              <select id="cf-type">
                <option value="web" data-i18n="contact.typeWeb">توسعه‌ی وب</option>
                <option value="ai" data-i18n="contact.typeAi">هوش مصنوعی و اتوماسیون</option>
                <option value="desktop" data-i18n="contact.typeDesktop">اپلیکیشن دسکتاپ</option>
                <option value="data" data-i18n="contact.typeData">تحلیل داده</option>
                <option value="other" data-i18n="contact.typeOther">چیز دیگه‌ای</option>
              </select>
            </div>
            <div class="field field--full">
              <label for="cf-message" data-i18n="contact.fMessage">پیام</label>
              <textarea id="cf-message" data-required placeholder="کمی درباره‌ی پروژه‌ات بگو…" data-i18n-placeholder="contact.fMessagePlaceholder"></textarea>
              <span class="field__error" data-i18n="contact.errRequired">این فیلد الزامیه</span>
            </div>
          </div>
          <button type="submit" class="cta-btn primary" data-i18n="contact.submit">ارسال پیام</button>
          <p class="contact-form__server-error" role="alert" data-i18n="contact.errServer">مشکلی پیش اومد. لطفاً دوباره تلاش کن یا مستقیم ایمیل بزن.</p>
        </form>
        <div class="contact-form__success">
          <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
          <h3 data-i18n="contact.successTitle">پیامت رسید!</h3>
          <p data-i18n="contact.successText">ممنون که وقت گذاشتی — در اسرع وقت جوابت رو می‌دم.</p>
        </div>
      </div>

      <div class="contact-cards">
        <div class="glass-card location-card reveal">
          <svg viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <p><strong data-i18n="contact.locationCity">شیراز، ایران</strong><span data-i18n="contact.locationDesc">در دسترس برای همکاری دورکاری با تیم‌ها و مشتری‌های داخل و خارج از ایران.</span></p>
        </div>

        <a href="mailto:<?= e($email) ?>" class="glass-card contact-card reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardEmailLabel">ایمیل</span>
            <span class="contact-card__value"><?= e($email) ?></span>
          </span>
        </a>

        <a href="tel:<?= e($phone) ?>" class="glass-card contact-card reveal" dir="ltr">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardPhoneLabel">تلفن</span>
            <span class="contact-card__value"><?= e($phoneDisplay) ?></span>
          </span>
        </a>

        <a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener" class="glass-card contact-card reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardInstagramLabel">اینستاگرام</span>
            <span class="contact-card__value"><?= e($instagramHandle) ?></span>
          </span>
        </a>

        <a href="<?= e($telegramBot) ?>" target="_blank" rel="noopener" class="glass-card contact-card reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4z"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardTelegramLabel">تلگرام</span>
            <span class="contact-card__value" data-i18n="contact.cardTelegramValue">شروع گفت‌وگو</span>
          </span>
        </a>

        <a href="/database/portfolio.zip" class="glass-card contact-card reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><path d="M4 19h16"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardResumeLabel">رزومه</span>
            <span class="contact-card__value" data-i18n="contact.cardResumeValue">دانلود PDF</span>
          </span>
        </a>

        <div class="glass-card contact-card is-pending reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 11a2 2 0 0 1 4 0v5"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardLinkedinLabel">لینکدین</span>
            <span class="contact-card__value" data-i18n="contact.comingSoon">به‌زودی</span>
          </span>
        </div>

        <div class="glass-card contact-card is-pending reveal">
          <span class="contact-card__icon"><svg viewBox="0 0 24 24"><path d="m8 9-5 3 5 3M16 9l5 3-5 3M13 6l-2 12"/></svg></span>
          <span class="contact-card__text">
            <span class="contact-card__label" data-i18n="contact.cardGithubLabel">گیت‌هاب</span>
            <span class="contact-card__value" data-i18n="contact.comingSoon">به‌زودی</span>
          </span>
        </div>
      </div>
    </div>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="pg-section__head reveal">
      <div class="kicker">// COMMON QUESTIONS</div>
      <h2 data-i18n="contact.faqHeading">سوالات متداول</h2>
    </div>
    <div class="faq-list reveal">
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="contact.faq1Q">چقدر طول می‌کشه تا جواب بدی؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="contact.faq1A">معمولاً ظرف ۲۴ ساعت کاری پاسخ می‌دم.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="contact.faq2Q">برای گفت‌وگوی اولیه هزینه‌ای هست؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="contact.faq2A">نه، گفت‌وگوی اولیه درباره‌ی پروژه‌ات کاملاً رایگان و بدون تعهده.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="contact.faq3Q">اگه هنوز ایده‌ام کامل مشخص نیست چی؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="contact.faq3A">مشکلی نیست؛ خیلی از پروژه‌ها همین‌طور شروع می‌شن. می‌تونیم با یک گفت‌وگوی کوتاه ایده رو با هم روشن‌تر کنیم.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="contact.faq4Q">بهترین راه ارتباطی کدومه؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="contact.faq4A">ایمیل بهترین و مطمئن‌ترین راهه؛ بعد از هماهنگی اولیه می‌تونیم کانال مناسب دیگه‌ای رو هم انتخاب کنیم.</p></div>
      </div>
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
        <li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
        <li><a href="tel:<?= e($phone) ?>" dir="ltr"><?= e($phoneDisplay) ?></a></li>
        <li><a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener">Instagram · <?= e($instagramHandle) ?></a></li>
        <li><a href="<?= e($telegramBot) ?>" target="_blank" rel="noopener" data-i18n="contact.cardTelegramLabel">دستیار تلگرام</a></li>
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
  <a href="/contact" class="liquid-nav__item is-active" data-page="contact">
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
<script src="js/contact-form.js?v=20260913"></script>
</body>
</html>
