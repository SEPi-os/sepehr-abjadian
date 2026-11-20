<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }
$owner = shop_is_owner($admin);
$productId = (int) ($_GET['product'] ?? $_POST['product'] ?? 0);

// ---- AJAX: reveal one license (owner only, every reveal is audit-logged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'reveal') {
    if (!csrf_verify() || !$owner) { store_json(['ok' => false, 'error' => 'دسترسی ندارید یا نشست منقضی شده.'], 403); }
    $row = store_row('SELECT * FROM shop_licenses WHERE id = ?', 'i', [(int) ($_POST['id'] ?? 0)]);
    $code = $row ? store_license_plain($row) : null;
    if ($code === null) { store_json(['ok' => false, 'error' => 'پیدا نشد یا رمزگشایی ممکن نیست (کلید storage/app.key را بررسی کن).'], 404); }
    store_audit('license.reveal', 'license', (int) $row['id'], 'product #' . $row['product_id']);
    store_json(['ok' => true, 'code' => $code]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $action = (string) ($_POST['action'] ?? '');
    $lid = (int) ($_POST['license_id'] ?? 0);
    if ($action === 'add' && $productId) {
        $r = shop_license_bulk_add($productId, (string) ($_POST['codes'] ?? ''), trim((string) ($_POST['note'] ?? '')));
        store_audit('license.add', 'product', $productId, json_encode($r));
        flash_set($r['added'] ? 'ok' : 'error', 'جدید: ' . $r['added'] . ' | تکراری: ' . $r['duplicates'] . ' | نامعتبر: ' . $r['invalid']);
    } elseif ($action === 'toggle') {
        store_exec("UPDATE shop_licenses SET status = IF(status = 'available', 'disabled', 'available') WHERE id = ? AND status IN ('available','disabled')", 'i', [$lid]);
        store_audit('license.toggle', 'license', $lid);
    } elseif ($action === 'delete') {
        $n = store_exec("DELETE FROM shop_licenses WHERE id = ? AND status IN ('available','disabled')", 'i', [$lid]);
        store_audit('license.delete', 'license', $lid);
        flash_set($n ? 'ok' : 'error', $n ? 'حذف شد.' : 'لایسنس رزروشده یا تحویل‌شده قابل حذف نیست.');
    } elseif ($action === 'replace') {
        if (!$owner) { flash_set('error', 'فقط owner می‌تونه لایسنس تحویل‌شده رو جایگزین کنه.'); }
        else { $r = shop_license_replace($lid, (int) $admin['id'], trim((string) ($_POST['reason'] ?? '')) ?: 'جایگزینی توسط مدیر'); flash_set($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'لایسنس جایگزین شد؛ خریدار کد جدید رو در پنلش می‌بینه.' : $r['error']); }
    }
    redirect('shop-licenses.php?product=' . $productId . (!empty($_POST['status']) ? '&status=' . urlencode($_POST['status']) : ''));
}

