<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $cat = store_row('SELECT * FROM shop_categories WHERE id = ?', 'i', [$id]);
    if (!$cat) {
        flash_set('error', 'دسته پیدا نشد.');
    } elseif ($action === 'toggle') {
        store_exec('UPDATE shop_categories SET is_active = 1 - is_active WHERE id = ?', 'i', [$id]);
        store_audit('category.toggle', 'category', $id);
        flash_set('ok', 'وضعیت دسته تغییر کرد (دسته‌ی غیرفعال و محصولاتش در فروشگاه نمایش داده نمی‌شن).');
    } elseif ($action === 'delete') {
        $n = (int) store_val('SELECT COUNT(*) FROM shop_products WHERE category_id = ?', 'i', [$id]);
        $move = (int) ($_POST['move_to'] ?? 0);
        if ($n > 0 && (!$move || $move === $id || !store_val('SELECT COUNT(*) FROM shop_categories WHERE id = ?', 'i', [$move]))) {
            flash_set('error', 'این دسته ' . $n . ' محصول داره؛ برای حذف، دسته‌ی مقصد برای انتقال محصولات رو انتخاب کن.');
        } else {
            try {
                store_tx(function () use ($id, $n, $move) {
                    if ($n > 0) { store_exec('UPDATE shop_products SET category_id = ? WHERE category_id = ?', 'ii', [$move, $id]); }
                    store_exec('DELETE FROM shop_categories WHERE id = ?', 'i', [$id]);
                });
                store_unlink_image($cat['image']);
                store_unlink_image($cat['og_image']);
                store_audit('category.delete', 'category', $id, $cat['name_fa'] . ($n ? " ($n products moved to #$move)" : ''));
                flash_set('ok', 'دسته حذف شد' . ($n ? ' و محصولاتش منتقل شدن.' : '.'));
            } catch (Throwable $ex) {
                store_log('category delete failed', $ex);
                flash_set('error', 'حذف انجام نشد.');
            }
        }
    }
    redirect('shop-categories.php');
}
$pageTitle = 'دسته‌بندی محصولات';
require __DIR__ . '/_layout_top.php';
$cats = store_rows("SELECT c.*, (SELECT COUNT(*) FROM shop_products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS pc FROM shop_categories c ORDER BY c.display_order, c.id");
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
  <span class="a-muted">دسته‌ها در فروشگاه، منو و سایت‌مپ نمایش داده می‌شن؛ هر دسته تنظیمات سئوی مستقل داره.</span>
  <a class="a-btn a-btn--primary" href="shop-category-edit.php">+ دسته‌ی جدید</a>
</div>
<div class="a-card"><div class="a-table-wrap"><table class="a-table">
  <tr><th></th><th>نام</th><th>اسلاگ (URL)</th><th>محصول</th><th>ترتیب</th><th>وضعیت</th><th>سئو</th><th></th></tr>
  <?php foreach ($cats as $c): $seoOk = $c['seo_title'] && $c['seo_description'] && $c['focus_keyword']; ?>
  <tr>
    <td><?= $c['image'] ? '<img class="shop-thumb" src="../' . e($c['image']) . '" alt="">' : '<span style="font-size:26px">' . e($c['glyph']) . '</span>' ?></td>
    <td><b><?= e($c['name_fa']) ?></b><br><small class="a-muted"><?= e($c['name_en']) ?></small></td>
    <td dir="ltr"><a href="../shop/category/<?= e($c['slug']) ?>" target="_blank">/shop/category/<?= e($c['slug']) ?></a></td>
    <td><?= fa_digits((string) (int) $c['pc']) ?></td>
    <td><?= fa_digits((string) (int) $c['display_order']) ?></td>
    <td><?= $c['is_active'] ? '<span class="a-badge a-badge--ok">فعال</span>' : '<span class="a-badge a-badge--muted">غیرفعال</span>' ?></td>
    <td><?= $seoOk ? '<span class="a-badge a-badge--ok">کامل</span>' : '<span class="a-badge a-badge--warn">ناقص</span>' ?></td>
    <td><div class="a-btn-row">
      <a class="a-btn a-btn--sm" href="shop-category-edit.php?id=<?= (int) $c['id'] ?>">ویرایش</a>
      <form method="post" class="a-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="a-btn a-btn--sm" type="submit"><?= $c['is_active'] ? 'غیرفعال' : 'فعال' ?></button></form>
      <form method="post" class="a-inline-form" data-confirm="دسته «<?= e($c['name_fa']) ?>» حذف بشه؟<?= $c['pc'] ? ' محصولاتش به دسته‌ی انتخابی منتقل می‌شن.' : '' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="action" value="delete">
        <?php if ($c['pc']): ?><select name="move_to" required style="max-width:140px"><option value="">انتقال محصولات به…</option><?php foreach ($cats as $o): if ($o['id'] == $c['id']) { continue; } ?><option value="<?= (int) $o['id'] ?>"><?= e($o['name_fa']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="a-btn a-btn--sm a-btn--danger" type="submit">حذف</button></form>
    </div></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
