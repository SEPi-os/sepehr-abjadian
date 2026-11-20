<?php
/**
 * store-layout.php — shared page chrome for every store page, so the 14
 * new pages look exactly like the existing site (same head scripts, glass
 * pref-bar with theme/language switchers, footer, floating bottom nav,
 * call FAB) without copy-pasting that markup into each file.
 * All asset URLs are root-absolute because store URLs are nested (/shop/slug).
 */
require_once __DIR__ . '/store-core.php';
require_once __DIR__ . '/user-auth.php';

const STORE_ASSET_V = '20261010';

/** Signed-in user for store pages that REQUIRE login (cart/checkout/dashboard parts). Redirects with a vetted ?next=. */
function store_require_user(string $next = ''): array
{
    public_session_start();
    $user = current_user();
    $nextQ = ($n = store_safe_next($next)) !== '' ? '?next=' . rawurlencode($n) : '';
    if (!$user) {
        redirect('/account' . $nextQ);
    }
    if (isset($_SESSION['user_last_activity']) && (time() - $_SESSION['user_last_activity']) > USER_IDLE_TIMEOUT_SECONDS) {
        user_logout();
        redirect('/account?timeout=1' . ($nextQ !== '' ? '&' . substr($nextQ, 1) : ''));
    }
    $_SESSION['user_last_activity'] = time();
    // A user disabled by the admin loses access immediately, even mid-session.
    $row = store_row('SELECT id, status FROM users WHERE id = ?', 'i', [(int) $user['id']]);
    if (!$row || $row['status'] !== 'active') {
        user_logout();
        redirect('/account');
    }
    return $user;
}

