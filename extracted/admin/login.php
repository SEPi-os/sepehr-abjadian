<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
admin_session_start();

if (current_admin()) {
    redirect('index.php');
}

$error = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'invalid_form';
    } elseif (!bot_check_ok($_POST['website'] ?? '', 'admin_login_form_ts')) {
        $error = 'invalid'; // same generic message as wrong credentials — doesn't hint at the bot check
    } else {
        $token = $_POST['g-recaptcha-response'] ?? '';
        $result = attempt_admin_login($_POST['username'] ?? '', $_POST['password'] ?? '', $token);
        if ($result['ok']) {
            redirect('index.php');
        }
        $error = $result['error'];
    }
}

bot_check_stamp('admin_login_form_ts');

$errorMessages = [
    'empty' => 'نام کاربری و رمز عبور رو وارد کن.',
    'invalid' => 'نام کاربری یا رمز عبور اشتباهه.',
    'locked' => 'به دلیل تلاش‌های ناموفق زیاد، ورود موقتاً قفل شده. چند دقیقه دیگه دوباره امتحان کن.',
    'invalid_form' => 'فرم منقضی شده، دوباره تلاش کن.',
    'captcha' => 'لطفاً تیک «من ربات نیستم» رو بزن.',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود مدیر | سپهر ابجدیان</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=20260913">
</head>
<body class="admin-body">
  <div class="a-login-wrap">
    <div class="a-login-card">
      <div class="a-card">
        <h1>ورود به پنل مدیریت</h1>
        <p class="a-sub">سپهر ابجدیان — Portfolio CMS</p>

        <?php if ($timeout && !$error): ?>
          <div class="a-alert a-alert--error">به دلیل عدم فعالیت، از حساب خارج شدی. دوباره وارد شو.</div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="a-alert a-alert--error"><?= e($errorMessages[$error] ?? 'خطایی رخ داد.') ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="a-field">
            <label for="username">نام کاربری</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
          </div>
          <div class="a-field">
            <label for="password">رمز عبور</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <div class="a-hp-field" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>
          <?php if (recaptcha_enabled()): ?>
            <div class="a-field"><?= recaptcha_widget() ?></div>
          <?php endif; ?>
          <button type="submit" class="a-btn a-btn--primary" style="width:100%;justify-content:center;padding:11px;">ورود</button>
        </form>
      </div>
    </div>
  </div>
  <?= recaptcha_script_tag() ?>
</body>
</html>
