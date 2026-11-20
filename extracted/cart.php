<?php
require_once __DIR__ . '/includes/store-layout.php';
public_session_start();
$user = current_user();
store_layout_start(['title' => 'سبد خرید | فروشگاه سپهر ابجدیان', 'robots' => 'noindex,follow', 'canonical' => site_url('/cart'), 'user' => $user, 'nav' => 'shop', 'title_key' => 'store.cartTitle']);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// CART</div><h1 data-i18n="store.cartH1">سبد خرید</h1></section>
  <section class="pg-section store-section" id="cartApp" data-state="loading" data-logged-in="<?= $user ? '1' : '0' ?>">
    <?= store_flash_html() ?>
    <div class="cart-layout">
      <div class="cart-lines" id="cartLines" aria-live="polite"><div class="skeleton-row"></div><div class="skeleton-row"></div></div>
      <aside class="cart-summary glass-card" id="cartSummary" hidden>
        <h2 data-i18n="store.summary">خلاصه‌ی سفارش</h2>
        <dl>
          <div><dt data-i18n="store.subtotal">جمع اقلام</dt><dd id="sumSubtotal">—</dd></div>
          <div class="is-discount"><dt data-i18n="store.discount">تخفیف</dt><dd id="sumDiscount">—</dd></div>
          <div class="is-total"><dt data-i18n="store.total">مبلغ قابل پرداخت</dt><dd id="sumTotal">—</dd></div>
        </dl>
        <a class="cta-btn primary" id="checkoutBtn" href="/checkout" data-i18n="store.completePurchase">تکمیل خرید</a>
        <p class="buy-box__note" id="loginHint" hidden data-i18n="store.cartLoginHint">برای ادامه، وارد حساب کاربری می‌شی و به همین مرحله برمی‌گردی.</p>
        <button type="button" class="link-danger" id="clearCart" data-i18n="store.clearCart">خالی‌کردن سبد</button>
      </aside>
    </div>
    <div class="empty-state glass-card" id="cartEmpty" hidden>
      <div class="empty-state__icon" aria-hidden="true">🛒</div>
      <h2 data-i18n="store.cartEmptyTitle">سبد خرید خالی است</h2>
      <p data-i18n="store.cartEmptyText">از فروشگاه محصول انتخاب کن و به سبد اضافه‌ش کن.</p>
      <a class="cta-btn primary" href="/shop" data-i18n="store.goShop">رفتن به فروشگاه</a>
    </div>
    <noscript><div class="store-flash store-flash--warn">برای استفاده از سبد خرید، جاوااسکریپت مرورگر باید فعال باشد.</div></noscript>
  </section>
<?php store_layout_end(['nav' => 'shop']);
