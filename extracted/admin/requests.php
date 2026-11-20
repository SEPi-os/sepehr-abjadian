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
        $stmt = $conn->prepare('DELETE FROM project_requests WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_set('ok', 'درخواست حذف شد.');
    } elseif ($action === 'update') {
        $status = $_POST['status'] ?? 'new';
        $note = trim($_POST['admin_note'] ?? '');
        $stmt = $conn->prepare('UPDATE project_requests SET status=?, admin_note=? WHERE id=?');
        $stmt->bind_param('ssi', $status, $note, $id);
        $stmt->execute();
        $stmt->close();
        flash_set('ok', 'درخواست به‌روزرسانی شد.');
    }
    redirect('requests.php' . (isset($_GET['id']) ? '?id=' . (int) $_GET['id'] : ''));
}

$statusLabels = ['new' => 'جدید', 'reviewing' => 'در حال بررسی', 'contacted' => 'تماس گرفته شد', 'in_progress' => 'در حال انجام', 'completed' => 'تکمیل شد', 'rejected' => 'رد شد'];
$statusBadge = ['new' => 'a-badge--warn', 'reviewing' => 'a-badge--muted', 'contacted' => 'a-badge--ok', 'in_progress' => 'a-badge--ok', 'completed' => 'a-badge--ok', 'rejected' => 'a-badge--danger'];
$typeLabels = ['web' => 'توسعه‌ی وب', 'ai' => 'هوش مصنوعی', 'desktop' => 'دسکتاپ', 'data' => 'داده', 'other' => 'سایر'];
$budgetLabels = ['small' => 'کوچک', 'medium' => 'متوسط', 'large' => 'بزرگ', 'unknown' => 'نامشخص'];

// ---- detail view for one request ------------------------------------
$viewId = (int) ($_GET['id'] ?? 0);
$viewItem = $viewId ? $conn->query("SELECT * FROM project_requests WHERE id=$viewId")->fetch_assoc() : null;

// ---- list view --------------------------------------------------------
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$hasFilter = in_array($statusFilter, array_keys($statusLabels), true);

if ($hasFilter) {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS n FROM project_requests WHERE status = ?');
    $countStmt->bind_param('s', $statusFilter);
    $countStmt->execute();
    $total = (int) $countStmt->get_result()->fetch_assoc()['n'];
    $countStmt->close();
} else {
    $total = (int) $conn->query('SELECT COUNT(*) AS n FROM project_requests')->fetch_assoc()['n'];
}

[$totalPages, $page, $offset] = paginate_args($total, $perPage, $page);
// $perPage/$offset are always ints from paginate_args() — safe to interpolate; LIMIT/OFFSET can't be bound as params pre-MySQL 8 anyway.
if ($hasFilter) {
    $stmt = $conn->prepare("SELECT * FROM project_requests WHERE status = ? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->bind_param('s', $statusFilter);
    $stmt->execute();
    $requests = $stmt->get_result();
} else {
    $requests = $conn->query("SELECT * FROM project_requests ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
}

$pageTitle = 'درخواست‌های پروژه';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($viewItem): ?>
  <div class="a-card" style="margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
      <div>
        <strong style="font-size:15px;"><?= e($viewItem['name']) ?></strong>
        <div style="color:var(--a-muted);font-size:12.5px;margin-top:4px;">
          <a href="mailto:<?= e($viewItem['email']) ?>"><?= e($viewItem['email']) ?></a>
          <?php if ($viewItem['phone']): ?> · <a href="tel:<?= e($viewItem['phone']) ?>"><?= e($viewItem['phone']) ?></a><?php endif; ?>
          <?php if ($viewItem['company']): ?> · <?= e($viewItem['company']) ?><?php endif; ?>
        </div>
      </div>
      <a href="requests.php" class="a-btn a-btn--sm">بستن ✕</a>
    </div>
    <p style="background:rgba(0,0,0,.2);padding:14px;border-radius:9px;font-size:13.5px;line-height:1.9;white-space:pre-wrap;"><?= e($viewItem['message']) ?></p>
    <p style="font-size:12.5px;color:var(--a-muted);">نوع پروژه: <?= e($typeLabels[$viewItem['project_type']] ?? $viewItem['project_type']) ?> · بودجه: <?= e($budgetLabels[$viewItem['budget']] ?? $viewItem['budget']) ?> · ثبت‌شده: <?= e(date('Y-m-d H:i', strtotime($viewItem['created_at']))) ?></p>

    <form method="post" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int) $viewItem['id'] ?>">
      <div class="a-form-grid">
        <div class="a-field"><label>وضعیت</label>
          <select name="status">
            <?php foreach ($statusLabels as $key => $label): ?>
              <option value="<?= $key ?>" <?= $viewItem['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="a-field a-field--full"><label>یادداشت داخلی (فقط برای خودت)</label><textarea name="admin_note"><?= e($viewItem['admin_note'] ?? '') ?></textarea></div>
      </div>
      <button type="submit" class="a-btn a-btn--primary">ذخیره</button>
    </form>
  </div>
<?php endif; ?>

<div class="a-card">
  <form class="a-search-bar" method="get">
    <select name="status" onchange="this.form.submit()">
      <option value="">همه وضعیت‌ها</option>
      <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($requests->num_rows === 0): ?>
    <div class="a-empty">درخواستی پیدا نشد.</div>
  <?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th>نام</th><th>نوع</th><th>بودجه</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
      <tbody>
      <?php while ($r = $requests->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['name']) ?></td>
          <td><?= e($typeLabels[$r['project_type']] ?? $r['project_type']) ?></td>
          <td><?= e($budgetLabels[$r['budget']] ?? $r['budget']) ?></td>
          <td><span class="a-badge <?= $statusBadge[$r['status']] ?>"><?= $statusLabels[$r['status']] ?></span></td>
          <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
          <td>
            <div class="a-btn-row">
              <a href="?id=<?= (int) $r['id'] ?>" class="a-btn a-btn--sm">مشاهده</a>
              <form method="post" onsubmit="return confirm('این درخواست حذف بشه؟');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="a-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>" class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
