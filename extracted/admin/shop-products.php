<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }
$owner = shop_is_owner($admin);

function shop_pub_ready(int $id): ?string
{
    $p = store_row('SELECT focus_keyword, seo_description, title_fa FROM shop_products WHERE id = ?', 'i', [$id]);
    if (!$p) { return 'محصول پیدا نشد.'; }
    if (trim((string) $p['focus_keyword']) === '' || trim((string) $p['seo_description']) === '') {
        return 'محصول «' . $p['title_fa'] . '» برای انتشار باید کلمه‌ی کلیدی و توضیح متا (سئو) داشته باشه؛ از صفحه‌ی ویرایش کامل کن.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [(int) ($_POST['id'] ?? 0)])))));
    $msgs = [];
    foreach ($ids as $id) {
        $p = store_row('SELECT * FROM shop_products WHERE id = ?', 'i', [$id]);
        if (!$p) { continue; }
        switch ($action) {
            case 'toggle_active': case 'activate': case 'deactivate':
                $v = $action === 'toggle_active' ? 1 - (int) $p['is_active'] : ($action === 'activate' ? 1 : 0);
                store_exec('UPDATE shop_products SET is_active = ? WHERE id = ?', 'ii', [$v, $id]);
                store_audit('product.' . ($v ? 'activate' : 'deactivate'), 'product', $id);
                break;
            case 'toggle_status': case 'publish': case 'draft':
                $to = $action === 'toggle_status' ? ($p['status'] === 'published' ? 'draft' : 'published') : ($action === 'publish' ? 'published' : 'draft');
                if ($to === 'published' && ($why = shop_pub_ready($id))) { $msgs[] = $why; break; }
                store_exec("UPDATE shop_products SET status = ?, published_at = IF(? = 'published', COALESCE(published_at, NOW()), published_at) WHERE id = ?", 'ssi', [$to, $to, $id]);
                store_audit('product.' . $to, 'product', $id);
                break;
            case 'toggle_featured':
                store_exec('UPDATE shop_products SET is_featured = 1 - is_featured WHERE id = ?', 'i', [$id]);
                break;
            case 'trash':
                store_exec('UPDATE shop_products SET deleted_at = NOW(), is_active = 0 WHERE id = ?', 'i', [$id]);
                store_audit('product.trash', 'product', $id, $p['title_fa']);
                break;
            case 'restore':
                store_exec("UPDATE shop_products SET deleted_at = NULL, status = 'draft' WHERE id = ?", 'i', [$id]);
                store_audit('product.restore', 'product', $id);
                break;
            case 'delete_permanent':
                if (!$owner) { $msgs[] = 'حذف دائمی فقط برای owner مجازه.'; break; }
                if ($p['deleted_at'] === null) { $msgs[] = 'اول محصول رو به سطل زباله ببر.'; break; }
                if (shop_product_has_orders($id)) { $msgs[] = 'محصول «' . $p['title_fa'] . '» سابقه‌ی سفارش داره و حذف دائمی نمی‌شه (سوابق مالی حفظ می‌شن).'; break; }
                shop_delete_product_files_from_disk($id);
                store_exec('DELETE FROM shop_products WHERE id = ?', 'i', [$id]);
                store_audit('product.delete', 'product', $id, $p['title_fa']);
                break;
            case 'duplicate':
                $row = $p;
                foreach (['id', 'sku', 'slug', 'rating_avg', 'rating_count', 'sales_count', 'deleted_at', 'created_at', 'updated_at', 'published_at'] as $k) { unset($row[$k]); }
                $row['title_fa'] .= ' (کپی)'; $row['status'] = 'draft'; $row['is_active'] = 0;
                $slug = unique_slug('shop_products', slugify(($p['title_en'] ?: $p['title_fa']) . '-copy'));
                $cols = array_keys($row); $vals = array_values($row);
                store_exec('INSERT INTO shop_products (' . implode(',', $cols) . ', slug, sku) VALUES (' . implode(',', array_fill(0, count($cols) + 2, '?')) . ')', str_repeat('s', count($cols) + 2), array_merge($vals, [$slug, 'P-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))]));
                $newId = store_last_id();
                store_exec('INSERT INTO shop_product_specs (product_id, label_fa, label_en, label_ar, value_fa, value_en, value_ar, sort_order) SELECT ?, label_fa, label_en, label_ar, value_fa, value_en, value_ar, sort_order FROM shop_product_specs WHERE product_id = ?', 'ii', [$newId, $id]);
                store_audit('product.duplicate', 'product', $newId, 'from #' . $id);
                $msgs[] = 'نسخه‌ی پیش‌نویس ساخته شد (تصاویر، فایل‌ها و لایسنس‌ها کپی نمی‌شن).';
                break;
        }
    }
    flash_set($msgs ? 'error' : 'ok', $msgs ? implode(' ', $msgs) : 'انجام شد.');
    redirect('shop-products.php' . (!empty($_POST['back']) ? '?' . ltrim((string) $_POST['back'], '?') : ''));
}

$q = trim($_GET['q'] ?? ''); $cat = (int) ($_GET['cat'] ?? 0); $status = $_GET['status'] ?? ''; $active = $_GET['active'] ?? ''; $type = $_GET['type'] ?? '';
$where = []; $types = ''; $params = [];
if ($status === 'trash') { $where[] = 'p.deleted_at IS NOT NULL'; } else { $where[] = 'p.deleted_at IS NULL'; }
if ($q !== '') { $like = '%' . store_like_escape($q) . '%'; $where[] = '(p.title_fa LIKE ? OR p.title_en LIKE ? OR p.sku LIKE ? OR p.slug LIKE ?)'; $types .= 'ssss'; array_push($params, $like, $like, $like, $like); }
if ($cat) { $where[] = 'p.category_id = ?'; $types .= 'i'; $params[] = $cat; }
if (in_array($status, ['published', 'draft'], true)) { $where[] = 'p.status = ?'; $types .= 's'; $params[] = $status; }
if ($active === '1' || $active === '0') { $where[] = 'p.is_active = ?'; $types .= 'i'; $params[] = (int) $active; }
if (isset(store_product_types()[$type])) { $where[] = 'p.product_type = ?'; $types .= 's'; $params[] = $type; }
$whereSql = implode(' AND ', $where);
$perPage = 20;
$total = (int) store_val("SELECT COUNT(*) FROM shop_products p WHERE $whereSql", $types, $params);
[$totalPages, $page, $offset] = paginate_args($total, $perPage, (int) ($_GET['page'] ?? 1));
$rows = store_rows(
    "SELECT p.*, c.name_fa AS cat_name, i.thumb_path,
       (SELECT COUNT(*) FROM shop_product_files f WHERE f.product_id = p.id AND f.is_active = 1) AS files,
       (SELECT COUNT(*) FROM shop_licenses l WHERE l.product_id = p.id AND l.status = 'available') AS stock
     FROM shop_products p JOIN shop_categories c ON c.id = p.category_id LEFT JOIN shop_product_images i ON i.product_id = p.id AND i.is_main = 1
     WHERE $whereSql ORDER BY p.id DESC LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$perPage, $offset]));
$cats = store_rows('SELECT id, name_fa FROM shop_categories ORDER BY display_order, id');
$back = http_build_query(array_filter(['q' => $q, 'cat' => $cat ?: '', 'status' => $status, 'active' => $active, 'type' => $type, 'page' => $page > 1 ? $page : '']));
$pageTitle = 'محصولات';
require __DIR__ . '/_layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;flex-wrap:wrap;">
  <span class="a-muted"><?= fa_digits((string) $total) ?> محصول</span>
  <span class="a-btn-row"><a class="a-btn<?= $status === 'trash' ? ' a-btn--primary' : '' ?>" href="?status=trash">سطل زباله</a><a class="a-btn a-btn--primary" href="shop-product-edit.php">+ محصول جدید</a></span>
</div>
<form class="a-card shop-filters" method="get">
  <div class="a-field"><label>جستجو</label><input type="search" name="q" value="<?= e($q) ?>" placeholder="عنوان، SKU، آدرس"></div>
  <div class="a-field"><label>دسته</label><select name="cat"><option value="">همه</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name_fa']) ?></option><?php endforeach; ?></select></div>
  <div class="a-field"><label>وضعیت</label><select name="status"><option value="">همه</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>منتشرشده</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>پیش‌نویس</option><option value="trash"<?= $status === 'trash' ? ' selected' : '' ?>>سطل زباله</option></select></div>
  <div class="a-field"><label>فروش</label><select name="active"><option value="">همه</option><option value="1"<?= $active === '1' ? ' selected' : '' ?>>فعال</option><option value="0"<?= $active === '0' ? ' selected' : '' ?>>غیرفعال</option></select></div>
  <div class="a-field"><label>نوع</label><select name="type"><option value="">همه</option><?php foreach (store_product_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <button class="a-btn a-btn--primary" type="submit">فیلتر</button>
</form>

<form method="post" class="a-card"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
  <?php if (!$rows): ?><div class="a-empty">محصولی پیدا نشد.</div><?php else: ?>
  <div class="a-btn-row" style="margin-bottom:10px;">
    <select name="action" aria-label="عملیات گروهی"><option value="">عملیات گروهی…</option>
      <?php if ($status === 'trash'): ?><option value="restore">بازیابی</option><?php if ($owner): ?><option value="delete_permanent">حذف دائمی (فقط بدون سابقه‌ی سفارش)</option><?php endif; ?>
      <?php else: ?><option value="publish">انتشار</option><option value="draft">پیش‌نویس</option><option value="activate">فعال‌سازی فروش</option><option value="deactivate">غیرفعال‌سازی فروش</option><option value="trash">انتقال به سطل زباله</option><?php endif; ?></select>
    <button class="a-btn" type="submit" data-confirm="عملیات روی محصولات انتخاب‌شده اجرا بشه؟">اجرا</button>
  </div>
  <div class="a-table-wrap"><table class="a-table">
    <tr><th><input type="checkbox" data-select-all aria-label="انتخاب همه"></th><th></th><th>محصول</th><th>دسته</th><th>قیمت</th><th>تحویل / موجودی</th><th>وضعیت</th><th>امتیاز / فروش</th><th></th></tr>
    <?php $dm = store_delivery_methods(); foreach ($rows as $p): $pr = store_price($p); ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>"></td>
      <td><?= $p['thumb_path'] ? '<img class="shop-thumb" src="../' . e($p['thumb_path']) . '" alt="">' : '<span class="shop-thumb" style="display:grid;place-items:center">🛍️</span>' ?></td>
      <td><a href="shop-product-edit.php?id=<?= (int) $p['id'] ?>"><b><?= e($p['title_fa']) ?></b></a><?= $p['is_featured'] ? ' ⭐' : '' ?><br><small class="a-muted" dir="ltr"><?= e($p['sku']) ?> · /<?= e($p['slug']) ?></small></td>
      <td><?= e($p['cat_name']) ?></td>
      <td><?= $pr['free'] ? 'رایگان' : ($pr['on_sale'] ? '<s class="a-muted">' . e(fa_digits(number_format($pr['list']))) . '</s><br>' : '') . e(shop_money($pr['final'])) ?></td>
      <td><?= e($dm[$p['delivery_method']]) ?><br><small class="a-muted"><?php if (in_array($p['delivery_method'], ['file', 'file_license'], true)): ?>فایل: <?= fa_digits((string) (int) $p['files']) ?><?php endif; ?> <?php if (in_array($p['delivery_method'], ['license', 'file_license'], true)): ?>لایسنس آزاد: <b style="color:<?= $p['stock'] > 2 ? 'inherit' : 'var(--a-danger)' ?>"><?= fa_digits((string) (int) $p['stock']) ?></b><?php endif; ?></small></td>
      <td><?= $p['status'] === 'published' ? '<span class="a-badge a-badge--ok">منتشر</span>' : '<span class="a-badge a-badge--muted">پیش‌نویس</span>' ?> <?= $p['is_active'] ? '' : '<span class="a-badge a-badge--danger">غیرفعال</span>' ?></td>
      <td><?= (int) $p['rating_count'] ? fa_digits(number_format((float) $p['rating_avg'], 1)) . ' ★ (' . fa_digits((string) (int) $p['rating_count']) . ')' : '—' ?><br><small class="a-muted"><?= fa_digits((string) (int) $p['sales_count']) ?> فروش</small></td>
      <td><div class="a-btn-row">
        <a class="a-btn a-btn--sm" href="shop-product-edit.php?id=<?= (int) $p['id'] ?>">ویرایش</a>
        <?php if ($p['status'] === 'published' && $p['is_active'] && !$p['deleted_at']): ?><a class="a-btn a-btn--sm" href="../shop/<?= e($p['slug']) ?>" target="_blank">مشاهده</a><?php endif; ?>
        <button class="a-btn a-btn--sm" type="submit" name="action" value="toggle_active" formaction="shop-products.php" onclick="this.form.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(c=>c.checked=false);this.form.insertAdjacentHTML('beforeend','<input type=hidden name=id value=<?= (int) $p['id'] ?>>')"><?= $p['is_active'] ? 'غیرفعال' : 'فعال' ?></button>
      </div></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= shop_pager($page, $totalPages, array_filter(['q' => $q, 'cat' => $cat ?: '', 'status' => $status, 'active' => $active, 'type' => $type])) ?>
  <?php endif; ?>
</form>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
