<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-payment.php';

store_no_cache();
$number = (string) ($_GET['order'] ?? '');
$user = store_require_user('/payment/start/' . $number);
$order = store_order_for_user($number, (int) $user['id']);
if (!$order) {
    store_message_page(404, 'سفارش پیدا نشد', 'این سفارش وجود ندارد یا متعلق به حساب تو نیست.', ['سفارش‌های من' => '/dashboard/orders']);
}
if ($order['payment_status'] === 'paid') {
    redirect('/payment/success/' . $order['order_number']);
}
$items = store_order_items((int) $order['id']);
$error = '';
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid or expired form submission.');
    }
    if (!store_rate_limit('pay_start', 'u' . (int) $user['id'], 20, 600)) {
        $error = 'تعداد تلاش‌ها زیاد بود؛ چند دقیقه بعد دوباره امتحان کن.';
    } else {
        $res = store_payment_begin($order);
        if ($res['ok']) {
            $redirectUrl = $res['redirect_url'];
        } else {
            $error = match ($res['error']) {
                'gateway_not_configured' => 'درگاه پرداخت آنلاین هنوز راه‌اندازی نشده. سفارش تو با همین شماره ثبت شده و پرداخت‌نشده باقی می‌مونه؛ لطفاً با پشتیبانی تماس بگیر.',
                'no_stock' => 'موجودی لایسنس این سفارش تمام شده و پرداخت انجام نمی‌شه. با پشتیبانی تماس بگیر.',
                'order_closed' => 'این سفارش دیگر قابل پرداخت نیست (لغو یا منقضی شده). از سبد خرید دوباره سفارش بده.',
                'already_paid' => 'این سفارش قبلاً پرداخت شده.',
                'shop_closed' => 'فروشگاه موقتاً بسته است.',
                default => 'ارتباط با درگاه پرداخت برقرار نشد؛ کمی بعد دوباره تلاش کن.',
            };
        }
    }
}

store_layout_start(['title' => 'پرداخت سفارش ' . $order['order_number'] . ' | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop']);
if ($redirectUrl !== ''): ?>
  <section class="pg-section store-section"><div class="empty-state glass-card">
    <div class="spinner" aria-hidden="true"></div>
    <h1 data-i18n="store.redirecting">در حال انتقال به درگاه پرداخت…</h1>
    <p data-i18n="store.redirectingText">اگر منتقل نشدی روی دکمه‌ی زیر بزن. این صفحه را نبند.</p>
    <a class="cta-btn primary" id="gatewayLink" href="<?= e($redirectUrl) ?>" rel="noopener" data-i18n="store.goGateway">رفتن به درگاه</a>
        <script>setTimeout(function () { window.location.replace(<?= json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); }, 400);</script>
  </div></section>
<?php else: ?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// PAYMENT</div><h1 data-i18n="store.payH1">پرداخت سفارش</h1></section>
  <section class="pg-section store-section">
    <?php if ($error): ?><div class="store-flash store-flash--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="cart-layout">
      <article class="glass-card order-summary">
        <h2><span data-i18n="store.orderNo">شماره سفارش</span>: <span dir="ltr"><?= e($order['order_number']) ?></span></h2>
        <ul class="order-lines"><?php foreach ($items as $it): ?><li><span><?= e($it['product_title']) ?> × <?= fa_digits((string) (int) $it['quantity']) ?></span><span><?= store_price_html((int) $it['line_total']) ?></span></li><?php endforeach; ?></ul>
        <div class="order-total"><span data-i18n="store.total">مبلغ قابل پرداخت</span><b><?= store_price_html((int) $order['total']) ?></b></div>
        <?php if ($order['status'] === 'pending' && $order['expires_at']): ?><p class="muted"><span data-i18n="store.reservedUntil">رزرو تا</span> <?= e(fa_digits(substr((string) $order['expires_at'], 11, 5))) ?></p><?php endif; ?>
      </article>
      <aside class="glass-card cart-summary">
        <?php if ($order['status'] === 'pending'): ?>
          <form method="post" action="/payment/start/<?= e($order['order_number']) ?>" id="payForm"><?= csrf_field() ?>
            <button class="cta-btn primary" type="submit" data-i18n="store.payNow">پرداخت امن</button>
          </form>
          <?php if (!empty($_GET['auto']) && !$error && store_gateway_ready()): ?><script>document.getElementById('payForm').submit();</script><?php endif; ?>
        <?php else: ?>
          <p class="muted" data-i18n="store.orderClosed">این سفارش دیگر قابل پرداخت نیست.</p>
        <?php endif; ?>
        <a class="link-muted" href="/dashboard/orders/<?= e($order['order_number']) ?>" data-i18n="store.viewOrder">مشاهده‌ی سفارش</a>
      </aside>
    </div>
  </section>
<?php endif;
store_layout_end(['nav' => 'shop']);
