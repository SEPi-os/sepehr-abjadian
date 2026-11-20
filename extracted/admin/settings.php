<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $form = $_POST['form'] ?? '';

    if ($form === 'settings') {
        $keys = ['contact_phone', 'contact_email', 'contact_instagram', 'contact_telegram_bot', 'site_name'];
        foreach ($keys as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = $conn->prepare('UPDATE site_settings SET setting_value=? WHERE setting_key=?');
            $stmt->bind_param('ss', $value, $key);
            $stmt->execute();
            $stmt->close();
        }
        flash_set('ok', 'تنظیمات ذخیره شد.');
        redirect('settings.php');
    }

    if ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $pwStmt = $conn->prepare('SELECT password FROM admins WHERE id=?');
        $pwStmt->bind_param('i', $admin['id']);
        $pwStmt->execute();
        $row = $pwStmt->get_result()->fetch_assoc();
        $pwStmt->close();

        if (!password_verify($current, $row['password'])) {
            $errors[] = 'رمز فعلی اشتباهه.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'رمز جدید باید حداقل ۸ کاراکتر باشه.';
        } elseif ($new !== $confirm) {
            $errors[] = 'تکرار رمز جدید مطابقت نداره.';
        } else {
            $hash = password_hash($new, PASSWORD_ARGON2ID);
            $stmt = $conn->prepare('UPDATE admins SET password=? WHERE id=?');
            $stmt->bind_param('si', $hash, $admin['id']);
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'رمز عبورت با موفقیت تغییر کرد.');
            redirect('settings.php');
        }
    }
}

$settingsRows = $conn->query('SELECT setting_key, setting_value FROM site_settings');
$settings = [];
while ($row = $settingsRows->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'تنظیمات سایت';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($errors): ?><div class="a-alert a-alert--error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="a-grid a-grid--2">
  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">اطلاعات تماس سایت</strong>
    <p style="color:var(--a-muted);font-size:12px;margin-top:-8px;margin-bottom:16px;">
      این مقادیر توی دیتابیس ذخیره می‌شن؛ برای اینکه واقعاً روی صفحات سایت هم نمایش داده بشن، فایل‌های PHP صفحات عمومی باید این‌ها رو از <code>site_settings</code> بخونن (به‌جای مقدار ثابت توی HTML).
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="settings">
      <div class="a-field"><label>نام سایت</label><input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></div>
      <div class="a-field"><label>تلفن</label><input type="text" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" dir="ltr"></div>
      <div class="a-field"><label>ایمیل</label><input type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>"></div>
      <div class="a-field"><label>اینستاگرام (لینک کامل)</label><input type="url" name="contact_instagram" value="<?= e($settings['contact_instagram'] ?? '') ?>"></div>
      <div class="a-field"><label>لینک دستیار تلگرام</label><input type="url" name="contact_telegram_bot" value="<?= e($settings['contact_telegram_bot'] ?? '') ?>"></div>
      <button type="submit" class="a-btn a-btn--primary">ذخیره</button>
    </form>
  </div>

  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">تغییر رمز عبور</strong>
    <p style="color:var(--a-warn);font-size:12px;margin-top:-8px;margin-bottom:16px;">
      اگه هنوز از رمز پیش‌فرض (ChangeMe#2026!) استفاده می‌کنی، همین الان تغییرش بده.
    </p>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="password">
      <div class="a-field"><label>رمز فعلی</label><input type="password" name="current_password" autocomplete="current-password" required></div>
      <div class="a-field"><label>رمز جدید (حداقل ۸ کاراکتر)</label><input type="password" name="new_password" autocomplete="new-password" required></div>
      <div class="a-field"><label>تکرار رمز جدید</label><input type="password" name="confirm_password" autocomplete="new-password" required></div>
      <button type="submit" class="a-btn a-btn--primary">تغییر رمز</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
