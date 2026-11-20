<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        // Users with order history are never deleted (financial records must stay intact): disable instead.
        $hasOrders = false;
        try {
            require_once __DIR__ . '/../includes/store-core.php';
            $hasOrders = (int) store_val('SELECT COUNT(*) FROM shop_orders WHERE user_id = ?', 'i', [$id]) > 0;
        } catch (Throwable $storeEx) {
            $hasOrders = false;   // store tables not installed
        }
        if ($hasOrders) {
            flash_set('error', 'این کاربر سابقه‌ی سفارش داره و حذف نمی‌شه تا سوابق مالی حفظ بشن. به‌جاش «غیرفعال» کن.');
        } else {
            $stmt = $conn->prepare('DELETE FROM users WHERE id=?');
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            flash_set($ok ? 'ok' : 'error', $ok ? 'کاربر حذف شد.' : 'حذف کاربر ممکن نشد.');
        }
    } elseif ($action === 'toggle_status') {
        $u = $conn->query('SELECT status FROM users WHERE id=' . $id)->fetch_assoc();
        $new = ($u['status'] ?? 'active') === 'active' ? 'disabled' : 'active';
        $conn->query("UPDATE users SET status = '$new' WHERE id = $id");
        flash_set('ok', 'وضعیت کاربر تغییر کرد.');
    }
    redirect('users.php');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare('SELECT * FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? ORDER BY created_at DESC');
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query('SELECT * FROM users ORDER BY created_at DESC');
}

$pageTitle = 'کاربران سایت';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-card">
  <p style="color:var(--a-muted);font-size:12.5px;margin:0 0 16px;">
    سایت فعلاً فرم عمومی ثبت‌نام/ورود نداره — این پنل برای مدیریت دستی کاربرانه، و برای وقتیه که یک فرم ثبت‌نام به سایت اضافه بشه.
  </p>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <form class="a-search-bar" method="get" style="margin:0;">
      <input type="text" name="q" placeholder="جستجو…" value="<?= e($q) ?>">
      <button type="submit" class="a-btn a-btn--sm">جستجو</button>
    </form>
    <a href="user-edit.php" class="a-btn a-btn--primary">+ کاربر جدید</a>
  </div>

  <?php if ($users->num_rows === 0): ?>
    <div class="a-empty">کاربری پیدا نشد.</div>
  <?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>نام</th><th>یوزرنیم</th><th>ایمیل</th><th>وضعیت</th><th>تاریخ عضویت</th><th></th></tr></thead>
      <tbody>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button type="submit" class="a-badge <?= $u['status'] === 'active' ? 'a-badge--ok' : 'a-badge--danger' ?>" style="border:none;cursor:pointer;">
                <?= $u['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
              </button>
            </form>
          </td>
          <td><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
          <td>
            <div class="a-btn-row">
              <a href="user-edit.php?id=<?= (int) $u['id'] ?>" class="a-btn a-btn--sm">ویرایش</a>
              <form method="post" onsubmit="return confirm('این کاربر حذف بشه؟');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
