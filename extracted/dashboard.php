<?php
/**
 * dashboard.php — the regular-visitor equivalent of the admin panel: one
 * page, two tabs (profile info / password), reusing the exact same visual
 * system as account.php (same 3D canvas, same glass-card + tab mechanics,
 * same tokens) so it never feels like a bolted-on extra. Reuses js/account.js
 * as-is (tabs, password-visibility toggle, inline validation are all
 * generic — see that file's header comment) and only adds js/dashboard.js
 * for the one thing account.js doesn't do: confirming logout.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/user-auth.php';
require_once __DIR__ . '/includes/store-core.php';

$user = require_public_login(); // redirects anonymous visitors to account.php

// Store summary for the dashboard header (silently hidden if the store tables don't exist yet).
$storeStats = null;
try {
    $storeStats = [
        'orders' => (int) store_val('SELECT COUNT(*) FROM shop_orders WHERE user_id = ?', 'i', [(int) $user['id']]),
        'purchases' => (int) store_val("SELECT COUNT(*) FROM shop_order_items oi JOIN shop_orders o ON o.id = oi.order_id WHERE o.user_id = ? AND o.payment_status = 'paid'", 'i', [(int) $user['id']]),
        'reviews' => (int) store_val('SELECT COUNT(*) FROM shop_reviews WHERE user_id = ?', 'i', [(int) $user['id']]),
    ];
} catch (Throwable $storeEx) {
    $storeStats = null;
}

$mode = ($_GET['tab'] ?? '') === 'security' ? 'security' : 'profile';
$profileError = '';
$profileFieldError = '';
$profileSuccess = false;
$securityError = '';
$securitySuccess = false;
$formValues = ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'] ?? ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if ($form === 'logout') {
        if (csrf_verify()) {
            user_logout();
        }
        redirect('index.php');
    } elseif ($form === 'profile') {
        $mode = 'profile';
        $formValues = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ];
        if (!csrf_verify()) {
            $profileError = 'server';
        } else {
            $result = update_user_profile((int) $user['id'], $formValues['name'], $formValues['email'], $formValues['phone']);
            if ($result['ok']) {
                $user = current_user();
                $formValues = ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'] ?? ''];
                $profileSuccess = true;
            } else {
                $profileError = $result['error'];
                $profileFieldError = $result['field'] ?? '';
            }
        }
    } elseif ($form === 'security') {
        $mode = 'security';
        if (!csrf_verify()) {
            $securityError = 'server';
        } else {
            $result = change_user_password(
                (int) $user['id'],
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['confirm_new_password'] ?? ''
            );
            $securitySuccess = $result['ok'];
            if (!$result['ok']) {
                $securityError = $result['error'];
            }
        }
    }
}

$conn = db();
$stmt = $conn->prepare('SELECT created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$createdAt = $stmt->get_result()->fetch_assoc()['created_at'] ?? null;
$stmt->close();
$memberSinceDisplay = $createdAt ? format_post_date(date('Y-m-d', strtotime($createdAt)), 'fa') : '';

$profileErrKey = match ($profileError) {
    'invalid_email' => 'Email',
    'email_taken' => '', // handled separately below (dashboard.* namespace, not auth.*)
    'server' => 'Server',
    'missing_fields' => 'Required',
    default => '',
};

$securityErrKey = match ($securityError) {
    'weak_password' => 'PasswordWeak',
    'password_mismatch' => 'PasswordMismatch',
    'server' => 'Server',
    default => '', // wrong_current handled separately (dashboard.* namespace)
};

$csrfToken = csrf_token();
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
<title data-i18n="dashboard.title">داشبورد کاربری | سپهر ابجدیان</title>
<meta name="description" data-i18n-content="dashboard.metaDescription" content="مدیریت اطلاعات و تنظیمات حساب کاربری.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="<?= e(site_url('/dashboard')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/site-preferences.css?v=20260913">
<link rel="stylesheet" href="css/account.css?v=20260913">
<link rel="stylesheet" href="css/bottom-nav.css?v=20260913">
<link rel="stylesheet" href="css/dashboard.css?v=20260913">
<script defer src="js/i18n-data.js?v=20260913"></script>
<script defer src="js/store-i18n.js?v=20261010"></script>
<script defer src="js/site-preferences.js?v=20260913"></script>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.185.1/build/three.module.js",
    "gsap": "https://cdn.jsdelivr.net/npm/gsap@3.15.0/index.js"
  }
}
</script>
</head>
<body class="account-body dashboard-body">

<div class="noise"></div>

<canvas id="authCanvas" class="account-canvas" aria-hidden="true"></canvas>

<a href="/" class="account-back">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  <span data-i18n="nav.home">خانه</span>
</a>

<div class="pref-bar account-pref-bar">
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

<main class="account-stage dash-stage">
  <div class="account-card-wrap dash-card-wrap">
    <div class="account-card glass-card dash-card">

      <div class="dash-head">
        <div class="dash-avatar" aria-hidden="true"><span><?= e(mb_substr($user['name'], 0, 1)) ?></span></div>
        <div class="dash-head__text">
          <h1><span data-i18n="dashboard.welcomeBack">خوش برگشتی،</span> <span class="dash-name"><?= e($user['name']) ?></span></h1>
          <p class="dash-meta">
            <span data-i18n="dashboard.memberSince">عضویت از</span> <span><?= e($memberSinceDisplay) ?></span>
            <span class="dash-dot" aria-hidden="true">•</span>
            <span class="dash-status-badge" data-i18n="dashboard.statusActive">فعال</span>
          </p>
        </div>
      </div>


      <?php if ($storeStats !== null): ?>
      <div class="dash-store">
        <h2 class="dash-store__title" data-i18n="store.dashStore">فروشگاه من</h2>
        <div class="dash-store__grid">
          <a class="dash-store__card" href="/dashboard/purchases">
            <b><?= fa_digits((string) $storeStats['purchases']) ?></b>
            <span data-i18n="store.navPurchases">خریدها و دانلودها</span>
            <small data-i18n="store.dashPurchasesHint">دانلود فایل‌ها و لایسنس‌ها</small>
          </a>
          <a class="dash-store__card" href="/dashboard/orders">
            <b><?= fa_digits((string) $storeStats['orders']) ?></b>
            <span data-i18n="store.navOrders">سفارش‌ها</span>
            <small data-i18n="store.dashOrdersHint">پیگیری و تاریخچه‌ی سفارش‌ها</small>
          </a>
          <a class="dash-store__card" href="/dashboard/reviews">
            <b><?= fa_digits((string) $storeStats['reviews']) ?></b>
            <span data-i18n="store.navReviews">نظرات من</span>
            <small data-i18n="store.dashReviewsHint">نظرهای ثبت‌شده توسط تو</small>
          </a>
        </div>
      </div>
      <?php endif; ?>

      <div class="account-tabs" role="tablist">
        <span class="account-tabs__indicator" id="tabIndicator" aria-hidden="true"></span>
        <button type="button" class="account-tabs__btn<?= $mode === 'profile' ? ' is-active' : '' ?>" data-tab="profile" role="tab" aria-selected="<?= $mode === 'profile' ? 'true' : 'false' ?>" data-i18n="dashboard.tabProfile">اطلاعات حساب</button>
        <button type="button" class="account-tabs__btn<?= $mode === 'security' ? ' is-active' : '' ?>" data-tab="security" role="tab" aria-selected="<?= $mode === 'security' ? 'true' : 'false' ?>" data-i18n="dashboard.tabSecurity">امنیت</button>
      </div>

      <div class="account-panels">
        <!-- PROFILE -->
        <section class="account-panel<?= $mode === 'profile' ? ' is-active' : '' ?>" data-panel="profile">
          <?php if ($profileSuccess): ?>
            <div class="account-alert account-alert--success" data-i18n="dashboard.successProfile">اطلاعات با موفقیت به‌روزرسانی شد.</div>
          <?php elseif ($profileError === 'email_taken'): ?>
            <div class="account-alert" data-i18n="dashboard.errEmailTaken">این ایمیل قبلاً ثبت شده.</div>
          <?php elseif ($profileError): ?>
            <div class="account-alert" data-i18n="auth.err<?= $profileErrKey ?>">خطا</div>
          <?php endif; ?>

          <form method="post" class="account-form" novalidate>
            <input type="hidden" name="form" value="profile">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="a-field-3d">
              <label for="dp-username" data-i18n="auth.fUsername">نام کاربری</label>
              <input type="text" id="dp-username" value="<?= e($user['username']) ?>" disabled>
            </div>
            <div class="a-field-3d">
              <label for="dp-name" data-i18n="auth.fName">نام و نام‌خانوادگی</label>
              <input type="text" id="dp-name" name="name" value="<?= e($formValues['name']) ?>" data-required>
              <span class="a-field-3d__error" data-i18n="auth.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="a-field-3d">
              <label for="dp-email" data-i18n="auth.fEmail">ایمیل</label>
              <input type="email" id="dp-email" name="email" value="<?= e($formValues['email']) ?>" data-required data-pattern="email">
              <span class="a-field-3d__error" data-i18n="auth.errEmail">ایمیل نامعتبر</span>
            </div>
            <div class="a-field-3d">
              <label for="dp-phone" data-i18n="auth.fPhone">تلفن (اختیاری)</label>
              <input type="tel" id="dp-phone" name="phone" value="<?= e($formValues['phone']) ?>">
            </div>
            <button type="submit" class="account-submit"><span data-i18n="dashboard.saveChanges">ذخیره تغییرات</span></button>
          </form>
        </section>

        <!-- SECURITY -->
        <section class="account-panel<?= $mode === 'security' ? ' is-active' : '' ?>" data-panel="security">
          <?php if ($securitySuccess): ?>
            <div class="account-alert account-alert--success" data-i18n="dashboard.successPassword">رمز عبور با موفقیت تغییر کرد.</div>
          <?php elseif ($securityError === 'wrong_current'): ?>
            <div class="account-alert" data-i18n="dashboard.errCurrentPasswordWrong">رمز عبور فعلی اشتباهه.</div>
          <?php elseif ($securityError): ?>
            <div class="account-alert" data-i18n="auth.err<?= $securityErrKey ?>">خطا</div>
          <?php endif; ?>

          <form method="post" class="account-form" novalidate>
            <input type="hidden" name="form" value="security">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="a-field-3d a-field-3d--password">
              <label for="dp-current" data-i18n="dashboard.currentPassword">رمز عبور فعلی</label>
              <input type="password" id="dp-current" name="current_password" autocomplete="current-password" data-required>
              <button type="button" class="a-pw-toggle" data-target="dp-current" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="a-field-3d a-field-3d--password">
              <label for="dp-new" data-i18n="dashboard.newPassword">رمز عبور جدید</label>
              <input type="password" id="dp-new" name="new_password" autocomplete="new-password" data-required data-pattern="password">
              <button type="button" class="a-pw-toggle" data-target="dp-new" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errPasswordWeak">رمز عبور ضعیفه</span>
            </div>
            <div class="a-field-3d a-field-3d--password">
              <label for="dp-confirm" data-i18n="dashboard.confirmNewPassword">تکرار رمز عبور جدید</label>
              <input type="password" id="dp-confirm" name="confirm_new_password" autocomplete="new-password" data-required data-pattern="match:dp-new">
              <button type="button" class="a-pw-toggle" data-target="dp-confirm" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errPasswordMismatch">تکرار رمز عبور یکی نیست</span>
            </div>
            <button type="submit" class="account-submit"><span data-i18n="dashboard.changePasswordBtn">تغییر رمز عبور</span></button>
          </form>
        </section>
      </div>

      <form method="post" class="dash-logout-form" id="logoutForm">
        <input type="hidden" name="form" value="logout">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <button type="submit" class="dash-logout-btn" data-i18n="auth.logoutBtn">خروج از حساب</button>
      </form>
    </div>
  </div>
</main>

<!-- Same bottom navigation as every other page, so switching between the
     dashboard and the rest of the site is a single tap either direction. -->
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
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8M12 16v4"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.services">خدمات</span>
  </a>
  <a href="/contact" class="liquid-nav__item" data-page="contact">
    <span class="liquid-nav__icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
    <span class="liquid-nav__label" data-i18n="bottomNav.contact">تماس</span>
  </a>
</nav>

<script type="module" src="js/account-3d.js?v=20260913"></script>
<script src="js/account.js?v=20260913"></script>
<script src="js/bottom-nav.js?v=20260913"></script>
<script src="js/dashboard.js?v=20260913"></script>
</body>
</html>
