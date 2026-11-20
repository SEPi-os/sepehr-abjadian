<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

// ---- handle form submissions -------------------------------------------
// IMPORTANT: this whole block runs BEFORE _layout_top.php (which is what
// prints the <!DOCTYPE html> and everything after it) — redirect() calls
// header(), and header() only works before any output has been sent.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $nameFa = trim($_POST['name_fa'] ?? '');
        $nameEn = trim($_POST['name_en'] ?? '');
        $nameAr = trim($_POST['name_ar'] ?? '');
        $slugSource = trim($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : $nameEn;
        $slug = slugify($slugSource);

        if ($nameFa === '' || $nameEn === '' || $nameAr === '') {
            flash_set('error', 'هر سه نام (فارسی، انگلیسی، عربی) الزامیه.');
        } else {
            if ($action === 'create') {
                $slug = unique_slug('blog_categories', $slug);
                $stmt = $conn->prepare('INSERT INTO blog_categories (slug, name_fa, name_en, name_ar) VALUES (?,?,?,?)');
                $stmt->bind_param('ssss', $slug, $nameFa, $nameEn, $nameAr);
                $stmt->execute();
                $stmt->close();
                flash_set('ok', 'دسته‌بندی اضافه شد.');
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $slug = unique_slug('blog_categories', $slug, $id);
                $stmt = $conn->prepare('UPDATE blog_categories SET slug=?, name_fa=?, name_en=?, name_ar=? WHERE id=?');
                $stmt->bind_param('ssssi', $slug, $nameFa, $nameEn, $nameAr, $id);
                $stmt->execute();
                $stmt->close();
                flash_set('ok', 'دسته‌بندی ویرایش شد.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // Posts referencing this category are kept — their category_id
        // just falls back to NULL (see ON DELETE SET NULL in the schema).
        $stmt = $conn->prepare('DELETE FROM blog_categories WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash_set('ok', 'دسته‌بندی حذف شد. مقالات این دسته حذف نشدن، فقط بدون دسته موندن.');
    }
    redirect('blog-categories.php');
}

$categories = $conn->query('SELECT c.*, (SELECT COUNT(*) FROM blog_posts p WHERE p.category_id = c.id) AS post_count FROM blog_categories c ORDER BY c.id');
$pageTitle = 'دسته‌بندی‌های وبلاگ';
require __DIR__ . '/_layout_top.php';
?>

<div class="a-grid a-grid--2">
  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">دسته‌بندی جدید</strong>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="a-field"><label>نام (فارسی)</label><input type="text" name="name_fa" required></div>
      <div class="a-field"><label>نام (انگلیسی)</label><input type="text" name="name_en" required></div>
      <div class="a-field"><label>نام (عربی)</label><input type="text" name="name_ar" required></div>
      <div class="a-field"><label>Slug (اختیاری — خودکار از نام انگلیسی ساخته می‌شه)</label><input type="text" name="slug" placeholder="ai"></div>
      <button type="submit" class="a-btn a-btn--primary">افزودن</button>
    </form>
  </div>

  <div class="a-card">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">دسته‌بندی‌های موجود</strong>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead><tr><th>نام</th><th>Slug</th><th>مقالات</th><th></th></tr></thead>
        <tbody>
        <?php while ($c = $categories->fetch_assoc()): ?>
          <tr>
            <td><?= e($c['name_fa']) ?> <span style="color:var(--a-muted)">/ <?= e($c['name_en']) ?> / <?= e($c['name_ar']) ?></span></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td><?= (int) $c['post_count'] ?></td>
            <td>
              <div class="a-btn-row">
                <button type="button" class="a-btn a-btn--sm" onclick="editCat(<?= (int) $c['id'] ?>, '<?= e(addslashes($c['name_fa'])) ?>', '<?= e(addslashes($c['name_en'])) ?>', '<?= e(addslashes($c['name_ar'])) ?>', '<?= e($c['slug']) ?>')">ویرایش</button>
                <form method="post" onsubmit="return confirm('حذف این دسته‌بندی؟');" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button type="submit" class="a-btn a-btn--sm a-btn--danger">حذف</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- edit modal (simple inline form, shown/hidden with plain JS) -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;align-items:center;justify-content:center;">
  <div class="a-card" style="width:100%;max-width:420px;">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">ویرایش دسته‌بندی</strong>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="a-field"><label>نام (فارسی)</label><input type="text" name="name_fa" id="edit-name_fa" required></div>
      <div class="a-field"><label>نام (انگلیسی)</label><input type="text" name="name_en" id="edit-name_en" required></div>
      <div class="a-field"><label>نام (عربی)</label><input type="text" name="name_ar" id="edit-name_ar" required></div>
      <div class="a-field"><label>Slug</label><input type="text" name="slug" id="edit-slug" required></div>
      <div class="a-btn-row">
        <button type="submit" class="a-btn a-btn--primary">ذخیره</button>
        <button type="button" class="a-btn" onclick="document.getElementById('editModal').style.display='none'">انصراف</button>
      </div>
    </form>
  </div>
</div>
<script>
function editCat(id, nameFa, nameEn, nameAr, slug) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-name_fa').value = nameFa;
  document.getElementById('edit-name_en').value = nameEn;
  document.getElementById('edit-name_ar').value = nameAr;
  document.getElementById('edit-slug').value = slug;
  document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
