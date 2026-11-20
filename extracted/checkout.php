<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-payment.php';

store_no_cache();
$user = store_require_user('/checkout');           // not logged in -> /account?next=/checkout
$dbUser = store_row('SELECT name, email, phone FROM users WHERE id = ?', 'i', [(int) $user['id']]) ?: [];
$values = ['name' => (string) ($dbUser['name'] ?? ''), 'email' => (string) ($dbUser['email'] ?? ''), 'phone' => (string) ($dbUser['phone'] ?? ''), 'note' => ''];
$errors = [];
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid or expired form submission. Go back and try again.');
    }
    $values = [
        'name' => trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 120)),
        'email' => trim(mb_substr((string) ($_POST['email'] ?? ''), 0, 190)),
        'phone' => preg_replace('/[^\d+]/', '', strtr((string) ($_POST['phone'] ?? ''), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])),
        'note' => trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 1000)),
    ];
    if (mb_strlen($values['name']) < 2) {
        $errors['name'] = 'نام و نام خانوادگی را وارد کن.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'ایمیل معتبر وارد کن.';
    }
    if ($values['phone'] !== '' && !preg_match('/^(\+98|0)?9\d{9}$/', $values['phone'])) {
        $errors['phone'] = 'شماره موبایل معتبر نیست (مثل 09123456789).';
    }
    if (empty($_POST['accept_terms'])) {
        $errors['terms'] = 'برای ادامه باید شرایط و قوانین خرید را بپذیری.';
    }
    if (!$errors) {
        $res = store_create_order($user, store_cart_from_json((string) ($_POST['cart_json'] ?? '')), $values, (string) ($_POST['checkout_token'] ?? ''), store_int_from_input($_POST['expected_total'] ?? 0));
        if ($res['ok']) {
            $order = $res['order'];
            if ((int) $order['total'] === 0) {
                store_complete_free_order($order);
                redirect('/payment/success/' . $order['order_number']);
            }
            redirect('/payment/start/' . $order['order_number'] . '?auto=1');
        }
        $msgs = [
            'empty' => 'سبد خرید خالی است.', 'cart_invalid' => 'بعضی از محصولات سبد دیگر قابل خرید نیستند؛ سبد را بازبینی کن.',
            'total_changed' => 'قیمت یا موجودی تغییر کرده؛ مبلغ جدید را بررسی کن و دوباره ثبت سفارش بزن.', 'no_stock' => 'موجودی یکی از اقلام همین الان تمام شد.',
            'shop_closed' => 'فروشگاه موقتاً بسته است.', 'rate_limited' => 'تعداد سفارش‌های تو در این ساعت زیاد بوده؛ بعداً تلاش کن.',
            'user_disabled' => 'حساب کاربری تو فعال نیست.', 'bad_token' => 'فرم منقضی شده؛ صفحه را تازه کن.', 'server' => 'خطای داخلی هنگام ثبت سفارش؛ دوباره تلاش کن.',
        ];
        $formError = $msgs[$res['error']] ?? 'ثبت سفارش ممکن نشد.';
        if (in_array($res['error'], ['empty', 'cart_invalid', 'no_stock'], true)) {
            flash_set('error', $formError);
            redirect('/cart');
        }
    }
}
$token = bin2hex(random_bytes(16));

