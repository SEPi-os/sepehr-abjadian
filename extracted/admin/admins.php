<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

// Only the owner role can manage other admin accounts — an editor-level
// admin shouldn't be able to create new owner accounts or lock out others.
$isOwner = $admin['role'] === 'owner';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    if (!$isOwner) {
        flash_set('error', 'فقط مالک (owner) می‌تونه مدیران رو مدیریت کنه.');
        redirect('admins.php');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id === (int) $admin['id']) {
            flash_set('error', 'نمی‌تونی حساب خودت رو حذف کنی.');
        } else {
            $stmt = $conn->prepare('DELETE FROM admins WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'مدیر حذف شد.');
        }
        redirect('admins.php');
    }

    if ($action === 'toggle_status') {
        if ($id === (int) $admin['id']) {
            flash_set('error', 'نمی‌تونی حساب خودت رو غیرفعال کنی.');
        } else {
            $row = $conn->query('SELECT status FROM admins WHERE id=' . $id)->fetch_assoc();
            $new = ($row['status'] ?? 'active') === 'active' ? 'disabled' : 'active';
            $conn->query("UPDATE admins SET status = '$new' WHERE id = $id");
        }
        redirect('admins.php');
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] === 'owner' ? 'owner' : 'editor';
        $password = $_POST['password'] ?? '';

        if ($name === '' || $username === '' || $email === '' || strlen($password) < 8) {
            $errors[] = 'نام، یوزرنیم، ایمیل الزامی‌ان و رمز عبور باید حداقل ۸ کاراکتر باشه.';
        } else {
            $dup = $conn->prepare('SELECT id FROM admins WHERE username=? OR email=?');
            $dup->bind_param('ss', $username, $email);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()) {
                $errors[] = 'این یوزرنیم یا ایمیل قبلاً استفاده شده.';
            }
            $dup->close();
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $conn->prepare('INSERT INTO admins (name, username, email, password, role) VALUES (?,?,?,?,?)');
            $stmt->bind_param('sssss', $name, $username, $email, $hash, $role);
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'مدیر جدید ثبت شد.');
            redirect('admins.php');
        }
    }
}

$admins = $conn->query('SELECT id, name, username, email, role, status, last_login FROM admins ORDER BY id');
$pageTitle = 'مدیران سایت';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($errors): ?><div class="a-alert a-alert--error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="a-grid a-grid--2">
  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">مدیر جدید</strong>
    <?php if (!$isOwner): ?>
      <p style="color:var(--a-muted);font-size:13px;">فقط حساب owner می‌تونه مدیر جدید ثبت کنه.</p>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="a-field"><label>نام</label><input type="text" name="name" required></div>
      <div class="a-field"><label>یوزرنیم</label><input type="text" name="username" required></div>
      <div class="a-field"><label>ایمیل</label><input type="email" name="email" required></div>
      <div class="a-field"><label>نقش</label>
        <select name="role"><option value="editor">Editor</option><option value="owner">Owner</option></select>
      </div>
      <div class="a-field"><label>رمز عبور (حداقل ۸ کاراکتر)</label><input type="password" name="password" autocomplete="new-password" required></div>
      <button type="submit" class="a-btn a-btn--primary">ثبت مدیر</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">مدیران فعلی</strong>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead><tr><th>نام</th><th>نقش</th><th>وضعیت</th><th>آخرین ورود</th><?php if ($isOwner): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php while ($a = $admins->fetch_assoc()): ?>
          <tr>
            <td><?= e($a['name']) ?><br><span style="color:var(--a-muted);font-size:11.5px;"><?= e($a['username']) ?></span></td>
            <td><?= e($a['role']) ?></td>
            <td><span class="a-badge <?= $a['status'] === 'active' ? 'a-badge--ok' : 'a-badge--danger' ?>"><?= $a['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span></td>
            <td><?= $a['last_login'] ? e(date('Y-m-d H:i', strtotime($a['last_login']))) : '—' ?></td>
            <?php if ($isOwner): ?>
            <td>
              <?php if ((int) $a['id'] !== (int) $admin['id']): ?>
              <div class="a-btn-row">
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" class="a-btn a-btn--sm"><?= $a['status'] === 'active' ? 'غیرفعال کن' : 'فعال کن' ?></button></form>
                <form method="post" onsubmit="return confirm('این مدیر حذف بشه؟');" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button></form>
              </div>
              <?php else: ?>
                <span style="color:var(--a-muted);font-size:12px;">(خودت)</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
