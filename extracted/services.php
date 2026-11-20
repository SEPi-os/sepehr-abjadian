<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
public_session_start();
$currentUser = current_user();
$servicesList = [
    ['name' => 'هوش مصنوعی و ایجنت‌های هوشمند', 'description' => 'طراحی، آموزش و استقرار مدل‌های یادگیری عمیق؛ ساخت ایجنت‌های مبتنی بر LLM، پیاده‌سازی RAG و اتوماسیون فرایندها.'],
    ['name' => 'برنامه‌نویسی و بک‌اند پایتون', 'description' => 'توسعه‌ی بک‌اند با Flask، Django و FastAPI؛ پردازش و تحلیل داده، و ساخت مدل با PyTorch و TensorFlow.'],
    ['name' => 'طراحی و توسعه‌ی وب فول‌استک', 'description' => 'رابط‌های کاربری مدرن با React و Next.js، جلوه‌های سه‌بعدی با Three.js و سایت‌های کاملاً سفارشی.'],
    ['name' => 'توسعه‌ی بک‌اند و API', 'description' => 'طراحی REST API با معماری مقیاس‌پذیر، اتصال به دیتابیس و سرویس‌های ابری، با PHP یا پایتون.'],
    ['name' => 'اپلیکیشن دسکتاپ', 'description' => 'نرم‌افزارهای دسکتاپ نیتیو با PySide6 و C#، شامل دستیارهای هوشمند با تشخیص صدا و معماری پلاگین‌محور.'],
    ['name' => 'تحلیل و علوم داده', 'description' => 'تحلیل داده، مصورسازی، مدل‌سازی آماری و تبدیل داده‌های خام به گزارش‌های قابل‌ارائه به کسب‌وکار.'],
];
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
<title data-i18n="services.title">خدمات | سپهر ابجدیان — توسعه‌دهنده فول‌استک و متخصص هوش مصنوعی</title>
<meta name="description" data-i18n-content="services.metaDescription" content="توسعه‌ی هوش مصنوعی و ایجنت‌های هوشمند، برنامه‌نویسی پایتون، وب فول‌استک، بک‌اند و API، اپلیکیشن دسکتاپ و تحلیل داده — با فرایندی شفاف از ایده تا استقرار.">
<meta property="og:title" data-i18n-content="services.ogTitle" content="خدمات | سپهر ابجدیان">
<meta property="og:description" data-i18n-content="services.ogDescription" content="هوش مصنوعی، پایتون، وب فول‌استک، بک‌اند و اپلیکیشن دسکتاپ — از ایده تا استقرار.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<link rel="canonical" href="<?= e(site_url('/services')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" data-i18n-content="services.ogTitle" content="خدمات | سپهر ابجدیان">
<meta name="twitter:description" data-i18n-content="services.ogDescription" content="هوش مصنوعی، پایتون، وب فول‌استک، بک‌اند و اپلیکیشن دسکتاپ — از ایده تا استقرار.">
<meta name="twitter:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>">
<script type="application/ld+json">
<?= breadcrumb_ld(['خانه' => '/', 'خدمات' => '/services']) ?>
</script>
<script type="application/ld+json">
<?= json_ld([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    '@id' => site_url('/services') . '#services',
    'itemListElement' => array_values(array_map(function ($i, $s) {
        return [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => [
                '@type' => 'Service',
                'name' => $s['name'],
                'description' => $s['description'],
                'provider' => ['@type' => 'Person', '@id' => site_url('/') . '#person', 'name' => 'Sepehr Abjadian'],
            ],
        ];
    }, array_keys($servicesList), $servicesList)),
]) ?>
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/pages-shared.css?v=20260913">
<link rel="stylesheet" href="css/services.css?v=20260913">
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

<!-- No top .glass-nav on this page (only index.php has one) — this small
     floating pill carries the same two controls in the same screen
     position, styled with the same glass tokens (see css/site-preferences.css). -->
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
    <div class="kicker page-hero__eyebrow">// WHAT I OFFER</div>
    <h1 data-i18n="services.heroH1">خدمات</h1>
    <p data-i18n="services.heroP">از یک ایجنت هوش مصنوعی گرفته تا یک وب‌سایت فول‌استک کامل — هرچی که بسازیم، از ایده تا استقرار کنارتم.</p>
  </section>

  <section class="pg-section">
    <div class="services-grid">
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">🧠</div>
        <h3 data-i18n="services.c1Title">هوش مصنوعی و ایجنت‌های هوشمند</h3>
        <p data-i18n="services.c1Desc">طراحی، آموزش و استقرار مدل‌های یادگیری عمیق؛ ساخت ایجنت‌های مبتنی بر LLM، پیاده‌سازی RAG و اتوماسیون فرایندها.</p>
        <ul>
          <li data-i18n="services.c1Li1">طراحی و آموزش مدل‌های یادگیری عمیق</li>
          <li data-i18n="services.c1Li2">ساخت ایجنت‌های LLM و RAG</li>
          <li data-i18n="services.c1Li3">اتوماسیون فرایندها با n8n</li>
          <li data-i18n="services.c1Li4">یکپارچه‌سازی با API‌های هوش مصنوعی</li>
        </ul>
      </article>
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">🐍</div>
        <h3 data-i18n="services.c2Title">برنامه‌نویسی و بک‌اند پایتون</h3>
        <p data-i18n="services.c2Desc">توسعه‌ی بک‌اند با Flask، Django و FastAPI؛ پردازش و تحلیل داده، و ساخت مدل با PyTorch و TensorFlow.</p>
        <ul>
          <li data-i18n="services.c2Li1">Flask / Django / FastAPI</li>
          <li data-i18n="services.c2Li2">طراحی دیتابیس و ORM</li>
          <li data-i18n="services.c2Li3">پردازش داده با Pandas</li>
          <li data-i18n="services.c2Li4">مدل‌سازی با PyTorch و TensorFlow</li>
        </ul>
      </article>
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">🌐</div>
        <h3 data-i18n="services.c3Title">طراحی و توسعه‌ی وب فول‌استک</h3>
        <p data-i18n="services.c3Desc">رابط‌های کاربری مدرن با React و Next.js، جلوه‌های سه‌بعدی با Three.js و سایت‌های کاملاً سفارشی.</p>
        <ul>
          <li data-i18n="services.c3Li1">React و Next.js</li>
          <li data-i18n="services.c3Li2">جلوه‌های سه‌بعدی با Three.js</li>
          <li data-i18n="services.c3Li3">انیمیشن با GSAP و Framer Motion</li>
          <li data-i18n="services.c3Li4">وردپرس کاملاً کاستوم</li>
        </ul>
      </article>
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">⚙️</div>
        <h3 data-i18n="services.c4Title">توسعه‌ی بک‌اند و API</h3>
        <p data-i18n="services.c4Desc">طراحی REST API با معماری مقیاس‌پذیر، اتصال به دیتابیس و سرویس‌های ابری، با PHP یا پایتون.</p>
        <ul>
          <li data-i18n="services.c4Li1">طراحی REST API</li>
          <li data-i18n="services.c4Li2">معماری مقیاس‌پذیر</li>
          <li data-i18n="services.c4Li3">اتصال به دیتابیس و سرویس ابری</li>
          <li data-i18n="services.c4Li4">PHP و پایتون</li>
        </ul>
      </article>
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">🖥️</div>
        <h3 data-i18n="services.c5Title">اپلیکیشن دسکتاپ</h3>
        <p data-i18n="services.c5Desc">نرم‌افزارهای دسکتاپ نیتیو با PySide6 و C#، شامل دستیارهای هوشمند با تشخیص صدا و معماری پلاگین‌محور.</p>
        <ul>
          <li data-i18n="services.c5Li1">PySide6 (پایتون)</li>
          <li data-i18n="services.c5Li2">اپلیکیشن‌های نیتیو C#</li>
          <li data-i18n="services.c5Li3">تشخیص صدا و دستور صوتی</li>
          <li data-i18n="services.c5Li4">معماری پلاگین‌محور</li>
        </ul>
      </article>
      <article class="glass-card service-card reveal">
        <div class="service-card__glyph">📊</div>
        <h3 data-i18n="services.c6Title">تحلیل و علوم داده</h3>
        <p data-i18n="services.c6Desc">تحلیل داده، مصورسازی، مدل‌سازی آماری و تبدیل داده‌های خام به گزارش‌های قابل‌ارائه به کسب‌وکار.</p>
        <ul>
          <li data-i18n="services.c6Li1">تحلیل و پاکسازی داده</li>
          <li data-i18n="services.c6Li2">مصورسازی داده</li>
          <li data-i18n="services.c6Li3">مدل‌سازی آماری و یادگیری‌ماشین</li>
          <li data-i18n="services.c6Li4">گزارش‌های تصمیم‌محور</li>
        </ul>
      </article>
    </div>
  </section>

  <section class="pg-section">
    <div class="pg-section__head reveal">
      <div class="kicker">// HOW WE BUILD</div>
      <h2 data-i18n="services.processHeading">فرایند کار</h2>
      <p data-i18n="services.processP">یک مسیر شفاف و قابل پیگیری، از اولین گفت‌وگو تا روزی که پروژه رو تحویل می‌گیری.</p>
    </div>
    <div class="process">
      <div class="process-step reveal">
        <div class="process-step__num" data-i18n="services.step1Num">۱</div>
        <div class="process-step__body">
          <h3 data-i18n="services.step1Title">کشف و تحلیل نیاز</h3>
          <p data-i18n="services.step1Desc">گفت‌وگو درباره‌ی هدف پروژه، کاربران نهایی و محدودیت‌های فنی؛ خروجی این مرحله یک بریف روشنه که هر دو طرف روش توافق داریم.</p>
        </div>
      </div>
      <div class="process-step reveal">
        <div class="process-step__num" data-i18n="services.step2Num">۲</div>
        <div class="process-step__body">
          <h3 data-i18n="services.step2Title">طراحی و نمونه‌سازی</h3>
          <p data-i18n="services.step2Desc">تعریف معماری فنی، انتخاب استک مناسب پروژه و طراحی اولیه‌ی رابط کاربری پیش از شروع توسعه‌ی اصلی.</p>
        </div>
      </div>
      <div class="process-step reveal">
        <div class="process-step__num" data-i18n="services.step3Num">۳</div>
        <div class="process-step__body">
          <h3 data-i18n="services.step3Title">توسعه</h3>
          <p data-i18n="services.step3Desc">پیاده‌سازی تدریجی و ماژولار، با به‌روزرسانی‌های منظم تا همیشه بدونی پروژه دقیقاً کجای مسیره.</p>
        </div>
      </div>
      <div class="process-step reveal">
        <div class="process-step__num" data-i18n="services.step4Num">۴</div>
        <div class="process-step__body">
          <h3 data-i18n="services.step4Title">تست و بهینه‌سازی</h3>
          <p data-i18n="services.step4Desc">بررسی عملکرد، واکنش‌گرایی روی دستگاه‌های مختلف و رفع باگ‌ها پیش از انتشار نهایی.</p>
        </div>
      </div>
      <div class="process-step reveal">
        <div class="process-step__num" data-i18n="services.step5Num">۵</div>
        <div class="process-step__body">
          <h3 data-i18n="services.step5Title">استقرار و پشتیبانی</h3>
          <p data-i18n="services.step5Desc">راه‌اندازی نهایی روی سرور یا پلتفرم موردنظرت، به‌همراه پشتیبانی بعد از تحویل برای رفع مشکلات و تغییرات کوچک.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="pg-section__head reveal">
      <div class="kicker">// TECH STACK</div>
      <h2 data-i18n="services.techHeading">ابزارهایی که باهاشون کار می‌کنم</h2>
    </div>
    <div class="tech-grid reveal">
      <span class="chip">Python</span><span class="chip">TensorFlow</span><span class="chip">PyTorch</span>
      <span class="chip">React</span><span class="chip">Next.js</span><span class="chip">Three.js</span>
      <span class="chip">Django</span><span class="chip">Flask</span><span class="chip">FastAPI</span>
      <span class="chip">PHP</span><span class="chip">C#</span><span class="chip">JavaScript</span>
      <span class="chip">WordPress</span><span class="chip">REST API</span><span class="chip">n8n</span>
      <span class="chip">PySide6</span><span class="chip">GSAP</span>
    </div>
  </section>

  <section class="pg-section">
    <div class="pg-section__head reveal">
      <div class="kicker">// PRICING</div>
      <h2 data-i18n="services.pricingHeading">تعرفه‌ی خدمات</h2>
      <p data-i18n="services.pricingHeadP">بازه‌ی قیمت هر خدمت، برای شفافیت کامل از همون اول. قیمت نهایی بر اساس دامنه‌ی دقیق پروژه مشخص می‌شه.</p>
    </div>
    <div class="pricing-tabs" role="tablist" aria-label="دسته‌بندی تعرفه‌ها">
      <button type="button" class="chip is-active" data-tab="website" data-i18n="services.tabWebsite">وب‌سایت</button>
      <button type="button" class="chip" data-tab="ai" data-i18n="services.tabAi">هوش مصنوعی</button>
      <button type="button" class="chip" data-tab="infra" data-i18n="services.tabInfra">سئو، سرعت و امنیت</button>
      <button type="button" class="chip" data-tab="plans" data-i18n="services.tabPlans">پلن‌های آماده</button>
    </div>
    <div class="pricing-panel" id="pricingPanel">
      <!-- Populated by js/pricing-render.js from js/pricing-data.js -->
    </div>
    <p class="pricing-note" data-i18n="services.pricingNote">همه‌ی قیمت‌ها به تومان و تقریبی‌ان؛ رقم نهایی بعد از بررسی دقیق نیازهای پروژه، توی پیش‌فاکتور رایگان اعلام می‌شه.</p>
  </section>

  <section class="pg-section">
    <div class="pg-section__head reveal">
      <div class="kicker">// WHY WORK WITH ME</div>
      <h2 data-i18n="services.whyHeading">چرا با من کار کنی؟</h2>
    </div>
    <div class="why-grid">
      <div class="glass-card why-item reveal">
        <div class="why-item__icon"><svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div><h3 data-i18n="services.why1Title">تجربه‌ی عملی روی پروژه‌های واقعی</h3><p data-i18n="services.why1Desc">نه فقط پروژه‌های آموزشی؛ محصولاتی که واقعاً استفاده می‌شن — از دستیار هوش مصنوعی تا فروشگاه آنلاین.</p></div>
      </div>
      <div class="glass-card why-item reveal">
        <div class="why-item__icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <div><h3 data-i18n="services.why2Title">ارتباط دوزبانه و شفاف</h3><p data-i18n="services.why2Desc">فارسی و انگلیسی، با گزارش پیشرفت منظم — همیشه می‌دونی پروژه دقیقاً کجاست.</p></div>
      </div>
      <div class="glass-card why-item reveal">
        <div class="why-item__icon"><svg viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></svg></div>
        <div><h3 data-i18n="services.why3Title">از ایده تا استقرار</h3><p data-i18n="services.why3Desc">یک نفر مسئول کل مسیر پروژه‌ست — از معماری تا رابط کاربری، بدون واسطه‌های اضافه.</p></div>
      </div>
      <div class="glass-card why-item reveal">
        <div class="why-item__icon"><svg viewBox="0 0 24 24"><path d="m18 16 4-4-4-4M6 8l-4 4 4 4M14.5 4l-5 16"/></svg></div>
        <div><h3 data-i18n="services.why4Title">کدی قابل نگه‌داری</h3><p data-i18n="services.why4Desc">معماری تمیز و مستندسازی مناسب، تا پروژه بعداً هم به‌راحتی قابل توسعه بمونه.</p></div>
      </div>
    </div>
  </section>

  <section class="pg-section pg-section--tight">
    <div class="pg-section__head reveal">
      <div class="kicker">// COMMON QUESTIONS</div>
      <h2 data-i18n="services.faqHeading">سوالات متداول</h2>
    </div>
    <div class="faq-list reveal">
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="services.faq1Q">هزینه‌ی یک پروژه چقدره؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="services.faq1A">بسته به دامنه، پیچیدگی و مدت‌زمان پروژه فرق می‌کنه. بعد از یک گفت‌وگوی اولیه‌ی کوتاه، یک پیش‌فاکتور دقیق و شفاف برات ارسال می‌کنم.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="services.faq2Q">یک پروژه معمولاً چقدر طول می‌کشه؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="services.faq2A">پروژه‌های کوچک معمولاً چند روز تا دو هفته، و پروژه‌های بزرگ‌تر چند هفته تا چند ماه زمان می‌برن. بازه‌ی دقیق در مرحله‌ی برنامه‌ریزی مشخص می‌شه.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="services.faq3Q">با مشتری‌های خارج از ایران هم همکاری می‌کنی؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="services.faq3A">بله، به فارسی و انگلیسی، و با هماهنگی ساعت کاری مناسب برای هر دو طرف.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="services.faq4Q">بعد از تحویل، پشتیبانی هم هست؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="services.faq4A">بله. برای رفع باگ و تغییرات کوچک بعد از تحویل، بسته به توافق پروژه پشتیبانی ارائه می‌شه.</p></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-item__q"><span data-i18n="services.faq5Q">می‌تونم فقط بخشی از پروژه رو بسپارم؟</span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
        <div class="faq-item__a"><p data-i18n="services.faq5A">کاملاً. می‌تونیم فقط روی یک بخش مشخص (مثلاً بک‌اند یا مدل هوش مصنوعی) کار کنیم یا با تیم فعلی‌ات هماهنگ بشیم.</p></div>
      </div>
    </div>
  </section>

  <section class="pg-section">
    <div class="glass-card big pricing-cta reveal">
      <h3 data-i18n="services.pricingH3">پروژه‌ای در ذهن داری؟</h3>
      <p data-i18n="services.pricingP">برای دریافت پیش‌فاکتور دقیق و رایگان، کافیه کمی درباره‌ی پروژه‌ات بگی.</p>
      <div class="cta-row">
        <a href="/contact" class="cta-btn primary" data-i18n="services.pricingCta1">درخواست پروژه</a>
        <a href="/database/portfolio.zip" class="cta-btn ghost" data-i18n="services.pricingCta2">دانلود رزومه PDF</a>
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
<script src="js/bottom-nav.js?v=20260913"></script>
<script src="js/ui.js?v=20260913"></script>
<script src="js/pricing-data.js?v=20260913"></script>
<script src="js/pricing-render.js?v=20260913"></script>
</body>
</html>
