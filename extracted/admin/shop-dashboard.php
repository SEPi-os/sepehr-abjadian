<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) {
    redirect('shop-migrate.php');
}
store_expire_stale_orders();
$days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['days'] ?? 30) : 30;
$pageTitle = 'داشبورد فروشگاه';
require __DIR__ . '/_layout_top.php';

$st = shop_stats();
$from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
$daily = [];
for ($i = $days - 1; $i >= 0; $i--) { $daily[date('Y-m-d', strtotime("-$i days"))] = 0; }
foreach (store_rows("SELECT DATE(paid_at) d, SUM(total) s FROM shop_orders WHERE payment_status = 'paid' AND paid_at >= ? GROUP BY DATE(paid_at)", 's', [$from . ' 00:00:00']) as $r) {
    if (isset($daily[$r['d']])) { $daily[$r['d']] = (int) $r['s']; }
}
$periodRevenue = array_sum($daily);
$top = store_rows("SELECT p.id, p.title_fa, SUM(i.quantity) q, SUM(i.line_total) s FROM shop_order_items i JOIN shop_orders o ON o.id = i.order_id JOIN shop_products p ON p.id = i.product_id WHERE o.payment_status = 'paid' GROUP BY p.id, p.title_fa ORDER BY q DESC, s DESC LIMIT 6");
$recent = store_rows('SELECT o.order_number, o.total, o.payment_status, o.delivery_status, o.created_at, u.name FROM shop_orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 8');
$lowStock = store_rows("SELECT p.id, p.title_fa, (SELECT COUNT(*) FROM shop_licenses l WHERE l.product_id = p.id AND l.status = 'available') AS stock FROM shop_products p WHERE p.delivery_method IN ('license','file_license') AND p.deleted_at IS NULL AND p.status = 'published' HAVING stock < 3 ORDER BY stock LIMIT 6");
$alerts = [];
if (!store_gateway_ready()) { $alerts[] = ['warn', 'درگاه پرداخت پیکربندی نشده؛ مشتری‌ها می‌تونن سفارش ثبت کنن ولی پرداخت آنلاین انجام نمی‌شه. (STORE_SETUP.md)']; }
if (!store_webp_supported()) { $alerts[] = ['error', 'GD با WebP روی سرور فعال نیست؛ آپلود تصویر محصول کار نمی‌کنه.']; }
if ($st['reviews_pending']) { $alerts[] = ['warn', fa_digits((string) $st['reviews_pending']) . ' نظر منتظر تأیید توئه. <a href="shop-reviews.php">بررسی نظرات</a>']; }
if ($st['delivery_issues']) { $alerts[] = ['error', fa_digits((string) $st['delivery_issues']) . ' سفارش پرداخت‌شده با خطای تحویل داریم. <a href="shop-orders.php?delivery=problem">مشاهده</a>']; }
if ($st['verifying']) { $alerts[] = ['warn', fa_digits((string) $st['verifying']) . ' پرداخت در انتظار تأیید نهایی از درگاه است (با cron خودکار بررسی می‌شه). <a href="shop-orders.php?delivery=problem">مشاهده</a>']; }
foreach ($lowStock as $ls) { $alerts[] = [$ls['stock'] == 0 ? 'error' : 'warn', 'موجودی لایسنس «' . e($ls['title_fa']) . '»: ' . fa_digits((string) $ls['stock']) . ' عدد. <a href="shop-licenses.php?product=' . (int) $ls['id'] . '">افزودن لایسنس</a>']; }
?>
<?php if ($alerts): ?><div class="shop-alert-list" style="margin-bottom:18px;"><?php foreach ($alerts as [$t, $m]): ?><div class="a-alert a-alert--<?= $t === 'error' ? 'error' : 'ok' ?>" style="<?= $t === 'warn' ? 'border-color:rgba(242,183,5,.5);background:rgba(242,183,5,.1);color:#f2d06b' : '' ?>"><?= $m ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="a-grid a-grid--4" style="margin-bottom:14px;">
  <div class="a-card a-stat"><b><?= e(shop_money($st['revenue'])) ?></b><span>کل مبلغ واریزشده</span><small>پرداخت‌های تأییدشده (بدون بازپرداخت)</small></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['orders_total']) ?></b><span>کل سفارش‌ها</span><small><?= fa_digits((string) $st['orders_paid']) ?> پرداخت‌شده · <?= fa_digits((string) $st['orders_pending']) ?> پرداخت‌نشده</small></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['items_sold']) ?></b><span>تعداد اقلام فروخته‌شده</span><small>خریدهای انجام‌شده</small></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['users']) ?></b><span>کاربران</span></div>
