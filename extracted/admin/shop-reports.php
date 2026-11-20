<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
shop_require_owner($admin);
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }

$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-29 days'));
$group = in_array($_GET['group'] ?? 'day', ['day', 'week', 'month'], true) ? ($_GET['group'] ?? 'day') : 'day';
$fmt = ['day' => '%Y-%m-%d', 'week' => '%x-W%v', 'month' => '%Y-%m'][$group];
$series = store_rows("SELECT DATE_FORMAT(paid_at, '$fmt') AS k, COUNT(*) AS n, SUM(total) AS s FROM shop_orders WHERE payment_status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY k ORDER BY k", 'ss', [$from . ' 00:00:00', $to . ' 23:59:59']);
$byProduct = store_rows("SELECT p.title_fa, SUM(i.quantity) q, SUM(i.line_total) s FROM shop_order_items i JOIN shop_orders o ON o.id = i.order_id JOIN shop_products p ON p.id = i.product_id WHERE o.payment_status = 'paid' AND o.paid_at BETWEEN ? AND ? GROUP BY p.id, p.title_fa ORDER BY s DESC LIMIT 20", 'ss', [$from . ' 00:00:00', $to . ' 23:59:59']);
$byCat = store_rows("SELECT c.name_fa, SUM(i.quantity) q, SUM(i.line_total) s FROM shop_order_items i JOIN shop_orders o ON o.id = i.order_id JOIN shop_products p ON p.id = i.product_id JOIN shop_categories c ON c.id = p.category_id WHERE o.payment_status = 'paid' AND o.paid_at BETWEEN ? AND ? GROUP BY c.id, c.name_fa ORDER BY s DESC", 'ss', [$from . ' 00:00:00', $to . ' 23:59:59']);
$ref = store_row("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM shop_orders WHERE payment_status = 'refunded' AND created_at BETWEEN ? AND ?", 'ss', [$from . ' 00:00:00', $to . ' 23:59:59']);
$tot = ['n' => array_sum(array_column($series, 'n')), 's' => array_sum(array_column($series, 's'))];

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-' . $from . '_' . $to . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['period', 'orders', 'revenue']);
    foreach ($series as $r) { fputcsv($o, [$r['k'], $r['n'], $r['s']]); }
    exit;
}
$pageTitle = 'گزارش فروش';
require __DIR__ . '/_layout_top.php';
?>
<form class="a-card shop-filters" method="get">
  <div class="a-field"><label>از</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="a-field"><label>تا</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="a-field"><label>گروه‌بندی</label><select name="group"><option value="day"<?= $group === 'day' ? ' selected' : '' ?>>روزانه</option><option value="week"<?= $group === 'week' ? ' selected' : '' ?>>هفتگی</option><option value="month"<?= $group === 'month' ? ' selected' : '' ?>>ماهانه</option></select></div>
  <button class="a-btn a-btn--primary" type="submit">اعمال</button>
  <a class="a-btn" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'group' => $group, 'export' => 'csv'])) ?>">خروجی CSV</a>
</form>
<div class="a-grid a-grid--3" style="margin-bottom:14px">
  <div class="a-card a-stat"><b><?= e(shop_money((int) $tot['s'])) ?></b><span>مبلغ واریزشده در بازه</span></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) (int) $tot['n']) ?></b><span>سفارش پرداخت‌شده</span></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) (int) $ref['n']) ?></b><span>بازپرداخت</span><small><?= e(shop_money((int) $ref['s'])) ?></small></div>
</div>
<div class="a-card" style="margin-bottom:14px"><strong>نمودار فروش</strong><?= shop_bar_chart(array_column($series, 's', 'k')) ?></div>
<div class="a-grid a-grid--2">
  <div class="a-card"><strong>فروش به تفکیک دوره</strong><div class="a-table-wrap"><table class="a-table"><tr><th>دوره</th><th>سفارش</th><th>مبلغ</th></tr><?php foreach ($series as $r): ?><tr><td dir="ltr"><?= e($r['k']) ?></td><td><?= fa_digits((string) (int) $r['n']) ?></td><td><?= e(shop_money((int) $r['s'])) ?></td></tr><?php endforeach; ?></table></div></div>
  <div class="a-card"><strong>فروش به تفکیک دسته</strong><div class="a-table-wrap"><table class="a-table"><tr><th>دسته</th><th>تعداد</th><th>مبلغ</th></tr><?php foreach ($byCat as $r): ?><tr><td><?= e($r['name_fa']) ?></td><td><?= fa_digits((string) (int) $r['q']) ?></td><td><?= e(shop_money((int) $r['s'])) ?></td></tr><?php endforeach; ?></table></div>
  <strong style="display:block;margin-top:16px">پرفروش‌ترین محصولات</strong><div class="a-table-wrap"><table class="a-table"><tr><th>محصول</th><th>تعداد</th><th>مبلغ</th></tr><?php foreach ($byProduct as $r): ?><tr><td><?= e($r['title_fa']) ?></td><td><?= fa_digits((string) (int) $r['q']) ?></td><td><?= e(shop_money((int) $r['s'])) ?></td></tr><?php endforeach; ?></table></div></div>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
