<?php
require_once __DIR__ . '/includes/user-auth.php';
require_once __DIR__ . '/includes/seo.php';
public_session_start();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8" />
  <link rel="icon" href="favicon.ico" sizes="any">
  <link rel="icon" href="assets/brand/icon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="assets/brand/apple-touch-icon.png">
  <!-- Applies the saved theme + language/direction before anything paints,
       so returning visitors never see a flash of the wrong theme or a
       layout that jumps from rtl to ltr after load. Kept tiny and safe. -->
  <script>
    (function () {
      try {
        var th = localStorage.getItem('portfolio-theme');
        document.documentElement.setAttribute('data-theme', (th === 'dark' || th === 'light') ? th : 'dark');
        var lg = localStorage.getItem('portfolio-language');
        lg = (lg === 'fa' || lg === 'en' || lg === 'ar') ? lg : 'fa';
        document.documentElement.setAttribute('lang', lg);
        document.documentElement.setAttribute('dir', lg === 'en' ? 'ltr' : 'rtl');
      } catch (e) { /* localStorage unavailable — dark/fa defaults already set in HTML */ }
    })();
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title data-i18n="home.title">محمد علی ابجدیان | متخصص هوش مصنوعی و پایتون</title>
  <meta name="description" data-i18n-content="home.metaDescription"
    content="رزومه و نمونه‌کارهای محمد علی ابجدیان، متخصص هوش مصنوعی، پایتون، دیپ لرنینگ و طراحی سایت فول‌استک." />
  <link rel="canonical" href="<?= e(site_url("/")) ?>" />
  <meta property="og:title" data-i18n-content="home.title" content="محمد علی ابجدیان | متخصص هوش مصنوعی و پایتون" />
  <meta property="og:description" data-i18n-content="home.metaDescription" content="رزومه و نمونه‌کارهای محمد علی ابجدیان، متخصص هوش مصنوعی، پایتون، دیپ لرنینگ و طراحی سایت فول‌استک." />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Sepehr Abjadian" />
  <meta property="og:locale" content="fa_IR" />
  <meta property="og:url" content="<?= e(site_url("/")) ?>" />
  <meta property="og:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" data-i18n-content="home.title" content="محمد علی ابجدیان | متخصص هوش مصنوعی و پایتون" />
  <meta name="twitter:description" data-i18n-content="home.metaDescription" content="رزومه و نمونه‌کارهای محمد علی ابجدیان، متخصص هوش مصنوعی، پایتون، دیپ لرنینگ و طراحی سایت فول‌استک." />
  <meta name="twitter:image" content="<?= e(site_url('/assets/brand/logo.jpeg')) ?>" />
  <!-- Hero portraits are the largest above-the-fold paint; preload both
       so the full-bleed hero shows up as early as possible. -->
  <link rel="preload" as="image" href="assets/hero/portrait-real.webp" fetchpriority="high" />
  <link rel="preload" as="image" href="assets/hero/portrait-neon.webp" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=20260913" />
  <link rel="stylesheet" href="css/pages-shared.css?v=20260913" />
  <link rel="stylesheet" href="css/bottom-nav.css?v=20260913" />
  <link rel="stylesheet" href="css/site-preferences.css?v=20260913" />
  <script defer src="js/i18n-data.js?v=20260913"></script>
  <script defer src="js/site-preferences.js?v=20260913"></script>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Person",
    "name": "Mohammad Ali Abjadian",
    "alternateName": "Sepehr Abjadian",
    "url": "<?= e(site_url("/")) ?>",
    "image": "<?= e(site_url("/assets/brand/logo.jpeg")) ?>",
    "jobTitle": "Full-Stack Developer & AI Specialist",
    "addressLocality": "Shiraz",
    "addressCountry": "IR",
    "email": "mailto:i88020023@gmail.com",
    "telephone": "+989044691979",
    "sameAs": [
      "https://www.instagram.com/s_abjadian",
      "https://t.me/sepehrworld_bot"
    ],
    "knowsAbout": ["Artificial Intelligence", "Machine Learning", "Deep Learning", "Python", "Full-Stack Web Development", "n8n Automation", "RAG", "AI Agents"]
  }
  </script>

  <!-- Cinematic 3D space background: Three.js + GSAP ScrollTrigger, loaded
       as native ES modules (no bundler). Must come before js/space-main.js. -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <script type="importmap">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.185.1/build/three.module.js",
      "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.185.1/examples/jsm/",
      "gsap": "https://cdn.jsdelivr.net/npm/gsap@3.15.0/index.js",
      "gsap/ScrollTrigger": "https://cdn.jsdelivr.net/npm/gsap@3.15.0/ScrollTrigger.js"
    }
  }
  </script>
  <script type="module" src="js/space-main.js?v=20260913"></script>
  <script type="module" src="js/portrait-hero.js?v=20260913"></script>
