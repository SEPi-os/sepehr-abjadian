<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }
store_expire_stale_orders();

$q = trim($_GET['q'] ?? ''); $status = $_GET['status'] ?? ''; $payment = $_GET['payment'] ?? ''; $delivery = $_GET['delivery'] ?? '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : ''; $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$where = ['1=1']; $types = ''; $params = [];
if ($q !== '') { $like = '%' . store_like_escape($q) . '%'; $where[] = '(o.order_number LIKE ? OR o.customer_email LIKE ? OR o.customer_name LIKE ? OR u.username LIKE ? OR o.customer_phone LIKE ?)'; $types .= 'sssss'; array_push($params, $like, $like, $like, $like, $like); }
if (isset(store_order_status_labels()[$status])) { $where[] = 'o.status = ?'; $types .= 's'; $params[] = $status; }
if (isset(store_payment_status_labels()[$payment])) { $where[] = 'o.payment_status = ?'; $types .= 's'; $params[] = $payment; }
if ($delivery === 'problem') { $where[] = "(o.payment_status = 'paid' AND o.delivery_status IN ('failed','partial','pending') AND o.status <> 'completed' OR EXISTS (SELECT 1 FROM shop_payments pv WHERE pv.order_id = o.id AND pv.status = 'verifying'))"; }
elseif (isset(store_delivery_status_labels()[$delivery]) && $delivery !== '') { $where[] = 'o.delivery_status = ?'; $types .= 's'; $params[] = $delivery; }
if ($from) { $where[] = 'o.created_at >= ?'; $types .= 's'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'o.created_at <= ?'; $types .= 's'; $params[] = $to . ' 23:59:59'; }
$whereSql = implode(' AND ', $where);
$base = "FROM shop_orders o JOIN users u ON u.id = o.user_id WHERE $whereSql";

if (($_GET['export'] ?? '') === 'csv') {
    shop_require_owner($admin);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['order_number', 'created_at', 'paid_at', 'customer', 'email', 'phone', 'total', 'currency', 'status', 'payment_status', 'delivery_status']);
    foreach (store_rows("SELECT o.*, u.username $base ORDER BY o.id DESC LIMIT 20000", $types, $params) as $r) {
        // CSV-injection guard: never let a cell start with a formula character
        $safe = fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v;
        fputcsv($out, [$r['order_number'], $r['created_at'], $r['paid_at'], $safe($r['customer_name']), $safe($r['customer_email']), $safe($r['customer_phone']), $r['total'], $r['currency'], $r['status'], $r['payment_status'], $r['delivery_status']]);
    }
    exit;
}

$perPage = 20;
$total = (int) store_val("SELECT COUNT(*) $base", $types, $params);
[$totalPages, $page, $offset] = paginate_args($total, $perPage, (int) ($_GET['page'] ?? 1));
$rows = store_rows("SELECT o.*, u.username, (SELECT COUNT(*) FROM shop_order_items i WHERE i.order_id = o.id) AS items, (SELECT status FROM shop_payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS last_pay $base ORDER BY o.id DESC LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$perPage, $offset]));
$sum = store_row("SELECT COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.total END),0) paid_sum, COUNT(*) n $base", $types, $params);
$pageTitle = 'سفارش‌ها و پرداخت‌ها';
require __DIR__ . '/_layout_top.php';
$ps = store_payment_status_labels(); $os = store_order_status_labels(); $ds = store_delivery_status_labels();
$qs = array_filter(['q' => $q, 'status' => $status, 'payment' => $payment, 'delivery' => $delivery, 'from' => $from, 'to' => $to]);
?>
<form class="a-card shop-filters" method="get">
  <div class="a-field"><label>جستجو</label><input type="search" name="q" value="<?= e($q) ?>" placeholder="شماره سفارش، ایمیل، نام، یوزرنیم"></div>
  <div class="a-field"><label>وضعیت سفارش</label><select name="status"><option value="">همه</option><?php foreach ($os as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="a-field"><label>پرداخت</label><select name="payment"><option value="">همه</option><?php foreach ($ps as $k => $l): ?><option value="<?= $k ?>"<?= $payment === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="a-field"><label>تحویل</label><select name="delivery"><option value="">همه</option><option value="problem"<?= $delivery === 'problem' ? ' selected' : '' ?>>نیازمند بررسی</option><?php foreach (['pending', 'delivered', 'partial', 'failed'] as $k): ?><option value="<?= $k ?>"<?= $delivery === $k ? ' selected' : '' ?>><?= e($ds[$k]) ?></option><?php endforeach; ?></select></div>
  <div class="a-field"><label>از تاریخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="a-field"><label>تا تاریخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="a-btn a-btn--primary" type="submit">فیلتر</button>
  <?php if (shop_is_owner($admin)): ?><a class="a-btn" href="?<?= e(http_build_query($qs + ['export' => 'csv'])) ?>">خروجی CSV</a><?php endif; ?>
</form>
<p class="a-muted"><?= fa_digits((string) $sum['n']) ?> سفارش · جمع پرداخت‌شده در این فیلتر: <b><?= e(shop_money((int) $sum['paid_sum'])) ?></b></p>
<div class="a-card"><?php if (!$rows): ?><div class="a-empty">سفارشی پیدا نشد.</div><?php else: ?>
  <div class="a-table-wrap"><table class="a-table">
    <tr><th>شماره</th><th>تاریخ</th><th>مشتری</th><th>اقلام</th><th>مبلغ</th><th>پرداخت</th><th>تحویل</th><th>وضعیت</th><th></th></tr>
    <?php foreach ($rows as $o): ?>
    <tr>
      <td dir="ltr"><a href="shop-order.php?n=<?= e($o['order_number']) ?>"><b><?= e($o['order_number']) ?></b></a></td>
      <td><?= e(shop_date($o['created_at'])) ?></td>
      <td><?= e($o['customer_name']) ?><br><small class="a-muted" dir="ltr">@<?= e($o['username']) ?></small></td>
      <td><?= fa_digits((string) (int) $o['items']) ?></td>
      <td><?= e(shop_money((int) $o['total'])) ?></td>
      <td><?= shop_badge($o['payment_status'], $ps[$o['payment_status']]) ?><?= $o['last_pay'] === 'verifying' ? ' <span class="a-badge a-badge--warn">تأیید نامشخص</span>' : '' ?></td>
      <td><?= shop_badge($o['delivery_status'], $ds[$o['delivery_status']]) ?></td>
      <td><?= shop_badge($o['status'], $os[$o['status']]) ?></td>
      <td><a class="a-btn a-btn--sm" href="shop-order.php?n=<?= e($o['order_number']) ?>">جزئیات</a></td>
    </tr><?php endforeach; ?>
  </table></div>
  <?= shop_pager($page, $totalPages, $qs) ?>
<?php endif; ?></div>
<?php require __DIR__ . '/_layout_bottom.php';
