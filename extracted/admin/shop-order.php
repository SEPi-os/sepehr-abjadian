<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }
$owner = shop_is_owner($admin);
$order = store_order_by_number((string) ($_GET['n'] ?? $_POST['n'] ?? ''));
if (!$order) { flash_set('error', 'سفارش پیدا نشد.'); redirect('shop-orders.php'); }
$oid = (int) $order['id'];
$self = 'shop-order.php?n=' . urlencode($order['order_number']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $act = (string) ($_POST['action'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $aid = (int) $admin['id'];
    switch ($act) {
        case 'retry_delivery':
            $r = store_fulfill_order($oid);
            store_audit('order.retry_delivery', 'order', $oid);
            flash_set(!empty($r['ok']) ? 'ok' : 'error', !empty($r['ok']) ? 'تحویل دوباره بررسی شد: ' . ($r['delivery_status'] ?? '') : 'سفارش پرداخت‌شده نیست یا خطایی رخ داد.');
            break;
        case 'reverify':
            $pay = store_row("SELECT id FROM shop_payments WHERE order_id = ? AND gateway NOT IN ('free','manual') AND status IN ('verifying','redirected','failed','cancelled') ORDER BY id DESC LIMIT 1", 'i', [$oid]);
            if (!$pay) { flash_set('error', 'پرداخت قابل بررسی‌ای وجود نداره.'); break; }
            $r = store_payment_process((int) $pay['id']);
            store_audit('order.reverify', 'order', $oid, $r['outcome']);
            flash_set($r['outcome'] === 'paid' ? 'ok' : 'error', 'نتیجه‌ی استعلام از درگاه: ' . $r['outcome']);
            break;
        case 'mark_paid':
            shop_require_owner($admin);
            $r = store_admin_mark_paid($oid, $aid, $reason);
            flash_set($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'پرداخت دستی ثبت شد و سفارش تحویل داده شد.' : ($r['error'] === 'reason_required' ? 'دلیل (حداقل ۵ حرف) الزامیه.' : 'این سفارش قابل تأیید دستی نیست.'));
            break;
        case 'refund':
            shop_require_owner($admin);
            $r = store_admin_refund($oid, $aid, $reason);
            flash_set($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'بازپرداخت ثبت شد؛ دسترسی خریدار لغو شد. (برگشت وجه واقعی باید از پنل درگاه انجام بشه.)' : ($r['error'] === 'reason_required' ? 'دلیل (حداقل ۵ حرف) الزامیه.' : 'فقط سفارش پرداخت‌شده قابل بازپرداخته.'));
            break;
        case 'cancel':
            $ok = store_cancel_order($oid, 'admin', $aid, $reason ?: 'لغو توسط مدیر');
            store_audit('order.cancel', 'order', $oid, $reason);
            flash_set($ok ? 'ok' : 'error', $ok ? 'سفارش لغو شد.' : 'این سفارش در حال پرداخته یا قبلاً بسته شده.');
            break;
        case 'note':
            store_exec('UPDATE shop_orders SET admin_note = ? WHERE id = ?', 'si', [mb_substr($reason, 0, 4000), $oid]);
            flash_set('ok', 'یادداشت ذخیره شد.');
            break;
        case 'deliver_manual':
            store_admin_deliver_item((int) ($_POST['item_id'] ?? 0), (string) ($_POST['delivery_note'] ?? ''));
            store_audit('order.manual_delivery', 'order', $oid);
            flash_set('ok', 'اطلاعات تحویل برای خریدار ثبت شد.');
            break;
    }
    redirect($self);
}
$items = store_order_items($oid);
$payments = store_rows('SELECT * FROM shop_payments WHERE order_id = ? ORDER BY id', 'i', [$oid]);
$events = store_rows('SELECT * FROM shop_order_events WHERE order_id = ? ORDER BY id', 'i', [$oid]);
$licenses = store_rows("SELECT id, order_item_id, code_hint, status FROM shop_licenses WHERE order_id = ? ORDER BY id", 'i', [$oid]);
$user = store_row('SELECT id, name, username, email, phone FROM users WHERE id = ?', 'i', [(int) $order['user_id']]);
$pageTitle = 'سفارش ' . $order['order_number'];
require __DIR__ . '/_layout_top.php';
$ps = store_payment_status_labels(); $os = store_order_status_labels(); $ds = store_delivery_status_labels();
$canRetry = $order['payment_status'] === 'paid' && $order['delivery_status'] !== 'delivered';
$canCancel = $order['status'] === 'pending' && in_array($order['payment_status'], ['unpaid', 'failed', 'cancelled'], true);
?>
<div class="a-btn-row" style="margin-bottom:14px"><a class="a-btn" href="shop-orders.php">← همه‌ی سفارش‌ها</a></div>
<div class="a-grid a-grid--2">
  <div class="a-card">
    <h2>وضعیت</h2>
    <dl class="shop-kv">
      <dt>شماره سفارش</dt><dd dir="ltr"><b><?= e($order['order_number']) ?></b></dd>
      <dt>تاریخ ثبت</dt><dd><?= e(shop_date($order['created_at'])) ?></dd>
      <dt>وضعیت سفارش</dt><dd><?= shop_badge($order['status'], $os[$order['status']]) ?></dd>
      <dt>پرداخت</dt><dd><?= shop_badge($order['payment_status'], $ps[$order['payment_status']]) ?> <?= $order['paid_at'] ? '<small class="a-muted">' . e(shop_date($order['paid_at'])) . '</small>' : '' ?></dd>
      <dt>تحویل</dt><dd><?= shop_badge($order['delivery_status'], $ds[$order['delivery_status']]) ?></dd>
      <dt>جمع اقلام</dt><dd><?= e(shop_money((int) $order['subtotal'])) ?></dd>
      <dt>تخفیف</dt><dd><?= e(shop_money((int) $order['discount_total'])) ?></dd>
      <dt>مبلغ نهایی</dt><dd><b><?= e(shop_money((int) $order['total'])) ?></b></dd>
    </dl>
  </div>
  <div class="a-card">
    <h2>مشتری</h2>
    <dl class="shop-kv">
      <dt>نام</dt><dd><?= e($order['customer_name']) ?></dd>
      <dt>ایمیل</dt><dd dir="ltr"><?= e($order['customer_email']) ?></dd>
      <dt>موبایل</dt><dd dir="ltr"><?= e($order['customer_phone'] ?: '—') ?></dd>
      <dt>حساب کاربری</dt><dd><a href="user-edit.php?id=<?= (int) $user['id'] ?>"><?= e($user['name']) ?> (@<?= e($user['username']) ?>)</a></dd>
      <dt>توضیح مشتری</dt><dd><?= nl2br(e($order['customer_note'] ?: '—')) ?></dd>
    </dl>
  </div>
</div>

<div class="a-card" style="margin-top:16px"><h2>اقلام</h2><div class="a-table-wrap"><table class="a-table">
  <tr><th>محصول</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th><th>تحویل</th></tr>
  <?php foreach ($items as $it): ?><tr>
    <td><?= e($it['product_title']) ?><br><small class="a-muted" dir="ltr"><?= e($it['product_sku']) ?> · <?= e($it['delivery_method']) ?></small></td>
    <td><?= fa_digits((string) (int) $it['quantity']) ?></td><td><?= e(shop_money((int) $it['unit_price'])) ?></td><td><?= e(shop_money((int) $it['line_total'])) ?></td>
    <td><?= $it['delivered_at'] ? '<span class="a-badge a-badge--ok">تحویل‌شده</span> <small class="a-muted">' . e(shop_date($it['delivered_at'])) . '</small>' : '<span class="a-badge a-badge--warn">در انتظار</span>' ?>
      <?php foreach ($licenses as $l): if ((int) $l['order_item_id'] !== (int) $it['id']) { continue; } ?><br><span class="code-chip">••••<?= e($l['code_hint']) ?></span> <?= shop_badge($l['status'], $l['status']) ?><?php endforeach; ?>
      <?php if ($it['delivery_method'] === 'manual' && $order['payment_status'] === 'paid'): ?>
        <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="deliver_manual"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
          <textarea name="delivery_note" rows="2" placeholder="اطلاعات/دستورالعمل تحویل برای خریدار" required><?= e((string) $it['delivery_note']) ?></textarea><button class="a-btn a-btn--sm" type="submit">ثبت تحویل</button></form>
      <?php endif; ?></td>
  </tr><?php endforeach; ?></table></div></div>

<div class="a-card" style="margin-top:16px"><h2>تلاش‌های پرداخت</h2>
  <?php if (!$payments): ?><div class="a-empty">هنوز پرداختی شروع نشده.</div><?php else: ?><div class="a-table-wrap"><table class="a-table">
    <tr><th>#</th><th>درگاه</th><th>مبلغ</th><th>وضعیت</th><th>مرجع درگاه</th><th>شناسه بانکی</th><th>کارت</th><th>تاریخ</th><th>خطا</th></tr>
    <?php foreach ($payments as $p): ?><tr><td><?= (int) $p['id'] ?></td><td><?= e($p['gateway']) ?></td><td><?= e(shop_money((int) $p['amount'])) ?></td><td><?= shop_badge($p['status'], $p['status']) ?></td><td dir="ltr"><small><?= e($p['gateway_ref'] ?: '—') ?></small></td><td dir="ltr"><small><?= e($p['transaction_id'] ?: '—') ?></small></td><td dir="ltr"><?= e($p['card_mask'] ?: '—') ?></td><td><?= e(shop_date($p['created_at'])) ?></td><td><small><?= e($p['error_message'] ?: '') ?></small></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>

<div class="a-card" style="margin-top:16px"><h2>عملیات</h2>
  <div class="a-btn-row">
    <?php if ($canRetry): ?><form method="post" class="a-inline-form"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="retry_delivery"><button class="a-btn a-btn--primary" type="submit">تلاش مجدد تحویل</button></form><?php endif; ?>
    <?php if ($order['payment_status'] !== 'paid' && $order['payment_status'] !== 'refunded' && $payments): ?><form method="post" class="a-inline-form"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="reverify"><button class="a-btn" type="submit">استعلام مجدد از درگاه</button></form><?php endif; ?>
    <?php if ($canCancel): ?><form method="post" class="a-inline-form" data-confirm="سفارش لغو بشه؟"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="cancel"><button class="a-btn a-btn--danger" type="submit">لغو سفارش</button></form><?php endif; ?>
  </div>
  <?php if ($owner && !in_array($order['payment_status'], ['paid', 'refunded'], true)): ?>
  <form method="post" style="margin-top:14px" data-confirm="پرداخت این سفارش به‌صورت دستی تأیید و محصول تحویل داده بشه؟"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="mark_paid">
    <div class="a-field"><label>تأیید دستی پرداخت (مثلاً کارت‌به‌کارت) — دلیل الزامی و در لاگ ثبت می‌شه</label><input type="text" name="reason" required minlength="5" maxlength="400"></div><button class="a-btn" type="submit">ثبت پرداخت دستی و تحویل</button></form>
  <?php endif; ?>
  <?php if ($owner && $order['payment_status'] === 'paid'): ?>
  <form method="post" style="margin-top:14px" data-confirm="بازپرداخت ثبت و دسترسی خریدار لغو بشه؟ این عملیات برگشت‌ناپذیره."><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="refund">
    <div class="a-field"><label>بازپرداخت — دلیل الزامی (دسترسی خریدار لغو و لایسنس‌ها غیرفعال می‌شن؛ برگشت پول از پنل درگاه انجام بشه)</label><input type="text" name="reason" required minlength="5" maxlength="400"></div><button class="a-btn a-btn--danger" type="submit">ثبت بازپرداخت</button></form>
  <?php endif; ?>
  <form method="post" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="n" value="<?= e($order['order_number']) ?>"><input type="hidden" name="action" value="note"><div class="a-field"><label>یادداشت داخلی مدیر</label><textarea name="reason" rows="3"><?= e((string) $order['admin_note']) ?></textarea></div><button class="a-btn" type="submit">ذخیره‌ی یادداشت</button></form>
</div>

<div class="a-card" style="margin-top:16px"><h2>تاریخچه‌ی سفارش</h2>
  <ul class="shop-timeline"><?php foreach ($events as $ev): ?><li><time><?= e(shop_date($ev['created_at'])) ?></time><span><b><?= e($ev['actor_type']) ?></b> · <?= e($ev['message']) ?> <small class="a-muted">(<?= e($ev['event']) ?>)</small></span></li><?php endforeach; ?></ul></div>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
