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
        $stmt = $conn->prepare('DELETE FROM contact_messages WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_set('ok', 'پیام حذف شد.');
    } elseif ($action === 'toggle_read') {
        $msg = $conn->query('SELECT status FROM contact_messages WHERE id=' . $id)->fetch_assoc();
        $new = ($msg['status'] ?? 'unread') === 'unread' ? 'read' : 'unread';
        $conn->query("UPDATE contact_messages SET status = '$new' WHERE id = $id");
    }
    redirect('messages.php');
}

$messages = $conn->query('SELECT * FROM contact_messages ORDER BY created_at DESC');

$pageTitle = 'پیام‌های تماس';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-card">
  <p style="color:var(--a-muted);font-size:12.5px;margin:0 0 16px;">
    فرم فعلی سایت (contact.html) به‌عنوان «درخواست پروژه» ثبت می‌شه — این جدول برای وقتیه که یک فرم تماس ساده‌تر هم به سایت اضافه بشه. فعلاً خالیه.
  </p>
  <?php if ($messages->num_rows === 0): ?>
    <div class="a-empty">پیامی ثبت نشده.</div>
  <?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th></th><th>نام</th><th>ایمیل</th><th>پیام</th><th>تاریخ</th><th></th></tr></thead>
      <tbody>
      <?php while ($m = $messages->fetch_assoc()): ?>
        <tr>
          <td>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_read">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="a-badge <?= $m['status'] === 'unread' ? 'a-badge--warn' : 'a-badge--muted' ?>" style="border:none;cursor:pointer;">
                <?= $m['status'] === 'unread' ? 'خوانده‌نشده' : 'خوانده‌شده' ?>
              </button>
            </form>
          </td>
          <td><?= e($m['name']) ?></td>
          <td><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></td>
          <td class="wrap"><?= e(mb_strimwidth($m['message'], 0, 80, '…')) ?></td>
          <td><?= e(date('Y-m-d', strtotime($m['created_at']))) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('این پیام حذف بشه؟');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
