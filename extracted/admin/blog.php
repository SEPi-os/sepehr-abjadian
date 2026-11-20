<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

// IMPORTANT: POST handling (and any redirect()) must run before
// _layout_top.php prints any HTML — see blog-categories.php for why.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $post = $conn->query('SELECT featured_image FROM blog_posts WHERE id=' . $id)->fetch_assoc();
        $stmt = $conn->prepare('DELETE FROM blog_posts WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        if ($post && $post['featured_image'] && str_starts_with($post['featured_image'], UPLOAD_URL . '/')) {
            @unlink(BASE_PATH . '/' . $post['featured_image']);
        }
        flash_set('ok', 'مقاله حذف شد.');
    } elseif ($action === 'toggle_status') {
        $post = $conn->query('SELECT status FROM blog_posts WHERE id=' . $id)->fetch_assoc();
        $new = ($post['status'] ?? 'draft') === 'published' ? 'draft' : 'published';
        $publishedAt = $new === 'published' ? ', published_at = COALESCE(published_at, NOW())' : '';
        $conn->query("UPDATE blog_posts SET status = '$new' $publishedAt WHERE id = $id");
        flash_set('ok', $new === 'published' ? 'مقاله منتشر شد.' : 'مقاله به پیش‌نویس تغییر کرد.');
    } elseif ($action === 'toggle_featured') {
        $conn->query("UPDATE blog_posts SET is_featured = 1 - is_featured WHERE id = $id");
        // Only one post can be featured at a time on the public blog page.
        $post = $conn->query('SELECT is_featured FROM blog_posts WHERE id=' . $id)->fetch_assoc();
        if ((int) ($post['is_featured'] ?? 0) === 1) {
            $conn->query("UPDATE blog_posts SET is_featured = 0 WHERE id != $id");
        }
        flash_set('ok', 'وضعیت مقاله‌ی ویژه به‌روزرسانی شد.');
    }
    redirect('blog.php' . (isset($_GET['page']) ? '?page=' . (int) $_GET['page'] : ''));
}

// ---- search / filter / pagination ----------------------------------
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
$types = '';
if ($q !== '') {
    $where[] = '(title_fa LIKE ? OR title_en LIKE ? OR title_ar LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if (in_array($statusFilter, ['draft', 'published'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS n FROM blog_posts $whereSql");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['n'];
$countStmt->close();

[$totalPages, $page, $offset] = paginate_args($total, $perPage, $page);

$sql = "SELECT p.*, c.name_fa AS cat_name FROM blog_posts p
        LEFT JOIN blog_categories c ON c.id = p.category_id
        $whereSql ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$posts = $stmt->get_result();

$pageTitle = 'مدیریت وبلاگ';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <form class="a-search-bar" method="get" style="margin:0;">
      <input type="text" name="q" placeholder="جستجوی عنوان…" value="<?= e($q) ?>">
      <select name="status">
        <option value="">همه وضعیت‌ها</option>
        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>منتشرشده</option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
      </select>
      <button type="submit" class="a-btn a-btn--sm">فیلتر</button>
    </form>
    <a href="blog-edit.php" class="a-btn a-btn--primary">+ مقاله‌ی جدید</a>
  </div>

  <?php if ($posts->num_rows === 0): ?>
    <div class="a-empty">مقاله‌ای پیدا نشد.</div>
  <?php else: ?>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead><tr><th></th><th>عنوان</th><th>دسته</th><th>وضعیت</th><th>ویژه</th><th>تاریخ</th><th></th></tr></thead>
      <tbody>
      <?php while ($p = $posts->fetch_assoc()): ?>
        <tr>
          <td style="font-size:20px;"><?= $p['glyph'] ?: '📝' ?></td>
          <td class="wrap"><?= e($p['title_fa']) ?></td>
          <td><?= e($p['cat_name'] ?? '—') ?></td>
          <td>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="a-badge <?= $p['status'] === 'published' ? 'a-badge--ok' : 'a-badge--muted' ?>" style="border:none;cursor:pointer;">
                <?= $p['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس' ?>
              </button>
            </form>
          </td>
          <td>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_featured">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="a-btn a-btn--sm" style="<?= $p['is_featured'] ? 'color:var(--a-warn);' : '' ?>">
                <?= $p['is_featured'] ? '★' : '☆' ?>
              </button>
            </form>
          </td>
          <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
          <td>
            <div class="a-btn-row">
              <a href="blog-edit.php?id=<?= (int) $p['id'] ?>" class="a-btn a-btn--sm">ویرایش</a>
              <form method="post" onsubmit="return confirm('این مقاله برای همیشه حذف بشه؟');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
      <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>" class="<?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