function store_bottom_nav(string $active): string
{
    $items = [
        ['home', '/', 'bottomNav.home', 'خانه', '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/>'],
        ['shop', '/shop', 'bottomNav.shop', 'فروشگاه', '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>'],
        ['portfolio', '/portfolio', 'bottomNav.portfolio', 'نمونه‌کار', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
        ['blog', '/blog', 'bottomNav.blog', 'وبلاگ', '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>'],
        ['services', '/services', 'bottomNav.services', 'خدمات', '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/>'],
        ['contact', '/contact', 'bottomNav.contact', 'تماس', '<path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2z"/>'],
    ];
    $h = '<nav class="liquid-nav" aria-label="پیمایش اصلی" data-i18n-aria-label="bottomNav.ariaMain"><span class="liquid-nav__indicator" aria-hidden="true"></span>';
    foreach ($items as [$key, $href, $i18n, $label, $svg]) {
        $h .= '<a href="' . $href . '" class="liquid-nav__item' . ($key === $active ? ' is-active' : '') . '" data-page="' . $key . '"><span class="liquid-nav__icon"><svg viewBox="0 0 24 24">' . $svg . '</svg></span><span class="liquid-nav__label" data-i18n="' . $i18n . '">' . $label . '</span></a>';
    }
    return $h . '</nav>';
}

/**
 * @param array{title:string,description?:string,canonical?:?string,robots?:string,og_image?:?string,og_type?:string,
 *              jsonld?:string[],css?:string[],js?:string[],nav?:string,user?:?array,title_key?:string,store_cfg?:bool} $o
 */
function store_layout_start(array $o): void
{
    $GLOBALS['store_layout_js'] = $o['js'] ?? [];   // page scripts passed to _start() are printed by _end()
    $title = $o['title'];
    $desc = $o['description'] ?? '';
    $robots = $o['robots'] ?? 'index,follow';
    $canonical = $o['canonical'] ?? null;
    $ogImage = $o['og_image'] ?? site_url('/assets/brand/logo.jpeg');
    $user = array_key_exists('user', $o) ? $o['user'] : (public_session_start() ?? current_user());
    $cfg = [
        'currency' => ['fa' => store_currency_label('fa'), 'en' => store_currency_label('en'), 'ar' => store_currency_label('ar')],
        'digits' => store_setting('shop_price_digits', 'fa'),
        'loggedIn' => (bool) $user,
        'maxQty' => store_setting_int('shop_max_qty_per_item', 5),
    ];
    header('X-Content-Type-Options: nosniff');
    if ($robots !== 'index,follow') {
        header('X-Robots-Tag: ' . $robots);
    }
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/assets/brand/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
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
<title<?= !empty($o['title_key']) ? ' data-i18n="' . e($o['title_key']) . '"' : '' ?>><?= e($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e($desc) ?>"><?php endif; ?>

<meta name="robots" content="<?= e($robots) ?>">
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>

<meta property="og:title" content="<?= e($o['og_title'] ?? $title) ?>">
<?php if ($desc !== ''): ?><meta property="og:description" content="<?= e($o['og_description'] ?? $desc) ?>"><?php endif; ?>

<meta property="og:type" content="<?= e($o['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="Sepehr Abjadian">
<meta property="og:locale" content="fa_IR">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($o['og_title'] ?? $title) ?>">
<?php if ($desc !== ''): ?><meta name="twitter:description" content="<?= e($o['og_description'] ?? $desc) ?>"><?php endif; ?>

<meta name="twitter:image" content="<?= e($ogImage) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css?v=<?= STORE_ASSET_V ?>">
<link rel="stylesheet" href="/css/pages-shared.css?v=<?= STORE_ASSET_V ?>">
<link rel="stylesheet" href="/css/bottom-nav.css?v=<?= STORE_ASSET_V ?>">
<link rel="stylesheet" href="/css/site-preferences.css?v=<?= STORE_ASSET_V ?>">
<link rel="stylesheet" href="/css/store.css?v=<?= STORE_ASSET_V ?>">
<?php foreach ($o['css'] ?? [] as $c): ?><link rel="stylesheet" href="<?= e($c) ?>?v=<?= STORE_ASSET_V ?>">
<?php endforeach; ?>
<script defer src="/js/i18n-data.js?v=<?= STORE_ASSET_V ?>"></script>
<script defer src="/js/store-i18n.js?v=<?= STORE_ASSET_V ?>"></script>
<script defer src="/js/site-preferences.js?v=<?= STORE_ASSET_V ?>"></script>
<script>window.STORE_CFG = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php foreach ($o['jsonld'] ?? [] as $ld): ?><script type="application/ld+json">
<?= $ld ?>

</script>
<?php endforeach; ?>
</head>
<body class="store-page">

<div class="noise"></div>
<div class="bg-orbs"><span class="orb-1"></span><span class="orb-2"></span><span class="orb-3"></span></div>
<div class="scroll-progress"></div>
<div class="cursor-dot"></div>
<div class="cursor-ring"></div>
<div id="ripple-layer"></div>

<div class="pref-bar">
  <?= render_auth_button($user) ?>
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
<?php
}

function store_layout_end(array $o = []): void
{
    $nav = $o['nav'] ?? 'shop';
    ?>
</main>

<footer class="pg-footer">
  <div class="pg-footer__inner">
    <div>
      <div class="pg-footer__brand">
        <img src="/assets/brand/logo-footer.jpg" alt="سپهر ابجدیان" width="36" height="36">
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

<?= store_bottom_nav($nav) ?>

<a class="call-fab" href="tel:+989044691979" aria-label="تماس فوری" data-i18n-aria-label="common.callAria">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
</a>

<div class="toast-host" id="toastHost" aria-live="polite" aria-atomic="true"></div>
<script src="/js/ambient.js?v=<?= STORE_ASSET_V ?>"></script>
<script src="/js/bottom-nav.js?v=<?= STORE_ASSET_V ?>"></script>
<script src="/js/ui.js?v=<?= STORE_ASSET_V ?>"></script>
<script src="/js/store.js?v=<?= STORE_ASSET_V ?>"></script>
<?php foreach (array_merge($GLOBALS['store_layout_js'] ?? [], $o['js'] ?? []) as $j): ?><script src="<?= e($j) ?>?v=<?= STORE_ASSET_V ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** One-time flash banner (reuses the session flash helpers). */
function store_flash_html(): string
{
    $f = flash_get();
    if (!$f) {
        return '';
    }
    return '<div class="store-flash store-flash--' . e($f['type']) . '" role="status">' . e($f['message']) . '</div>';
}

function store_breadcrumb_html(array $crumbs): string
{
    $h = '<nav class="crumbs" aria-label="breadcrumb"><ol>';
    $last = array_key_last($crumbs);
    foreach ($crumbs as $label => $href) {
        $h .= '<li>' . ($label === $last || $href === null ? '<span aria-current="page">' . $label . '</span>' : '<a href="' . e($href) . '">' . $label . '</a>') . '</li>';
    }
    return $h . '</ol></nav>';
}


/** Friendly full-page message (errors, empty states) with the correct HTTP status. */
function store_message_page(int $status, string $title, string $message, array $actions = []): never
{
    http_response_code($status);
    store_no_cache();
    store_layout_start(['title' => $title . ' | سپهر ابجدیان', 'robots' => 'noindex,nofollow']);
    echo '<section class="pg-section store-section"><div class="empty-state glass-card"><div class="empty-state__icon" aria-hidden="true">' . ($status >= 500 ? '⚠️' : 'ℹ️') . '</div><h1>' . e($title) . '</h1><p>' . e($message) . '</p><div class="cta-card__actions">';
    foreach ($actions as $label => $href) {
        echo '<a class="cta-btn" href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</div></div></section>';
    store_layout_end(['nav' => 'shop']);
    exit;
}

/** Tabs shared by the buyer's account pages. */
function store_account_nav(string $active): string
{
    $tabs = ['overview' => ['/dashboard', 'store.navOverview', 'پروفایل'], 'purchases' => ['/dashboard/purchases', 'store.navPurchases', 'خریدها و دانلودها'], 'orders' => ['/dashboard/orders', 'store.navOrders', 'سفارش‌ها'], 'reviews' => ['/dashboard/reviews', 'store.navReviews', 'نظرات من']];
    $h = '<nav class="store-tabs" aria-label="حساب کاربری">';
    foreach ($tabs as $k => [$href, $key, $label]) {
        $h .= '<a href="' . $href . '" class="' . ($k === $active ? 'is-active' : '') . '" data-i18n="' . $key . '">' . $label . '</a>';
    }
    return $h . '</nav>';
}