store_layout_start(['title' => 'تکمیل خرید | فروشگاه سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'canonical' => null, 'user' => $user, 'nav' => 'shop', 'title_key' => 'store.checkoutTitle']);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// CHECKOUT</div><h1 data-i18n="store.checkoutH1">تکمیل خرید</h1></section>
  <section class="pg-section store-section" id="checkoutApp" data-state="loading">
    <?= store_flash_html() ?>
    <?php if (!store_shop_enabled()): ?><div class="store-flash store-flash--warn" data-i18n="store.closedNotice">فروشگاه موقتاً بسته است.</div><?php endif; ?>
    <?php if ($formError): ?><div class="store-flash store-flash--error" role="alert"><?= e($formError) ?></div><?php endif; ?>
    <?php if (!store_gateway_ready()): ?><div class="store-flash store-flash--warn" data-i18n="store.gatewayOff">درگاه پرداخت آنلاین هنوز فعال نشده؛ می‌تونی سفارش رو ثبت کنی ولی پرداخت بعداً ممکن می‌شه.</div><?php endif; ?>
    <div class="cart-layout">
      <form class="glass-card checkout-form" method="post" action="/checkout" id="checkoutForm" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_token" value="<?= e($token) ?>">
        <input type="hidden" name="cart_json" id="cartJson" value="">
        <input type="hidden" name="expected_total" id="expectedTotal" value="0">
        <h2 data-i18n="store.buyerInfo">اطلاعات خریدار</h2>
        <label class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><span data-i18n="store.fullName">نام و نام خانوادگی</span><input type="text" name="name" value="<?= e($values['name']) ?>" required maxlength="120" autocomplete="name"><?php if (isset($errors['name'])): ?><em><?= e($errors['name']) ?></em><?php endif; ?></label>
        <label class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><span data-i18n="store.email">ایمیل (برای ارسال رسید)</span><input type="email" name="email" value="<?= e($values['email']) ?>" required maxlength="190" autocomplete="email" dir="ltr"><?php if (isset($errors['email'])): ?><em><?= e($errors['email']) ?></em><?php endif; ?></label>
        <label class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>"><span data-i18n="store.phone">موبایل (اختیاری)</span><input type="tel" name="phone" value="<?= e($values['phone']) ?>" maxlength="20" autocomplete="tel" dir="ltr" inputmode="tel"><?php if (isset($errors['phone'])): ?><em><?= e($errors['phone']) ?></em><?php endif; ?></label>
        <label class="field"><span data-i18n="store.note">توضیحات سفارش (اختیاری)</span><textarea name="note" rows="3" maxlength="1000"><?= e($values['note']) ?></textarea></label>
        <label class="check<?= isset($errors['terms']) ? ' has-error' : '' ?>"><input type="checkbox" name="accept_terms" value="1" required><span data-i18n="store.acceptTerms">شرایط و قوانین خرید محصولات دیجیتال را می‌پذیرم؛ محصولات دیجیتال پس از تحویل قابل بازگشت نیستند.</span></label>
        <?php if (isset($errors['terms'])): ?><em class="field-error"><?= e($errors['terms']) ?></em><?php endif; ?>
        <button class="cta-btn primary" type="submit" id="placeOrder" disabled data-i18n="store.placeOrder">ثبت سفارش و پرداخت</button>
        <p class="buy-box__note" data-i18n="store.payNote">بعد از ثبت سفارش به درگاه پرداخت امن هدایت می‌شی. مبلغ نهایی همیشه روی سرور محاسبه می‌شه.</p>
      </form>

      <aside class="cart-summary glass-card" id="checkoutSummary">
        <h2 data-i18n="store.summary">خلاصه‌ی سفارش</h2>
        <div id="checkoutLines" aria-live="polite"><div class="skeleton-row"></div></div>
        <dl>
          <div><dt data-i18n="store.subtotal">جمع اقلام</dt><dd id="sumSubtotal">—</dd></div>
          <div class="is-discount"><dt data-i18n="store.discount">تخفیف</dt><dd id="sumDiscount">—</dd></div>
          <div class="is-total"><dt data-i18n="store.total">مبلغ قابل پرداخت</dt><dd id="sumTotal">—</dd></div>
        </dl>
        <a class="link-muted" href="/cart" data-i18n="store.backToCart">بازگشت به سبد خرید</a>
      </aside>
    </div>
    <div class="empty-state glass-card" id="cartEmpty" hidden>
      <div class="empty-state__icon" aria-hidden="true">🛒</div><h2 data-i18n="store.cartEmptyTitle">سبد خرید خالی است</h2>
      <a class="cta-btn primary" href="/shop" data-i18n="store.goShop">رفتن به فروشگاه</a>
    </div>
  </section>
<?php store_layout_end(['nav' => 'shop']);
