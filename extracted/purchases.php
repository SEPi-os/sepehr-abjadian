<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-delivery.php';

store_no_cache();
$user = store_require_user('/dashboard/purchases');
$uid = (int) $user['id'];
$purchases = store_user_purchases($uid);
$kinds = ['source' => 'سورس', 'docs' => 'مستندات', 'install' => 'نصب', 'sample' => 'نمونه', 'extra' => 'فایل اضافی'];
$hasLicense = false;
foreach ($purchases as $p) { if ($p['licenses']) { $hasLicense = true; } }

function fmt_size(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB'; }

store_layout_start(['title' => 'خریدها و دانلودها | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop', 'title_key' => 'store.purchasesTitle', 'js' => ['/js/store-purchases.js']]);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// LIBRARY</div><h1 data-i18n="store.navPurchases">خریدها و دانلودها</h1></section>
  <section class="pg-section store-section" data-csrf="<?= e(csrf_token()) ?>">
    <?= store_account_nav('purchases') ?><?= store_flash_html() ?>
    <?php if (!$purchases): ?>
      <div class="empty-state glass-card"><div class="empty-state__icon" aria-hidden="true">📦</div><h2 data-i18n="store.noPurchases">هنوز خریدی انجام نداده‌ای</h2><a class="cta-btn primary" href="/shop" data-i18n="store.goShop">رفتن به فروشگاه</a></div>
    <?php else: ?>
      <?php if ($hasLicense): ?><div class="store-flash store-flash--ok" data-i18n="store.licenseHelp">لایسنس دوره‌ها را کپی کن و در اپلیکیشن اسپات‌پلیر (Spot Player) وارد کن تا دوره‌ی دانلودشده برایت باز شود.</div><?php endif; ?>
      <div class="purchase-list">
      <?php foreach ($purchases as $p): ?>
        <article class="purchase glass-card" id="order-<?= e($p['order_number']) ?>">
          <header class="purchase__head">
            <?php if ($p['thumb']): ?><img src="/<?= e($p['thumb']) ?>" alt="" width="64" height="64" loading="lazy"><?php endif; ?>
            <div><h2><a href="<?= e(store_product_url($p['product_slug'])) ?>"><?= e($p['product_title']) ?></a></h2>
              <small class="muted"><span data-i18n="store.orderNo">سفارش</span> <span dir="ltr"><?= e($p['order_number']) ?></span> · <?= e(fa_digits(substr((string) $p['paid_at'], 0, 10))) ?></small></div>
          </header>

          <?php if ($p['files']): ?>
            <h3 data-i18n="store.files">فایل‌های دانلودی</h3>
            <ul class="file-list">
              <?php foreach ($p['files'] as $f): ?>
                <li>
                  <div><b><?= e($f['title']) ?></b> <span class="badge"><?= e($kinds[$f['file_kind']] ?? '') ?></span><br><small class="muted" dir="ltr"><?= e(strtoupper($f['extension'])) ?> · <?= e(fmt_size((int) $f['size_bytes'])) ?></small></div>
                  <a class="cta-btn primary sm" href="<?= e(store_download_url((int) $p['file_entitlement_id'], (int) $f['id'], $uid)) ?>" rel="nofollow" data-i18n="store.download">دانلود</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php elseif ($p['file_entitlement_id']): ?>
            <p class="muted" data-i18n="store.noFilesYet">فایلی برای این محصول هنوز بارگذاری نشده؛ به‌زودی.</p>
          <?php endif; ?>

          <?php if ($p['licenses']): ?>
            <h3 data-i18n="store.licenses">لایسنس (Spot Player)</h3>
            <ul class="license-list">
              <?php foreach ($p['licenses'] as $i => $l): ?>
                <li data-license-id="<?= (int) $l['id'] ?>">
                  <code class="license-code" dir="ltr" data-masked="••••••••••••<?= e($l['code_hint']) ?>">••••••••••••<?= e($l['code_hint']) ?></code>
                  <button type="button" class="cta-btn ghost sm" data-license-show data-i18n="store.show">نمایش</button>
                  <button type="button" class="cta-btn primary sm" data-license-copy data-i18n="store.copy">کپی</button>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($p['delivery_method'] === 'manual'): ?>
            <?php if ($p['delivered_at']): ?><div class="prose"><h3 data-i18n="store.deliveryInfo">اطلاعات تحویل</h3><p><?= nl2br(e((string) $p['delivery_note'])) ?></p></div>
            <?php else: ?><p class="muted" data-i18n="store.manualPending">این محصول به‌صورت دستی تحویل داده می‌شود؛ بعد از آماده‌شدن اینجا می‌بینی.</p><?php endif; ?>
          <?php endif; ?>
          <?php if (!$p['delivered_at'] && $p['delivery_method'] !== 'manual'): ?><p class="store-flash store-flash--warn" data-i18n="store.partialText">تحویل این مورد هنوز کامل نیست؛ پشتیبانی در حال بررسی است.</p><?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php store_layout_end(['nav' => 'shop']);
