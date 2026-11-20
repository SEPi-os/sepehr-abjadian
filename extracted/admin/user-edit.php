<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$user = $id ? $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc() : null;
if ($id && !$user) {
    flash_set('error', 'کاربر پیدا نشد.');
    redirect('users.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $status = $_POST['status'] === 'disabled' ? 'disabled' : 'active';
    $password = $_POST['password'] ?? '';

    if ($name === '' || $username === '' || $email === '') {
        $errors[] = 'نام، یوزرنیم و ایمیل الزامی‌ان.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل معتبر نیست.';
    }
    if (!$user && $password === '') {
        $errors[] = 'برای کاربر جدید، رمز عبور الزامیه.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشه.';
    }

    if (!$errors) {
        $dupStmt = $conn->prepare('SELECT id FROM users WHERE (username=? OR email=?) AND id != ?');
        $notId = $id ?: 0;
        $dupStmt->bind_param('ssi', $username, $email, $notId);
        $dupStmt->execute();
        if ($dupStmt->get_result()->fetch_assoc()) {
            $errors[] = 'این یوزرنیم یا ایمیل قبلاً استفاده شده.';
        }
        $dupStmt->close();
    }

    if (!$errors) {
        if ($user) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $conn->prepare('UPDATE users SET name=?, username=?, email=?, phone=?, status=?, password=? WHERE id=?');
                $stmt->bind_param('ssssssi', $name, $username, $email, $phone, $status, $hash, $id);
            } else {
                $stmt = $conn->prepare('UPDATE users SET name=?, username=?, email=?, phone=?, status=? WHERE id=?');
                $stmt->bind_param('sssssi', $name, $username, $email, $phone, $status, $id);
            }
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'کاربر ذخیره شد.');
        } else {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $conn->prepare('INSERT INTO users (name, username, email, phone, status, password) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('ssssss', $name, $username, $email, $phone, $status, $hash);
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'کاربر ایجاد شد.');
        }
        redirect('users.php');
    }
}

$pageTitle = $user ? 'ویرایش کاربر' : 'کاربر جدید';
require __DIR__ . '/_layout_top.php';
$v = fn($f) => e($user[$f] ?? ($_POST[$f] ?? ''));
?>

<?php if ($errors): ?><div class="a-alert a-alert--error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="a-card" style="max-width:520px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="a-field"><label>نام</label><input type="text" name="name" value="<?= $v('name') ?>" required></div>
    <div class="a-field"><label>یوزرنیم</label><input type="text" name="username" value="<?= $v('username') ?>" required></div>
    <div class="a-field"><label>ایمیل</label><input type="email" name="email" value="<?= $v('email') ?>" required></div>
    <div class="a-field"><label>تلفن (اختیاری)</label><input type="tel" name="phone" value="<?= $v('phone') ?>"></div>
    <div class="a-field"><label>وضعیت</label>
      <select name="status">
        <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
        <option value="disabled" <?= ($user['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>غیرفعال</option>
      </select>
    </div>
    <div class="a-field"><label><?= $user ? 'رمز عبور جدید (خالی = بدون تغییر)' : 'رمز عبور' ?></label><input type="password" name="password" autocomplete="new-password"></div>
    <div class="a-btn-row">
      <button type="submit" class="a-btn a-btn--primary">ذخیره</button>
      <a href="users.php" class="a-btn">انصراف</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