</div>
<div class="a-grid a-grid--4" style="margin-bottom:20px;">
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['reviews_total']) ?></b><span>کل نظرات</span><small><?= fa_digits((string) $st['reviews_approved']) ?> تأییدشده · <?= fa_digits((string) $st['reviews_pending']) ?> منتظر · <?= fa_digits((string) $st['reviews_rejected']) ?> ردشده</small></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['products_published']) ?></b><span>محصول منتشرشده</span><small><?= fa_digits((string) $st['products_draft']) ?> پیش‌نویس · <?= fa_digits((string) $st['categories']) ?> دسته</small></div>
  <div class="a-card a-stat"><b><?= fa_digits((string) $st['orders_refunded']) ?></b><span>بازپرداخت‌شده</span><small><?= e(shop_money($st['refunded_amount'])) ?></small></div>
  <div class="a-card a-stat"><b><?= e(shop_money($periodRevenue)) ?></b><span>فروش <?= fa_digits((string) $days) ?> روز اخیر</span></div>
</div>

<div class="a-card" style="margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
    <strong>فروش روزانه (مبلغ واریزشده)</strong>
    <span class="a-btn-row"><?php foreach ([7, 30, 90] as $d): ?><a class="a-btn a-btn--sm<?= $d === $days ? ' a-btn--primary' : '' ?>" href="?days=<?= $d ?>"><?= fa_digits((string) $d) ?> روز</a><?php endforeach; ?></span>
  </div>
  <?= shop_bar_chart($daily) ?>
</div>

<div class="a-grid a-grid--2">
  <div class="a-card">
    <strong>پرفروش‌ترین محصولات</strong>
    <?php if (!$top): ?><div class="a-empty">هنوز فروشی ثبت نشده.</div><?php else: ?>
      <div class="a-table-wrap"><table class="a-table"><tr><th>محصول</th><th>تعداد</th><th>فروش</th></tr>
        <?php foreach ($top as $t): ?><tr><td><a href="shop-product-edit.php?id=<?= (int) $t['id'] ?>"><?= e($t['title_fa']) ?></a></td><td><?= fa_digits((string) (int) $t['q']) ?></td><td><?= e(shop_money((int) $t['s'])) ?></td></tr><?php endforeach; ?></table></div>
    <?php endif; ?>
  </div>
  <div class="a-card">
    <div style="display:flex;justify-content:space-between;align-items:center;"><strong>آخرین سفارش‌ها</strong><a class="a-btn a-btn--sm" href="shop-orders.php">همه</a></div>
    <?php if (!$recent): ?><div class="a-empty">هنوز سفارشی ثبت نشده.</div><?php else: ?>
      <div class="a-table-wrap"><table class="a-table"><tr><th>شماره</th><th>کاربر</th><th>مبلغ</th><th>پرداخت</th></tr>
        <?php $ps = store_payment_status_labels(); foreach ($recent as $o): ?><tr><td><a dir="ltr" href="shop-order.php?n=<?= e($o['order_number']) ?>"><?= e($o['order_number']) ?></a></td><td><?= e($o['name']) ?></td><td><?= e(shop_money((int) $o['total'])) ?></td><td><?= shop_badge($o['payment_status'], $ps[$o['payment_status']]) ?></td></tr><?php endforeach; ?></table></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
