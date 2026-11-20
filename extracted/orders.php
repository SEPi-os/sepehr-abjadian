<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-orders.php';

store_no_cache();
$user = store_require_user('/dashboard/orders');
$perPage = 15;
$total = (int) store_val('SELECT COUNT(*) FROM shop_orders WHERE user_id = ?', 'i', [(int) $user['id']]);
[$totalPages, $page, $offset] = paginate_args($total, $perPage, (int) ($_GET['page'] ?? 1));
$orders = store_orders_for_user((int) $user['id'], $perPage, $offset);
$ps = store_payment_status_labels();
$os = store_order_status_labels();

store_layout_start(['title' => 'سفارش‌های من | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop', 'title_key' => 'store.ordersTitle']);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// ORDERS</div><h1 data-i18n="store.navOrders">سفارش‌ها</h1></section>
  <section class="pg-section store-section">
    <?= store_account_nav('orders') ?><?= store_flash_html() ?>
    <?php if (!$orders): ?>
      <div class="empty-state glass-card"><div class="empty-state__icon" aria-hidden="true">🧾</div><h2 data-i18n="store.noOrders">هنوز سفارشی نداری</h2><a class="cta-btn primary" href="/shop" data-i18n="store.goShop">رفتن به فروشگاه</a></div>
    <?php else: ?>
      <div class="table-wrap glass-card"><table class="data-table">
        <thead><tr><th data-i18n="store.orderNo">شماره سفارش</th><th data-i18n="store.date">تاریخ</th><th data-i18n="store.items">اقلام</th><th data-i18n="store.total">مبلغ</th><th data-i18n="store.payStatus">وضعیت پرداخت</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td dir="ltr"><?= e($o['order_number']) ?></td>
            <td><?= e(fa_digits(substr($o['created_at'], 0, 16))) ?></td>
            <td><?= fa_digits((string) (int) $o['item_count']) ?></td>
            <td><?= store_price_html((int) $o['total']) ?></td>
            <td><span class="status status--<?= e($o['payment_status']) ?>" data-i18n="store.pay.<?= e($o['payment_status']) ?>"><?= e($ps[$o['payment_status']]) ?></span><?php if ($o['status'] === 'expired' || $o['status'] === 'cancelled'): ?> <small class="muted" data-i18n="store.ord.<?= e($o['status']) ?>">(<?= e($os[$o['status']]) ?>)</small><?php endif; ?></td>
            <td><a class="cta-btn ghost sm" href="/dashboard/orders/<?= e($o['order_number']) ?>" data-i18n="store.view">مشاهده</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?= store_pagination_html($page, $totalPages, '/dashboard/orders') ?>
    <?php endif; ?>
  </section>
<?php store_layout_end(['nav' => 'shop']);
