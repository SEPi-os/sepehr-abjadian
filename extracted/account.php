<?php
require_once __DIR__ . '/includes/user-auth.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/store-core.php'; // store_safe_next()
public_session_start();

// Where to send the visitor after login/register (e.g. back to /checkout). Only a short
// allow-list of internal store/account paths is ever accepted — never an external URL.
$next = store_safe_next($_GET['next'] ?? '');

// Already signed in? Don't show a login form for no reason.
if (current_user()) {
    redirect($next !== '' ? $next : 'index.php');
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$loginError = '';
$registerError = '';
$registerFieldError = '';
$showTimeout = isset($_GET['timeout']);
$formValues = ['name' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if ($form === 'login') {
        $mode = 'login';
        if (!csrf_verify()) {
            $loginError = 'server';
        } elseif (!bot_check_ok($_POST['website'] ?? '', 'account_login_form_ts')) {
            $loginError = 'invalid'; // generic — never hints that a bot check exists
        } else {
            $token = $_POST['g-recaptcha-response'] ?? '';
            $result = attempt_public_login($_POST['identifier'] ?? '', $_POST['password'] ?? '', $token);
            if ($result['ok']) {
                redirect($result['type'] === 'admin' ? 'admin/index.php' : ($next !== '' ? $next : 'index.php'));
            }
            $loginError = $result['error'];
        }
    } elseif ($form === 'register') {
        $mode = 'register';
        $formValues = [
            'name' => trim($_POST['name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ];
        if (!csrf_verify()) {
            $registerError = 'server';
        } elseif (!bot_check_ok($_POST['website'] ?? '', 'account_register_form_ts')) {
            $registerError = 'server';
        } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
            $registerError = 'password_mismatch';
            $registerFieldError = 'confirm_password';
        } else {
            $token = $_POST['g-recaptcha-response'] ?? '';
            $result = register_user($formValues['name'], $formValues['username'], $formValues['email'], $_POST['password'] ?? '', $formValues['phone'], $token);
            if ($result['ok']) {
                redirect($next !== '' ? $next : 'index.php');
            }
            $registerError = $result['error'];
            $registerFieldError = $result['field'] ?? '';
        }
    }
}

// Both panels are always present in the DOM (the tabs are a client-side
// CSS toggle, not separate page loads), so both get a fresh honeypot/
// timing stamp on every render regardless of which tab is visible.
bot_check_stamp('account_login_form_ts');
bot_check_stamp('account_register_form_ts');

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
<title data-i18n="auth.title">ورود یا ثبت‌نام | سپهر ابجدیان</title>
<meta name="description" data-i18n-content="auth.metaDescription" content="وارد حساب کاربری‌ات شو یا یک حساب جدید بساز.">
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="<?= e(site_url('/account')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;800;900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=20260913">
<link rel="stylesheet" href="css/site-preferences.css?v=20260913">
<link rel="stylesheet" href="css/account.css?v=20260913">
<script defer src="js/i18n-data.js?v=20260913"></script>
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
<body class="account-body">

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

<main class="account-stage">
  <div class="account-card-wrap" id="cardWrap">
    <div class="account-card glass-card" id="authCard">

      <div class="account-brand">
        <img src="assets/brand/logo.jpeg" alt="Sepehr Abjadian">
      </div>

      <div class="account-tabs" role="tablist">
        <span class="account-tabs__indicator" id="tabIndicator" aria-hidden="true"></span>
        <button type="button" class="account-tabs__btn<?= $mode === 'login' ? ' is-active' : '' ?>" data-tab="login" role="tab" aria-selected="<?= $mode === 'login' ? 'true' : 'false' ?>" data-i18n="auth.loginTab">ورود</button>
        <button type="button" class="account-tabs__btn<?= $mode === 'register' ? ' is-active' : '' ?>" data-tab="register" role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>" data-i18n="auth.registerTab">ثبت‌نام</button>
      </div>

      <div class="account-panels">
        <!-- LOGIN -->
        <section class="account-panel<?= $mode === 'login' ? ' is-active' : '' ?>" data-panel="login">
          <div class="kicker" data-i18n="auth.loginKicker">// WELCOME BACK</div>
          <h1 data-i18n="auth.loginHeading">خوش برگشتی</h1>
          <p class="account-sub" data-i18n="auth.loginSub">برای ادامه وارد حساب کاربری‌ات شو.</p>

          <?php if ($showTimeout && !$loginError): ?>
            <div class="account-alert" data-i18n="auth.errTimeout">به دلیل عدم فعالیت، از حساب خارج شدی.</div>
          <?php endif; ?>
          <?php if ($loginError): ?>
            <div class="account-alert" data-i18n="auth.err<?= match ($loginError) {
                'server' => 'Server', 'locked' => 'Locked', 'empty' => 'Required',
                'captcha' => 'Captcha', default => 'InvalidLogin',
            } ?>">خطا</div>
          <?php endif; ?>

          <form method="post" class="account-form" novalidate>
            <input type="hidden" name="form" value="login">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="a-field-3d">
              <label for="li-identifier" data-i18n="auth.fIdentifier">نام کاربری یا ایمیل</label>
              <input type="text" id="li-identifier" name="identifier" autocomplete="username" data-required>
              <span class="a-field-3d__error" data-i18n="auth.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="a-field-3d a-field-3d--password">
              <label for="li-password" data-i18n="auth.fPassword">رمز عبور</label>
              <input type="password" id="li-password" name="password" autocomplete="current-password" data-required>
              <button type="button" class="a-pw-toggle" data-target="li-password" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="account-hp-field" aria-hidden="true">
              <label for="li-website">Website</label>
              <input type="text" id="li-website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <?php if (recaptcha_enabled()): ?>
              <div class="account-recaptcha"><?= recaptcha_widget() ?></div>
            <?php endif; ?>
            <button type="submit" class="account-submit"><span data-i18n="auth.loginBtn">ورود به حساب</span></button>
          </form>

          <p class="account-switch">
            <span data-i18n="auth.switchToRegister">حساب نداری؟</span>
            <button type="button" data-switch-to="register" data-i18n="auth.switchToRegisterLink">بساز</button>
          </p>
        </section>

        <!-- REGISTER -->
        <section class="account-panel<?= $mode === 'register' ? ' is-active' : '' ?>" data-panel="register">
          <div class="kicker" data-i18n="auth.registerKicker">// JOIN IN</div>
          <h1 data-i18n="auth.registerHeading">بساز، شروع کن</h1>
          <p class="account-sub" data-i18n="auth.registerSub">چند ثانیه‌ای؛ یک حساب جدید بساز و همین الان وارد شو.</p>

          <?php if ($registerError): ?>
            <div class="account-alert" data-i18n="auth.err<?= match ($registerError) {
                'server' => 'Server', 'missing_fields' => 'Required', 'invalid_username' => 'UsernameFormat',
                'invalid_email' => 'Email', 'weak_password' => 'PasswordWeak', 'password_mismatch' => 'PasswordMismatch',
                'duplicate' => 'Duplicate', 'captcha' => 'Captcha', default => 'Server',
            } ?>">خطا</div>
          <?php endif; ?>

          <form method="post" class="account-form" novalidate>
            <input type="hidden" name="form" value="register">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="a-field-3d">
              <label for="rg-name" data-i18n="auth.fName">نام و نام‌خانوادگی</label>
              <input type="text" id="rg-name" name="name" autocomplete="name" value="<?= e($formValues['name']) ?>" data-required>
              <span class="a-field-3d__error" data-i18n="auth.errRequired">این فیلد الزامیه</span>
            </div>
            <div class="a-field-3d">
              <label for="rg-username" data-i18n="auth.fUsername">نام کاربری</label>
              <input type="text" id="rg-username" name="username" autocomplete="username" value="<?= e($formValues['username']) ?>" data-required data-pattern="username">
              <span class="a-field-3d__error" data-i18n="auth.errUsernameFormat">فرمت نامعتبر</span>
            </div>
            <div class="a-field-3d">
              <label for="rg-email" data-i18n="auth.fEmail">ایمیل</label>
              <input type="email" id="rg-email" name="email" autocomplete="email" value="<?= e($formValues['email']) ?>" data-required data-pattern="email">
              <span class="a-field-3d__error" data-i18n="auth.errEmail">ایمیل نامعتبر</span>
            </div>
            <div class="a-field-3d">
              <label for="rg-phone" data-i18n="auth.fPhone">تلفن (اختیاری)</label>
              <input type="tel" id="rg-phone" name="phone" autocomplete="tel" value="<?= e($formValues['phone']) ?>">
            </div>
            <div class="a-field-3d a-field-3d--password">
              <label for="rg-password" data-i18n="auth.fPassword">رمز عبور</label>
              <input type="password" id="rg-password" name="password" autocomplete="new-password" data-required data-pattern="password">
              <button type="button" class="a-pw-toggle" data-target="rg-password" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errPasswordWeak">رمز عبور ضعیفه</span>
            </div>
            <div class="a-field-3d a-field-3d--password">
              <label for="rg-confirm" data-i18n="auth.fConfirmPassword">تکرار رمز عبور</label>
              <input type="password" id="rg-confirm" name="confirm_password" autocomplete="new-password" data-required data-pattern="match:rg-password">
              <button type="button" class="a-pw-toggle" data-target="rg-confirm" aria-label="نمایش رمز عبور" data-i18n-aria-label="auth.showPassword">
                <svg class="a-pw-toggle__eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="a-pw-toggle__eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.5 21.5 0 0 1 5.06-6.06M9.9 4.24A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.5M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
              <span class="a-field-3d__error" data-i18n="auth.errPasswordMismatch">مطابقت نداره</span>
            </div>
            <div class="account-hp-field" aria-hidden="true">
              <label for="rg-website">Website</label>
              <input type="text" id="rg-website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <?php if (recaptcha_enabled()): ?>
              <div class="account-recaptcha"><?= recaptcha_widget() ?></div>
            <?php endif; ?>
            <button type="submit" class="account-submit"><span data-i18n="auth.registerBtn">ساخت حساب کاربری</span></button>
          </form>

          <p class="account-switch">
            <span data-i18n="auth.switchToLogin">قبلاً حساب ساختی؟</span>
            <button type="button" data-switch-to="login" data-i18n="auth.switchToLoginLink">وارد شو</button>
          </p>
        </section>
      </div>
    </div>
  </div>
</main>

<script type="module" src="js/account-3d.js?v=20260913"></script>
<script src="js/account.js?v=20260913"></script>
<?= recaptcha_script_tag() ?>
</body>
</html>