</head>

<body>

  <!-- Cinematic 3D space background (stars, nebula, Saturn). Fixed, full
       viewport, behind everything, never captures pointer events. Removed
       entirely by js/space-main.js if WebGL isn't available. -->
  <div class="space-scene" id="space-scene" aria-hidden="true">
    <canvas id="space-canvas"></canvas>
  </div>

  <!-- Custom Cursor -->
  <div class="cursor-dot"></div>
  <div class="cursor-ring"></div>

  <!-- Click ripple container -->
  <div id="ripple-layer"></div>

  <!-- Scroll progress -->
  <div class="scroll-progress"><span></span></div>

  <!-- Noise overlay -->
  <div class="noise"></div>

  <!-- Animated background blobs -->
  <div class="bg-orbs">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
  </div>

  <!-- NAV -->
  <nav class="glass-nav" id="nav">
    <a href="/" class="logo"><span>M.A</span> <span class="logo__full">Abjadian</span></a>
    <ul>
      <li><a href="/" data-i18n="nav.home">خانه</a></li>
      <li><a href="#about" data-i18n="nav.about">درباره</a></li>
      <li><a href="#services" data-i18n="nav.services">خدمات</a></li>
      <li><a href="/portfolio" data-i18n="nav.portfolio">نمونه‌کار</a></li>
      <li><a href="#certs" data-i18n="nav.certs">مدارک</a></li>
      <li><a href="#contact" data-i18n="nav.contact">تماس</a></li>
    </ul>
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
    <?= render_auth_button($currentUser) ?>
    <a href="#contact" class="cta-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span data-i18n="nav.cta">گفت‌وگو</span>
    </a>
  </nav>

  <!-- PORTRAIT HERO — animated/scroll/cursor-reactive portrait, first
       thing on the page. Logic in js/portrait-hero.js. -->
  <section class="portrait-hero" id="portraitHero" aria-label="سپهر">
    <div class="portrait-stage" id="portraitStage">
      <div class="portrait-glow" id="portraitGlow"></div>
      <img class="portrait-fallback" id="portraitImg" src="assets/hero/portrait-real.webp" alt="سپهر" />
      <canvas id="portraitGL" aria-hidden="true"></canvas>
      <div class="portrait-sweep" aria-hidden="true"></div>
      <div class="portrait-frame"></div>
      <div class="portrait-fade"></div>
    </div>
  </section>

  <!-- ABOUT -->
  <section class="about reveal" id="about">
    <div class="container about-center">
      <div class="about-text">
        <p class="kicker center">// ABOUT ME</p>
        <h2 class="center" data-i18n-html="home.aboutNameHtml">محمد علی <span class="grad">ابجدیان</span></h2>
        <p data-i18n="home.aboutP1">دانشجوی رشته شبکه و نرم‌افزار کامپیوتر و متخصص هوش مصنوعی. در حوزه دیپ‌لرنینگ، ماشین‌لرنینگ، تحلیل و علوم
          داده فعالیت می‌کنم و در کنار آن طراح وب فول‌استک هستم.</p>
        <p data-i18n-html="home.aboutP2Html">کار با <strong>REST API</strong> و JSON را به‌طور کامل می‌شناسم، ساخت <strong>ویندوز اپلیکیشن نیتیو با
            C#</strong> را انجام می‌دهم و در ساخت ایجنت‌های هوشمند تخصص دارم.</p>
        <div class="stats">
          <div><b>+50</b><span data-i18n="home.statProjects">پروژه</span></div>
          <div><b>+8</b><span data-i18n="home.statLangs">زبان برنامه‌نویسی</span></div>
          <div><b>7</b><span data-i18n="home.statCerts">مدرک معتبر</span></div>
          <div><b>+4</b><span data-i18n="home.statYears">سال تجربه</span></div>
        </div>

        <!-- SKILLS -->
        <p class="kicker center skills-block__kicker" data-i18n="home.skillsKicker">// PROFICIENCY</p>
        <h3 class="center skills-block__heading" data-i18n="home.skillsHeading">مهارت‌ها</h3>
        <div class="skill-bars">
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill1Label">وردپرس</span><b>95%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:95%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill2Label">HTML &amp; CSS</span><b>90%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:90%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill3Label">پایتون</span><b>85%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:85%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill4Label">بک‌اند (Flask / Django)</span><b>80%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:80%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill5Label">یادگیری ماشین</span><b>80%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:80%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill6Label">یادگیری عمیق و NLP</span><b>75%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:75%"></div></div></div>
          <div class="skill-bar"><div class="skill-bar__head"><span data-i18n="home.skill7Label">PHP</span><b>70%</b></div><div class="skill-bar__track"><div class="skill-bar__fill" style="--pct:70%"></div></div></div>
        </div>

        <!-- TIMELINE -->
        <p class="kicker center skills-block__kicker" data-i18n="home.timelineKicker">// EXPERIENCE</p>
        <h3 class="center skills-block__heading" data-i18n="home.timelineHeading">مسیر حرفه‌ای</h3>
        <div class="timeline">
          <div class="timeline__item">
            <div class="timeline__period" data-i18n="home.t1Period">۱۴۰۳ — اکنون</div>
            <div class="timeline__body">
              <h4 data-i18n="home.t1Title">طراح و توسعه‌دهنده ارشد · تحریردان</h4>
              <p data-i18n="home.t1Desc">طراحی و پیاده‌سازی کامل وب‌سایت تحریردان از صفر تا صد، شامل بک‌اند Flask، دیتابیس MySQL، طراحی UI سه‌بعدی شیشه‌ای، و بهینه‌سازی کامل عملکرد و سئو.</p>
            </div>
          </div>
          <div class="timeline__item">
            <div class="timeline__period" data-i18n="home.t2Period">۱۴۰۲ — ۱۴۰۳</div>
            <div class="timeline__body">
              <h4 data-i18n="home.t2Title">توسعه‌دهنده پایتون و هوش مصنوعی</h4>
              <p data-i18n="home.t2Desc">طراحی مدل‌های یادگیری ماشین، ساخت APIهای FastAPI و Flask، و توسعه‌ی سیستم‌های پیشنهاددهنده و چت‌بات‌های هوشمند.</p>
            </div>
          </div>
          <div class="timeline__item">
            <div class="timeline__period" data-i18n="home.t3Period">۱۴۰۱ — ۱۴۰۲</div>
            <div class="timeline__body">
              <h4 data-i18n="home.t3Title">طراح UI/UX سه‌بعدی</h4>
              <p data-i18n="home.t3Desc">طراحی رابط کاربری برای استارتاپ‌های مختلف با تمرکز بر تجربه‌های مدرن، شیشه‌ای، گرادیانتی و انیمیشن‌های روان.</p>
            </div>
          </div>
          <div class="timeline__item">
            <div class="timeline__period" data-i18n="home.t4Period">۱۴۰۰ — اکنون</div>
            <div class="timeline__body">
              <h4 data-i18n="home.t4Title">متخصص سئو و بهینه‌سازی عملکرد</h4>
              <p data-i18n="home.t4Desc">افزایش سرعت و رتبه‌ی گوگل برای ده‌ها سایت، بهینه‌سازی Core Web Vitals، و تولید محتوای حرفه‌ای و سئوشده.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- SERVICES -->
  <section class="services reveal" id="services">
    <div class="container">
      <p class="kicker center">// WHAT I DO</p>
      <h2 class="center" data-i18n-html="home.servicesHeadingHtml">خدمات <span class="grad">تخصصی</span></h2>
      <div class="grid services-grid">
        <article class="glass-card">
          <div class="icon">🧠</div>
          <h3 data-i18n="home.svc1Title">هوش مصنوعی و ایجنت</h3>
          <p data-i18n="home.svc1Desc">طراحی و آموزش مدل‌های یادگیری عمیق، ساخت ایجنت‌های LLM، RAG و اتوماسیون هوشمند.</p>
        </article>
        <article class="glass-card">
          <div class="icon">🐍</div>
          <h3 data-i18n="home.svc2Title">برنامه‌نویسی پایتون</h3>
          <p data-i18n="home.svc2Desc">Flask، Django، FastAPI، PyTorch، TensorFlow، Pandas — توسعه‌ی بک‌اند و علم داده.</p>
        </article>
        <article class="glass-card">
          <div class="icon">🌐</div>
          <h3 data-i18n="home.svc3Title">طراحی سایت فول‌استک</h3>
          <p data-i18n="home.svc3Desc">HTML/CSS/JS پیشرفته، React، Next.js، Three.js و وردپرس کاملاً کاستوم.</p>
        </article>
        <article class="glass-card">
          <div class="icon">⚙️</div>
          <h3 data-i18n="home.svc4Title">بک‌اند PHP و Python</h3>
          <p data-i18n="home.svc4Desc">طراحی REST API، معماری مقیاس‌پذیر، اتصال به دیتابیس و سرویس‌های ابری.</p>
        </article>
        <article class="glass-card">
          <div class="icon">🖥️</div>
          <h3 data-i18n="home.svc5Title">اپلیکیشن ویندوز با C#</h3>
          <p data-i18n="home.svc5Desc">ساخت نرم‌افزارهای دسکتاپ نیتیو و آفلاین با رابط مدرن و عملکرد بالا.</p>
        </article>
        <article class="glass-card">
          <div class="icon">📊</div>
          <h3 data-i18n="home.svc6Title">تحلیل و علوم داده</h3>
          <p data-i18n="home.svc6Desc">تحلیل داده، Visualization، مدل‌سازی آماری و گزارش‌های قابل عرضه به کسب‌وکار.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- SKILLS BAR -->
  <section class="skills reveal">
    <div class="container">
      <div class="marquee">
        <div class="track">
          <span>PYTHON</span><span>•</span><span>TENSORFLOW</span><span>•</span><span>PYTORCH</span><span>•</span><span>REACT</span><span>•</span><span>NEXT.JS</span><span>•</span><span>THREE.JS</span><span>•</span><span>DJANGO</span><span>•</span><span>FLASK</span><span>•</span><span>PHP</span><span>•</span><span>C#</span><span>•</span><span>JAVASCRIPT</span><span>•</span><span>WORDPRESS</span><span>•</span><span>REST
            API</span><span>•</span>
          <span>PYTHON</span><span>•</span><span>TENSORFLOW</span><span>•</span><span>PYTORCH</span><span>•</span><span>REACT</span><span>•</span><span>NEXT.JS</span><span>•</span><span>THREE.JS</span><span>•</span><span>DJANGO</span><span>•</span><span>FLASK</span><span>•</span><span>PHP</span><span>•</span><span>C#</span><span>•</span><span>JAVASCRIPT</span><span>•</span><span>WORDPRESS</span><span>•</span><span>REST
            API</span><span>•</span>
        </div>
      </div>
    </div>
  </section>

  <!-- WORKS -->
  <center>
    <section class="works reveal" id="works">
      <div class="container">
        <p class="kicker center">// SELECTED WORKS</p>
        <h2 class="center" data-i18n-html="home.worksHeadingHtml">نمونه <span class="grad">کارها</span></h2>
        <p class="center works-lead" data-i18n="home.worksLead">یک نمونه از پروژه‌های واقعی؛ نمونه‌کارهای کامل رو توی صفحه‌ی اختصاصی نمونه‌کارها ببین.</p>
        <div class="grid works-grid">
          <a href="screenshot-tahrirdan.webp" target="_blank" rel="noopener" class="work-card">
            <div class="thumb"><img src="screenshot-tahrirdan.webp" alt="تحریردان" loading="lazy" width="1200" height="611"></div>
            <div class="meta">
              <h3 data-i18n="home.w1Title">تحریردان — فروشگاه لوازم‌التحریر</h3><span>Flask · MySQL · Glass UI</span>
            </div>
          </a>
          <a href="https://t.me/sepehrworld_bot" target="_blank" rel="noopener" class="work-card">
            <div class="thumb work-card__glyph">🤖</div>
            <div class="meta">
              <h3 data-i18n="home.w2Title">دستیار هوش مصنوعی تلگرام</h3><span>n8n · Gemini · RAG</span>
            </div>
          </a>
          <a href="/portfolio" class="work-card">
            <div class="thumb work-card__glyph">🏎️</div>
            <div class="meta">
              <h3 data-i18n="home.w3Title">لندینگ مرسدس‌بنز CLS63 AMG</h3><span>Next.js 15 · R3F · GSAP</span>
            </div>
          </a>
        </div>
        <a href="/portfolio" class="cta-btn ghost works-see-all" data-i18n="home.worksSeeAllBtn">مشاهده همه نمونه‌کارها</a>
      </div>
    </section>
  </center>

  <!-- PORTFOLIO HIGHLIGHTS — top items, 3D coverflow carousel with click
       navigation, populated from api/home-highlights.php since this page
       is static HTML (no PHP). -->
  <section class="hl-section reveal" id="portfolioHighlights">
    <div class="container">
      <div class="hl-head">
        <h2 id="hlPortfolioTitle">برگزیده نمونه‌کارها</h2>
        <a href="/portfolio" class="hl-more" id="hlPortfolioMore"><span>مشاهده بیشتر</span></a>
      </div>
      <div class="hl-carousel">
        <button type="button" class="hl-nav hl-nav--prev" data-row="hlPortfolioRow" aria-label="قبلی" data-i18n-aria-label="common.prevLabel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
        </button>
        <div class="hl-row" id="hlPortfolioRow" aria-live="polite"></div>
        <button type="button" class="hl-nav hl-nav--next" data-row="hlPortfolioRow" aria-label="بعدی" data-i18n-aria-label="common.nextLabel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
      </div>
    </div>
  </section>

  <!-- CERTIFICATES -->
  <section class="certs reveal" id="certs">
    <div class="container">
      <p class="kicker center">// CREDENTIALS</p>
      <h2 class="center" data-i18n-html="home.certsHeadingHtml">مدارک <span class="grad">معتبر</span></h2>
      <div class="grid certs-grid">
        <div class="cert-card glass-card">
          <img src="cert-python.webp" alt="Deep Learning Certificate" loading="lazy" width="877" height="619">
          <h3>Python programming language</h3>
          <p>sololearn · 2022</p>
        </div>
        <div class="cert-card glass-card">
          <img src="cert-english.webp" alt="Python Certificate" loading="lazy" width="900" height="1160">
          <h3>Advansed English language</h3>
          <p>Navid group · 2023</p>
        </div>
        <div class="cert-card glass-card">
          <img src="cert-fullstack.webp" alt="Full Stack Certificate" loading="lazy" width="776" height="1045">
          <h3>Full-Stack Web Development - python . JS</h3>
          <p>CS50 - Harvard · 2025</p>
        </div>
        <div class="cert-card cert-card--badge glass-card">
          <div class="cert-card__glyph">🧠</div>
          <h3 data-i18n="home.cred4Title">Machine Learning Specialization</h3>
          <p data-i18n="home.cred4Issuer">Coursera · Andrew Ng · 2024</p>
        </div>
        <div class="cert-card cert-card--badge glass-card">
          <div class="cert-card__glyph">🕸️</div>
          <h3 data-i18n="home.cred5Title">Deep Learning Professional</h3>
          <p data-i18n="home.cred5Issuer">DeepLearning.AI · 2024</p>
        </div>
        <div class="cert-card cert-card--badge glass-card">
          <div class="cert-card__glyph">🐍</div>
          <h3 data-i18n="home.cred6Title">Advanced Python Programming</h3>
          <p data-i18n="home.cred6Issuer">Google Developers · 2023</p>
        </div>
        <div class="cert-card cert-card--badge glass-card">
          <div class="cert-card__glyph">🌐</div>
          <h3 data-i18n="home.cred7Title">Full-Stack Web Development</h3>
          <p data-i18n="home.cred7Issuer">Meta · 2023</p>
        </div>
      </div>
    </div>
  </section>

  <!-- BLOG HIGHLIGHTS — latest articles, same 3D coverflow carousel as the
       portfolio highlights section above. -->
  <section class="hl-section reveal" id="blogHighlights">
    <div class="container">
      <div class="hl-head">
        <h2 id="hlBlogTitle">تازه‌ترین مقاله‌ها</h2>
        <a href="/blog" class="hl-more" id="hlBlogMore"><span>مشاهده بیشتر</span></a>
      </div>
      <div class="hl-carousel">
        <button type="button" class="hl-nav hl-nav--prev" data-row="hlBlogRow" aria-label="قبلی" data-i18n-aria-label="common.prevLabel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
        </button>
        <div class="hl-row" id="hlBlogRow" aria-live="polite"></div>
        <button type="button" class="hl-nav hl-nav--next" data-row="hlBlogRow" aria-label="بعدی" data-i18n-aria-label="common.nextLabel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
      </div>
    </div>
  </section>

  <!-- CONTACT -->
  <section class="contact reveal" id="contact">
    <div class="container">
      <div class="glass-card big">
        <p class="kicker center">// LET'S BUILD</p>
        <h2 class="center" data-i18n-html="home.contactHeadingHtml">پروژه‌ای داری؟ <span class="grad">شروع کنیم.</span></h2>
        <p class="center lead" data-i18n="home.contactLead">اگر به دنبال متخصص هوش مصنوعی یا توسعه‌دهنده‌ی فول‌استک هستی، پیام بده.</p>
        <div class="contact-actions">
          <a href="mailto:i88020023@gmail.com" class="cta-btn primary" data-i18n="home.contactEmailBtn">ارسال ایمیل</a>
          <a href="/database/portfolio.zip" class="cta-btn ghost" data-i18n="home.contactResumeBtn">دانلود رزومه PDF</a>
        </div>
      </div>
    </div>
  </section>

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

  <a class="call-fab" href="tel:+989044691979" aria-label="تماس فوری" data-i18n-aria-label="common.callAria">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
</a>

<script src="script.js?v=20260913"></script>

  <!-- Bottom navigation — added on top of the existing page, connects Home
       to the new Blog / Services / Contact pages. Self-contained: only
       styles/scripts anything inside .liquid-nav (see css/bottom-nav.css
       and js/bottom-nav.js). Nothing above this comment was changed. -->
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
  <script type="module" src="js/home-highlights.js?v=20260913"></script>
</body>

</html>