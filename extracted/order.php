<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-payment.php';

store_no_cache();
$number = (string) ($_GET['number'] ?? '');
$user = store_require_user('/dashboard/orders/' . $number);
$order = store_order_for_user($number, (int) $user['id']);   // ownership enforced in SQL
if (!$order) {
    store_message_page(404, 'سفارش پیدا نشد', 'این سفارش وجود ندارد یا متعلق به حساب تو نیست.', ['سفارش‌های من' => '/dashboard/orders']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid or expired form submission.');
    }
    if (($_POST['action'] ?? '') === 'cancel') {
        $done = store_cancel_order((int) $order['id'], 'user', (int) $user['id'], 'لغو توسط کاربر');
        flash_set($done ? 'ok' : 'error', $done ? 'سفارش لغو شد.' : 'این سفارش در حال پرداخت است و فعلاً قابل لغو نیست.');
    }
    redirect('/dashboard/orders/' . $order['order_number']);
}
$items = store_order_items((int) $order['id']);
$events = store_rows("SELECT event, message, created_at FROM shop_order_events WHERE order_id = ? AND event IN ('created','payment_started','paid','payment_failed','payment_cancelled','delivery_delivered','delivery_partial','expired','cancelled','refunded','manual_paid') ORDER BY id", 'i', [(int) $order['id']]);
$ps = store_payment_status_labels(); $ds = store_delivery_status_labels(); $os = store_order_status_labels();
$canPay = $order['status'] === 'pending' && in_array($order['payment_status'], ['unpaid', 'failed', 'cancelled', 'pending'], true) && (int) $order['total'] > 0;
$canCancel = $order['status'] === 'pending' && in_array($order['payment_status'], ['unpaid', 'failed', 'cancelled'], true);

store_layout_start(['title' => 'سفارش ' . $order['order_number'] . ' | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop']);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// ORDER</div><h1><span data-i18n="store.orderNo">شماره سفارش</span> <span dir="ltr"><?= e($order['order_number']) ?></span></h1></section>
  <section class="pg-section store-section">
    <?= store_account_nav('orders') ?><?= store_flash_html() ?>
    <div class="cart-layout">
      <article class="glass-card order-summary">
        <h2 data-i18n="store.items">اقلام سفارش</h2>
        <ul class="order-lines"><?php foreach ($items as $it): ?>
          <li><span><?= e($it['product_title']) ?> × <?= fa_digits((string) (int) $it['quantity']) ?><?php if ($it['delivery_note']): ?><br><small class="muted"><?= nl2br(e($it['delivery_note'])) ?></small><?php endif; ?></span><span><?= store_price_html((int) $it['line_total']) ?></span></li>
        <?php endforeach; ?></ul>
        <div class="order-total"><span data-i18n="store.total">مبلغ کل</span><b><?= store_price_html((int) $order['total']) ?></b></div>
        <h2 data-i18n="store.history">تاریخچه‌ی سفارش</h2>
        <ol class="timeline"><?php foreach ($events as $ev): ?><li><time><?= e(fa_digits(substr($ev['created_at'], 0, 16))) ?></time><span><?= e($ev['message']) ?></span></li><?php endforeach; ?></ol>
      </article>
      <aside class="glass-card cart-summary">
        <dl>
          <div><dt data-i18n="store.date">تاریخ ثبت</dt><dd><?= e(fa_digits(substr($order['created_at'], 0, 16))) ?></dd></div>
          <div><dt data-i18n="store.payStatus">وضعیت پرداخت</dt><dd><span class="status status--<?= e($order['payment_status']) ?>" data-i18n="store.pay.<?= e($order['payment_status']) ?>"><?= e($ps[$order['payment_status']]) ?></span></dd></div>
          <div><dt data-i18n="store.orderStatus">وضعیت سفارش</dt><dd data-i18n="store.ord.<?= e($order['status']) ?>"><?= e($os[$order['status']]) ?></dd></div>
          <div><dt data-i18n="store.deliveryStatus">وضعیت تحویل</dt><dd><?= e($ds[$order['delivery_status']]) ?></dd></div>
        </dl>
        <?php if ($order['payment_status'] === 'paid'): ?><a class="cta-btn primary" href="/dashboard/purchases#order-<?= e($order['order_number']) ?>" data-i18n="store.goPurchases">دانلود / لایسنس</a><?php endif; ?>
        <?php if ($canPay): ?><a class="cta-btn primary" href="/payment/start/<?= e($order['order_number']) ?>" data-i18n="store.payNow">پرداخت</a><?php endif; ?>
        <?php if ($canCancel): ?><form method="post" onsubmit="return confirm('سفارش لغو بشه؟')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="link-danger" type="submit" data-i18n="store.cancelOrder">لغو سفارش</button></form><?php endif; ?>
      </aside>
    </div>
  </section>
<?php store_layout_end(['nav' => 'shop']);
