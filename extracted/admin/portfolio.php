<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

// IMPORTANT: POST handling (and any redirect()) must run before
// _layout_top.php prints any HTML.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $item = $conn->query('SELECT cover_image FROM portfolio_items WHERE id=' . $id)->fetch_assoc();
        $stmt = $conn->prepare('DELETE FROM portfolio_items WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($item && $item['cover_image'] && str_starts_with($item['cover_image'], UPLOAD_URL . '/')) {
            @unlink(BASE_PATH . '/' . $item['cover_image']);
        }
        flash_set('ok', 'نمونه‌کار حذف شد.');
    } elseif ($action === 'toggle_status') {
        $item = $conn->query('SELECT status FROM portfolio_items WHERE id=' . $id)->fetch_assoc();
        $new = ($item['status'] ?? 'draft') === 'published' ? 'draft' : 'published';
        $conn->query("UPDATE portfolio_items SET status = '$new' WHERE id = $id");
        flash_set('ok', 'وضعیت نمونه‌کار به‌روزرسانی شد.');
    } elseif ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true) ?: [];
        foreach ($order as $pos => $itemId) {
            $conn->query('UPDATE portfolio_items SET display_order = ' . (int) $pos . ' WHERE id = ' . (int) $itemId);
        }
        flash_set('ok', 'ترتیب نمونه‌کارها به‌روزرسانی شد.');
    }
    redirect('portfolio.php');
}

$items = $conn->query('SELECT * FROM portfolio_items ORDER BY display_order, id DESC');

$pageTitle = 'مدیریت نمونه‌کارها';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <p style="color:var(--a-muted);font-size:12.5px;margin:0;">این‌ها همون کارت‌هایی هستن که توی portfolio.php و بخش «نمونه کارها»ی صفحه‌ی اصلی نمایش داده می‌شن.</p>
    <a href="portfolio-edit.php" class="a-btn a-btn--primary">+ نمونه‌کار جدید</a>
  </div>

  <?php if ($items->num_rows === 0): ?>
    <div class="a-empty">هنوز نمونه‌کاری اضافه نشده.</div>
  <?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th></th><th>عنوان</th><th>دسته</th><th>وضعیت</th><th>ترتیب نمایش</th><th></th></tr></thead>
      <tbody>
      <?php while ($it = $items->fetch_assoc()): ?>
        <tr>
          <td style="font-size:20px;"><?php if ($it['cover_image']): ?>🖼️<?php else: ?><?= $it['glyph'] ?: '📦' ?><?php endif; ?></td>
          <td class="wrap"><?= e($it['title_fa']) ?></td>
          <td><?= $it['category'] === 'ai' ? 'هوش مصنوعی' : 'وب' ?></td>
          <td>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <button type="submit" class="a-badge <?= $it['status'] === 'published' ? 'a-badge--ok' : 'a-badge--muted' ?>" style="border:none;cursor:pointer;">
                <?= $it['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس' ?>
              </button>
            </form>
          </td>
          <td><?= (int) $it['display_order'] ?></td>
          <td>
            <div class="a-btn-row">
              <a href="portfolio-edit.php?id=<?= (int) $it['id'] ?>" class="a-btn a-btn--sm">ویرایش</a>
              <form method="post" onsubmit="return confirm('این نمونه‌کار حذف بشه؟');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                <button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <p style="color:var(--a-muted);font-size:12px;margin-top:12px;">برای تغییر ترتیب نمایش، عدد «ترتیب نمایش» رو موقع ویرایش هر نمونه‌کار تغییر بده — عدد کمتر، بالاتر نمایش داده می‌شه.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
