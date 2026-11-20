<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-payment.php';

store_no_cache();
$number = (string) ($_GET['order'] ?? '');
$hint = ($_GET['r'] ?? '') === 'failed' ? 'failed' : 'success';
$user = store_require_user('/payment/' . $hint . '/' . $number);
$order = store_order_for_user($number, (int) $user['id']);
if (!$order) {
    store_message_page(404, 'سفارش پیدا نشد', 'این سفارش وجود ندارد یا متعلق به حساب تو نیست.', ['سفارش‌های من' => '/dashboard/orders']);
}
// The page shows what the DATABASE says, never what the URL claims.
$state = match (true) {
    $order['payment_status'] === 'paid' => 'paid',
    $order['payment_status'] === 'refunded' => 'refunded',
    $order['payment_status'] === 'pending' && $order['status'] === 'pending' => 'pending',
    default => 'failed',
};
$expected = $state === 'paid' || $state === 'pending' ? 'success' : 'failed';
if ($expected !== $hint) {
    redirect('/payment/' . $expected . '/' . $order['order_number']);
}
$items = store_order_items((int) $order['id']);
$lastPay = store_row('SELECT status, error_message, transaction_id, card_mask FROM shop_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', 'i', [(int) $order['id']]);

store_layout_start(['title' => ($state === 'paid' ? 'پرداخت موفق' : ($state === 'pending' ? 'در حال بررسی پرداخت' : 'پرداخت ناموفق')) . ' | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop']);
?>
  <section class="pg-section store-section">
    <div class="result-card glass-card result-card--<?= $state ?>">
      <?php if ($state === 'paid'): ?>
        <div class="result-card__icon result-card__icon--ok" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></div>
        <h1 data-i18n="store.paySuccess">پرداخت با موفقیت انجام شد</h1>
        <p><span data-i18n="store.orderNo">شماره سفارش</span>: <b dir="ltr"><?= e($order['order_number']) ?></b></p>
        <?php if ($lastPay && $lastPay['transaction_id']): ?><p class="muted"><span data-i18n="store.txRef">کد پیگیری بانکی</span>: <span dir="ltr"><?= e($lastPay['transaction_id']) ?></span></p><?php endif; ?>
        <?php if ($order['delivery_status'] === 'delivered'): ?>
          <p data-i18n="store.deliveredText">محصولات تو آماده‌ست؛ از بخش «خریدها و دانلودها» دانلود کن یا لایسنس رو کپی کن.</p>
        <?php else: ?>
          <div class="store-flash store-flash--warn" data-i18n="store.deliveryPendingText">پرداخت تأیید شد، ولی تحویل بعضی اقلام هنوز کامل نشده. تیم پشتیبانی بررسی می‌کنه و بعد از آماده‌شدن در همین بخش می‌بینی.</div>
        <?php endif; ?>
        <div class="cta-card__actions"><a class="cta-btn primary" href="/dashboard/purchases" data-i18n="store.goPurchases">رفتن به خریدها و دانلودها</a><a class="cta-btn ghost" href="/dashboard/orders/<?= e($order['order_number']) ?>" data-i18n="store.viewOrder">مشاهده‌ی سفارش</a></div>
        <script>try { localStorage.removeItem('sa_cart_v1'); window.dispatchEvent(new Event('sa:cart-changed')); } catch (e) {}</script>
      <?php elseif ($state === 'pending'): ?>
        <div class="spinner" aria-hidden="true"></div>
        <h1 data-i18n="store.payPending">در حال تأیید پرداخت…</h1>
        <p data-i18n="store.payPendingText">نتیجه‌ی پرداخت هنوز از بانک تأیید نشده. اگر مبلغ از حسابت کم شده، نگران نباش؛ بعد از تأیید، محصول خودکار تحویل داده می‌شه.</p>
        <script>setTimeout(function () { location.reload(); }, 12000);</script>
        <div class="cta-card__actions"><a class="cta-btn ghost" href="/dashboard/orders/<?= e($order['order_number']) ?>" data-i18n="store.viewOrder">مشاهده‌ی سفارش</a></div>
      <?php elseif ($state === 'refunded'): ?>
        <h1 data-i18n="store.refunded">این سفارش بازپرداخت شده است</h1>
        <div class="cta-card__actions"><a class="cta-btn ghost" href="/dashboard/orders" data-i18n="store.navOrders">سفارش‌ها</a></div>
      <?php else: ?>
        <div class="result-card__icon result-card__icon--bad" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></div>
        <h1 data-i18n="store.payFailed">پرداخت انجام نشد</h1>
        <p><span data-i18n="store.orderNo">شماره سفارش</span>: <b dir="ltr"><?= e($order['order_number']) ?></b></p>
        <p data-i18n="store.payFailedText">اگر مبلغی از حسابت کم شده، معمولاً تا ۷۲ ساعت به حساب خودت برمی‌گرده. می‌تونی دوباره تلاش کنی.</p>
        <div class="cta-card__actions">
          <?php if ($order['status'] === 'pending'): ?><a class="cta-btn primary" href="/payment/start/<?= e($order['order_number']) ?>" data-i18n="store.retryPay">تلاش مجدد برای پرداخت</a><?php endif; ?>
          <a class="cta-btn ghost" href="/cart" data-i18n="store.backToCart">بازگشت به سبد خرید</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php store_layout_end(['nav' => 'shop']);