$prods = store_rows("SELECT p.id, p.title_fa, p.delivery_method,
    (SELECT COUNT(*) FROM shop_licenses l WHERE l.product_id = p.id AND l.status = 'available') AS free_n,
    (SELECT COUNT(*) FROM shop_licenses l WHERE l.product_id = p.id) AS all_n
  FROM shop_products p WHERE p.deleted_at IS NULL AND (p.delivery_method IN ('license','file_license') OR EXISTS (SELECT 1 FROM shop_licenses l WHERE l.product_id = p.id)) ORDER BY p.title_fa");
$pageTitle = 'مدیریت لایسنس‌ها';
require __DIR__ . '/_layout_top.php';
$status = in_array($_GET['status'] ?? '', ['available', 'reserved', 'assigned', 'disabled'], true) ? $_GET['status'] : '';
?>
<form class="a-card shop-filters" method="get"><div class="a-field"><label>محصول</label><select name="product" onchange="this.form.submit()"><option value="">انتخاب کن…</option>
  <?php foreach ($prods as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $productId === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['title_fa']) ?> — آزاد: <?= (int) $p['free_n'] ?> / کل: <?= (int) $p['all_n'] ?></option><?php endforeach; ?></select></div>
  <?php if ($productId): ?><div class="a-field"><label>وضعیت</label><select name="status" onchange="this.form.submit()"><option value="">همه</option><?php foreach (['available' => 'آزاد', 'reserved' => 'رزرو', 'assigned' => 'تحویل‌شده', 'disabled' => 'غیرفعال'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div><?php endif; ?></form>
<?php if (!$productId): ?>
  <div class="a-card"><?php if (!$prods): ?><div class="a-empty">هنوز محصولی با تحویل «لایسنس» تعریف نشده.</div><?php else: ?><div class="a-table-wrap"><table class="a-table"><tr><th>محصول</th><th>لایسنس آزاد</th><th>کل</th><th></th></tr>
    <?php foreach ($prods as $p): ?><tr><td><?= e($p['title_fa']) ?></td><td style="color:<?= $p['free_n'] > 2 ? 'inherit' : 'var(--a-danger)' ?>"><b><?= fa_digits((string) (int) $p['free_n']) ?></b></td><td><?= fa_digits((string) (int) $p['all_n']) ?></td><td><a class="a-btn a-btn--sm" href="?product=<?= (int) $p['id'] ?>">مدیریت</a></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php else:
  $product = store_row('SELECT id, title_fa FROM shop_products WHERE id = ?', 'i', [$productId]);
  $counts = array_column(store_rows('SELECT status, COUNT(*) c FROM shop_licenses WHERE product_id = ? GROUP BY status', 'i', [$productId]), 'c', 'status');
  $perPage = 25;
  $total = (int) store_val('SELECT COUNT(*) FROM shop_licenses WHERE product_id = ?' . ($status ? ' AND status = ?' : ''), $status ? 'is' : 'i', $status ? [$productId, $status] : [$productId]);
  [$totalPages, $page, $offset] = paginate_args($total, $perPage, (int) ($_GET['page'] ?? 1));
  $rows = store_rows('SELECT l.*, o.order_number, u.username FROM shop_licenses l LEFT JOIN shop_orders o ON o.id = l.order_id LEFT JOIN users u ON u.id = l.user_id WHERE l.product_id = ?' . ($status ? ' AND l.status = ?' : '') . ' ORDER BY l.id DESC LIMIT ? OFFSET ?', ($status ? 'is' : 'i') . 'ii', array_merge($status ? [$productId, $status] : [$productId], [$perPage, $offset]));
?>
  <div class="a-grid a-grid--4" style="margin-bottom:14px"><?php foreach (['available' => 'آزاد', 'reserved' => 'رزرو', 'assigned' => 'تحویل‌شده', 'disabled' => 'غیرفعال'] as $k => $l): ?><div class="a-card a-stat"><b><?= fa_digits((string) (int) ($counts[$k] ?? 0)) ?></b><span><?= $l ?></span></div><?php endforeach; ?></div>
  <div class="a-card" style="margin-bottom:14px"><h2>افزودن لایسنس برای «<?= e($product['title_fa'] ?? '') ?>»</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="product" value="<?= $productId ?>"><input type="hidden" name="action" value="add">
      <div class="a-field"><label>هر خط یک کد (تکراری‌ها خودکار رد می‌شن)</label><textarea name="codes" rows="5" dir="ltr" required></textarea></div>
      <div class="a-field"><label>یادداشت (اختیاری)</label><input type="text" name="note" maxlength="255"></div>
      <button class="a-btn a-btn--primary" type="submit">افزودن</button></form></div>
  <div class="a-card"><?php if (!$rows): ?><div class="a-empty">لایسنسی نیست.</div><?php else: ?><div class="a-table-wrap"><table class="a-table"><tr><th>#</th><th>کد</th><th>وضعیت</th><th>سفارش / خریدار</th><th>تاریخ</th><th>یادداشت</th><th></th></tr>
    <?php foreach ($rows as $l): ?><tr><td><?= (int) $l['id'] ?></td>
      <td><span class="code-chip">••••<?= e($l['code_hint']) ?></span><?php if ($owner): ?> <button type="button" class="a-btn a-btn--sm" data-reveal="<?= (int) $l['id'] ?>" data-csrf="<?= e(csrf_token()) ?>">نمایش کد</button><?php endif; ?></td>
      <td><?= shop_badge($l['status'], ['available' => 'آزاد', 'reserved' => 'رزرو', 'assigned' => 'تحویل‌شده', 'disabled' => 'غیرفعال'][$l['status']]) ?></td>
      <td><?= $l['order_number'] ? '<a dir="ltr" href="shop-order.php?n=' . e($l['order_number']) . '">' . e($l['order_number']) . '</a><br><small class="a-muted">@' . e((string) $l['username']) . '</small>' : '—' ?></td>
      <td><?= e(shop_date($l['assigned_at'] ?: $l['created_at'])) ?></td><td><small><?= e($l['note']) ?></small></td>
      <td><div class="a-btn-row">
        <?php if (in_array($l['status'], ['available', 'disabled'], true)): ?>
          <form method="post" class="a-inline-form"><?= csrf_field() ?><input type="hidden" name="product" value="<?= $productId ?>"><input type="hidden" name="license_id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="a-btn a-btn--sm" type="submit"><?= $l['status'] === 'available' ? 'غیرفعال' : 'فعال' ?></button></form>
          <form method="post" class="a-inline-form" data-confirm="حذف بشه؟"><?= csrf_field() ?><input type="hidden" name="product" value="<?= $productId ?>"><input type="hidden" name="license_id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="delete"><button class="a-btn a-btn--sm a-btn--danger" type="submit">حذف</button></form>
        <?php elseif ($l['status'] === 'assigned' && $owner): ?>
          <form method="post" class="a-inline-form" data-confirm="این لایسنس غیرفعال و یک لایسنس آزاد جایگزین بشه؟"><?= csrf_field() ?><input type="hidden" name="product" value="<?= $productId ?>"><input type="hidden" name="license_id" value="<?= (int) $l['id'] ?>"><input type="hidden" name="action" value="replace"><input type="text" name="reason" placeholder="دلیل جایگزینی" style="width:130px"><button class="a-btn a-btn--sm" type="submit">جایگزینی</button></form>
        <?php endif; ?></div></td></tr><?php endforeach; ?></table></div>
    <?= shop_pager($page, $totalPages, array_filter(['product' => $productId, 'status' => $status])) ?><?php endif; ?></div>
<?php endif; ?>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
