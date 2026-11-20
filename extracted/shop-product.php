<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-catalog.php';
require_once __DIR__ . '/includes/store-reviews.php';
require_once __DIR__ . '/includes/store-cart.php';

public_session_start();
$user = current_user();
$slug = (string) ($_GET['slug'] ?? '');
if (!preg_match('/^[a-zA-Z0-9-]{1,190}$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$preview = isset($_GET['preview']) && store_preview_valid($slug, (string) $_GET['preview']);
$p = store_product_by_slug($slug, !$preview);
if (!$p) {
    // renamed product -> permanent redirect to the current URL
    $old = store_row('SELECT p.slug FROM shop_product_redirects r JOIN shop_products p ON p.id = r.product_id JOIN shop_categories c ON c.id = p.category_id WHERE r.old_slug = ? AND ' . STORE_PUBLIC_WHERE, 's', [$slug]);
    if ($old) {
        http_response_code(301);
        header('Location: ' . store_product_url($old['slug']));
        exit;
    }
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$pid = (int) $p['id'];
$selfUrl = store_product_url($p['slug']);

// ---- Review actions (login + CSRF; text is stored as plain text, escaped on output) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid or expired form submission.');
    }
    if (!$user) {
        redirect('/account?next=' . rawurlencode($selfUrl));
    }
    $action = (string) ($_POST['action'] ?? '');
    $errors = ['rating' => 'امتیاز را (۱ تا ۵ ستاره) انتخاب کن.', 'too_short' => 'متن نظر خیلی کوتاهه.', 'too_long' => 'متن نظر خیلی طولانیه.', 'links' => 'متن نظر نباید بیش از دو لینک داشته باشه.', 'rate_limited' => 'تعداد ارسال‌های تو زیاده؛ کمی بعد دوباره تلاش کن.', 'disabled' => 'ثبت نظر فعلاً غیرفعاله.', 'login' => 'برای ثبت نظر وارد شو.', 'product' => 'این محصول در دسترس نیست.'];
    if ($action === 'review') {
        $r = store_review_submit((int) $user['id'], $pid, (int) ($_POST['rating'] ?? 0), (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''));
        flash_set($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'نظر تو ثبت شد و پس از تأیید مدیر نمایش داده می‌شه. ممنون!' : ($errors[$r['error']] ?? 'ثبت نظر ممکن نشد.'));
    } elseif ($action === 'delete_review') {
        store_review_delete_own((int) ($_POST['review_id'] ?? 0), (int) $user['id']);
        flash_set('ok', 'نظرت حذف شد.');
    }
    redirect($selfUrl . '#reviews');
}

[$sellable, $whyNot] = store_product_sellable($p + ['category_active' => $p['category_active']]);
$price = store_price($p);
$gallery = store_product_gallery($pid);
$specs = store_product_specs($pid);
$faq = store_product_faq($p);
$features = store_lines($p['features']);
$related = store_related_products($p, 4);
$reviewPage = max(1, (int) ($_GET['rp'] ?? 1));
$reviews = store_approved_reviews($pid, 10, ($reviewPage - 1) * 10);
$dist = array_fill(1, 5, 0);
foreach (store_rows("SELECT rating, COUNT(*) c FROM shop_reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", 'i', [$pid]) as $d) {
    $dist[(int) $d['rating']] = (int) $d['c'];
}
$myReview = $user ? store_user_review($pid, (int) $user['id']) : null;
$types = store_product_types();
$deliveryLabels = ['file' => ['فایل دانلودی (ZIP/RAR) پس از پرداخت', 'store.delFile'], 'license' => ['کد لایسنس پس از پرداخت', 'store.delLicense'], 'file_license' => ['فایل + کد لایسنس پس از پرداخت', 'store.delBoth'], 'manual' => ['تحویل توسط پشتیبانی پس از پرداخت', 'store.delManual']];

$title = trim((string) $p['seo_title']) !== '' ? $p['seo_title'] : $p['title_fa'] . store_setting('shop_seo_title_suffix', ' | فروشگاه سپهر ابجدیان');
$desc = store_meta_description($p);
$canonical = ($p['canonical_url'] && preg_match('#^https?://#i', $p['canonical_url'])) ? $p['canonical_url'] : site_url($selfUrl);
$mainImg = $gallery[0] ?? null;
$ogImage = $p['og_image'] ? site_url('/' . $p['og_image']) : ($mainImg ? site_url('/' . $mainImg['path']) : null);
$robots = ($p['robots_index'] && !$preview && $p['status'] === 'published') ? 'index,follow' : 'noindex,nofollow';

$ld = [
    breadcrumb_ld(['خانه' => '/', 'فروشگاه' => '/shop', $p['category_name_fa'] => '/shop/category/' . $p['category_slug'], $p['title_fa'] => $selfUrl]),
    store_product_jsonld($p, $gallery, $reviews, $sellable || $whyNot === 'shop_closed'),
];
if ($faq) {
    $ld[] = json_ld(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['a'])]], $faq)]);
}

$l10n = ['en' => [], 'ar' => []];
foreach (['en', 'ar'] as $l) {
    $l10n[$l]['description'] = store_sanitize_html((string) $p['description_' . $l]);
}

store_layout_start([
    'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'robots' => $robots, 'og_image' => $ogImage,
    'og_title' => $p['og_title'] ?: $title, 'og_description' => $p['og_description'] ?: $desc, 'og_type' => 'product',
    'jsonld' => $ld, 'user' => $user, 'nav' => 'shop',
]);
?>
  <section class="pg-section store-section product-page" data-product-id="<?= $pid ?>">
    <?= store_breadcrumb_html(['خانه' => '/', 'فروشگاه' => '/shop', e($p['category_name_fa']) => '/shop/category/' . $p['category_slug'], e($p['title_fa']) => null]) ?>
    <?php if ($preview): ?><div class="store-flash store-flash--warn">پیش‌نمایش مدیر — این صفحه برای عموم نمایش داده نمی‌شود (وضعیت: <?= e($p['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس') ?><?= (int) $p['is_active'] ? '' : '، غیرفعال' ?>).</div><?php endif; ?>
    <?= store_flash_html() ?>

    <div class="product-hero">
      <div class="gallery glass-card" id="gallery">
        <?php if ($mainImg): ?>
          <button type="button" class="gallery__main" id="galleryMainBtn" aria-label="بزرگنمایی تصویر" data-i18n-aria-label="store.zoom">
            <img id="galleryMain" src="/<?= e($mainImg['path']) ?>" srcset="/<?= e($mainImg['card_path']) ?> 600w, /<?= e($mainImg['path']) ?> 1600w" sizes="(max-width: 900px) 92vw, 640px" alt="<?= e($mainImg['alt'] ?: $p['title_fa']) ?>" width="<?= (int) $mainImg['width'] ?>" height="<?= (int) $mainImg['height'] ?>" fetchpriority="high" decoding="async">
          </button>
          <?php if (count($gallery) > 1): ?>
            <div class="gallery__thumbs" role="list">
              <?php foreach ($gallery as $i => $g): ?>
                <button type="button" role="listitem" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-full="/<?= e($g['path']) ?>" data-card="/<?= e($g['card_path']) ?>" data-alt="<?= e($g['alt'] ?: $p['title_fa']) ?>" data-w="<?= (int) $g['width'] ?>" data-h="<?= (int) $g['height'] ?>" aria-label="تصویر <?= $i + 1 ?>">
                  <img src="/<?= e($g['thumb_path']) ?>" alt="" width="80" height="80" loading="lazy" decoding="async">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="gallery__empty" aria-hidden="true">🛍️</div>
        <?php endif; ?>
      </div>

      <aside class="buy-box glass-card" aria-label="خرید">
        <a class="product-card__cat" href="/shop/category/<?= e($p['category_slug']) ?>"<?= store_l10n(['name_fa' => $p['category_name_fa'], 'name_en' => $p['category_name_en'], 'name_ar' => $p['category_name_ar']], 'name') ?>><?= e($p['category_name_fa']) ?></a>
        <h1 class="buy-box__title"<?= store_l10n($p, 'title') ?>><?= e($p['title_fa']) ?></h1>
        <?php if ((int) $p['rating_count'] > 0): ?>
          <a class="buy-box__rating" href="#reviews"><?= store_stars_html((float) $p['rating_avg']) ?> <b><?= fa_digits(number_format((float) $p['rating_avg'], 1)) ?></b> <span>(<?= fa_digits((string) (int) $p['rating_count']) ?> <span data-i18n="store.reviewsWord">نظر</span>)</span></a>
        <?php endif; ?>
        <?php if (trim($p['summary_fa']) !== ''): ?><p class="buy-box__summary"<?= store_l10n($p, 'summary') ?>><?= e($p['summary_fa']) ?></p><?php endif; ?>

        <div class="buy-box__price">
          <?php if ((int) $p['show_price'] || $price['free']): ?>
            <?php if ($price['on_sale']): ?><s class="price price--old" data-amount="<?= $price['list'] ?>"><?= e(store_money($price['list'])) ?></s><span class="badge badge--sale"><?= fa_digits((string) $price['pct']) ?>٪ <span data-i18n="store.off">تخفیف</span></span><?php endif; ?>
            <?= store_price_html($price['final'], 'price--big') ?>
            <?php if ($price['on_sale'] && $p['sale_ends_at']): ?><small class="sale-ends"><span data-i18n="store.saleEnds">پایان تخفیف:</span> <?= e(fa_digits(substr((string) $p['sale_ends_at'], 0, 10))) ?></small><?php endif; ?>
          <?php else: ?><span class="price" data-i18n="store.contactPrice">تماس بگیرید</span><?php endif; ?>
        </div>

        <ul class="buy-box__points">
          <li><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg><span data-i18n="<?= $deliveryLabels[$p['delivery_method']][1] ?>"><?= e($deliveryLabels[$p['delivery_method']][0]) ?></span></li>
          <li><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg><span data-i18n="store.pointSecure">پرداخت امن و تحویل خودکار</span></li>
          <li><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg><span data-i18n="store.pointDash">دسترسی دائمی از داشبورد کاربری</span></li>
        </ul>

        <?php if ($sellable): ?>
          <?php $maxQ = store_max_qty_for($p); ?>
          <?php if ($maxQ > 1): ?>
            <label class="qty-field"><span data-i18n="store.qty">تعداد</span>
              <select id="qtyInput" aria-label="تعداد"><?php for ($i = 1; $i <= $maxQ; $i++): ?><option value="<?= $i ?>"><?= fa_digits((string) $i) ?></option><?php endfor; ?></select>
            </label>
          <?php endif; ?>
          <div class="buy-box__actions">
            <button type="button" class="cta-btn primary" data-add-to-cart="<?= $pid ?>" data-price="<?= $price['final'] ?>" data-qty-source="#qtyInput"><span data-i18n="<?= $price['free'] ? 'store.getFree' : 'store.addToCart' ?>"><?= $price['free'] ? 'دریافت رایگان' : 'افزودن به سبد خرید' ?></span></button>
            <button type="button" class="cta-btn ghost" data-buy-now="<?= $pid ?>" data-price="<?= $price['final'] ?>" data-qty-source="#qtyInput" data-i18n="store.buyNow">خرید فوری</button>
          </div>
          <p class="buy-box__note" data-i18n="store.loginNote">برای تکمیل خرید باید وارد حساب کاربری شوی.</p>
        <?php else: ?>
          <div class="store-flash store-flash--warn" role="status">
            <?php if ($whyNot === 'out_of_stock'): ?><span data-i18n="store.outOfStock">این محصول موقتاً ناموجود است.</span>
            <?php elseif ($whyNot === 'shop_closed'): ?><span data-i18n="store.closedNotice">فروشگاه موقتاً بسته است.</span>
            <?php else: ?><span data-i18n="store.unavailable">این محصول فعلاً قابل خرید نیست.</span><?php endif; ?>
          </div>
        <?php endif; ?>

        <dl class="buy-box__meta">
          <div><dt data-i18n="store.sku">کد محصول</dt><dd dir="ltr"><?= e($p['sku']) ?></dd></div>
          <div><dt data-i18n="store.productType">نوع</dt><dd data-i18n="store.type.<?= e($p['product_type']) ?>"><?= e($types[$p['product_type']] ?? '') ?></dd></div>
          <?php if ($p['version']): ?><div><dt data-i18n="store.version">نسخه</dt><dd dir="ltr"><?= e($p['version']) ?></dd></div><?php endif; ?>
          <?php if ($p['brand']): ?><div><dt data-i18n="store.brand">سازنده</dt><dd><?= e($p['brand']) ?></dd></div><?php endif; ?>
          <div><dt data-i18n="store.updated">آخرین به‌روزرسانی</dt><dd><?= e(fa_digits(substr((string) $p['updated_at'], 0, 10))) ?></dd></div>
          <?php if ((int) $p['sales_count'] > 0): ?><div><dt data-i18n="store.sales">تعداد فروش</dt><dd><?= fa_digits((string) (int) $p['sales_count']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($p['demo_url'] && preg_match('#^https?://#i', $p['demo_url'])): ?><a class="cta-btn ghost buy-box__demo" href="<?= e($p['demo_url']) ?>" target="_blank" rel="noopener nofollow" data-i18n="store.demo">مشاهده‌ی دمو</a><?php endif; ?>
      </aside>
    </div>

    <nav class="section-tabs" aria-label="بخش‌های محصول">
      <a href="#description" data-i18n="store.tabDesc">توضیحات</a>
      <?php if ($specs): ?><a href="#specs" data-i18n="store.tabSpecs">مشخصات</a><?php endif; ?>
      <?php if (trim((string) $p['install_guide']) !== ''): ?><a href="#install" data-i18n="store.tabInstall">راهنمای نصب</a><?php endif; ?>
      <?php if ($faq): ?><a href="#faq" data-i18n="store.tabFaq">سوالات متداول</a><?php endif; ?>
      <a href="#reviews" data-i18n="store.tabReviews">نظرات</a>
    </nav>

    <article class="glass-card prose product-block" id="description">
      <h2 data-i18n="store.tabDesc">توضیحات</h2>
      <div data-l10n-id="description"><?= store_sanitize_html($p['description_fa']) ?: '<p>' . e($p['summary_fa']) . '</p>' ?></div>
      <?php if ($features): ?>
        <h3 data-i18n="store.features">ویژگی‌ها</h3>
        <ul class="feature-list"><?php foreach ($features as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </article>

    <?php $videoEmbed = video_embed_html($p['video_url'] ?? null); if ($videoEmbed): ?>
    <article class="glass-card product-block product-video" id="video"><h2 data-i18n="store.video">ویدیوی معرفی</h2><?= $videoEmbed ?></article>
    <?php endif; ?>

    <?php if ($specs): ?>
    <article class="glass-card product-block" id="specs">
      <h2 data-i18n="store.tabSpecs">مشخصات</h2>
      <div class="table-wrap"><table class="spec-table"><tbody>
        <?php foreach ($specs as $s): ?><tr><th scope="row"<?= store_l10n($s, 'label') ?>><?= e($s['label_fa']) ?></th><td<?= store_l10n($s, 'value') ?>><?= e($s['value_fa']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </article>
    <?php endif; ?>

    <?php foreach ([['requirements', 'store.requirements', 'پیش‌نیازها'], ['compatibility', 'store.compat', 'سازگاری'], ['install_guide', 'store.tabInstall', 'راهنمای نصب'], ['usage_guide', 'store.usage', 'راهنمای استفاده'], ['changelog', 'store.changelog', 'تاریخچه‌ی تغییرات'], ['license_terms', 'store.licenseTerms', 'شرایط لایسنس'], ['support_info', 'store.support', 'پشتیبانی']] as [$col, $key, $label]):
        if (trim((string) $p[$col]) === '') { continue; } ?>
      <article class="glass-card prose product-block" id="<?= $col === 'install_guide' ? 'install' : e($col) ?>">
        <h2 data-i18n="<?= $key ?>"><?= $label ?></h2>
        <?= store_sanitize_html($p[$col]) ?>
      </article>
    <?php endforeach; ?>

    <?php if ($faq): ?>
    <article class="glass-card product-block" id="faq">
      <h2 data-i18n="store.tabFaq">سوالات متداول</h2>
      <?php foreach ($faq as $f): ?><details class="faq-item"><summary><?= e($f['q']) ?></summary><div class="prose"><?= store_sanitize_html($f['a']) ?></div></details><?php endforeach; ?>
    </article>
    <?php endif; ?>

    <article class="glass-card product-block reviews" id="reviews">
      <h2 data-i18n="store.tabReviews">نظرات و امتیاز کاربران</h2>
      <div class="reviews__summary">
        <div class="reviews__avg">
          <b><?= (int) $p['rating_count'] > 0 ? fa_digits(number_format((float) $p['rating_avg'], 1)) : '—' ?></b>
          <?= store_stars_html((float) $p['rating_avg']) ?>
          <small><?= fa_digits((string) (int) $p['rating_count']) ?> <span data-i18n="store.reviewsWord">نظر</span></small>
        </div>
        <div class="reviews__bars">
          <?php for ($s = 5; $s >= 1; $s--): $pc = (int) $p['rating_count'] > 0 ? round($dist[$s] / (int) $p['rating_count'] * 100) : 0; ?>
            <div class="bar-row"><span><?= fa_digits((string) $s) ?> ★</span><span class="bar"><i style="width:<?= $pc ?>%"></i></span><span><?= fa_digits((string) $dist[$s]) ?></span></div>
          <?php endfor; ?>
        </div>
      </div>

      <?php if (store_setting('shop_reviews_enabled', '1') === '1'): ?>
        <?php if ($user): ?>
          <?php if ($myReview): ?>
            <div class="store-flash store-flash--<?= $myReview['status'] === 'approved' ? 'ok' : ($myReview['status'] === 'rejected' ? 'error' : 'warn') ?>">
              <?php if ($myReview['status'] === 'pending'): ?><span data-i18n="store.myReviewPending">نظر تو در انتظار تأیید مدیر است و هنوز نمایش داده نمی‌شود.</span>
              <?php elseif ($myReview['status'] === 'approved'): ?><span data-i18n="store.myReviewApproved">نظر تو تأیید و منتشر شده است. می‌تونی ویرایشش کنی (دوباره بررسی می‌شه).</span>
              <?php else: ?><span data-i18n="store.myReviewRejected">نظر تو منتشر نشد.</span> <?= $myReview['admin_note'] ? '— ' . e($myReview['admin_note']) : '' ?><?php endif; ?>
            </div>
          <?php endif; ?>
          <form class="review-form" method="post" action="<?= e($selfUrl) ?>#reviews" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="review">
            <fieldset class="star-input"><legend data-i18n="store.yourRating">امتیاز تو</legend>
              <?php for ($s = 5; $s >= 1; $s--): ?><input type="radio" id="rate<?= $s ?>" name="rating" value="<?= $s ?>"<?= $myReview && (int) $myReview['rating'] === $s ? ' checked' : '' ?> required><label for="rate<?= $s ?>" title="<?= $s ?>"><svg viewBox="0 0 24 24"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z"/></svg></label><?php endfor; ?>
            </fieldset>
            <label class="field"><span data-i18n="store.reviewTitle">عنوان (اختیاری)</span><input type="text" name="title" maxlength="120" value="<?= e($myReview['title'] ?? '') ?>"></label>
            <label class="field"><span data-i18n="store.reviewBody">متن نظر</span><textarea name="body" rows="5" required minlength="<?= store_setting_int('shop_review_min_chars', 10) ?>" maxlength="<?= store_setting_int('shop_review_max_chars', 2000) ?>"><?= e($myReview['body'] ?? '') ?></textarea></label>
            <p class="field-hint" data-i18n="store.reviewModerationNote">نظرها قبل از نمایش توسط مدیر بررسی می‌شن.</p>
            <div class="review-form__actions">
              <button class="cta-btn primary" type="submit" data-i18n="store.submitReview">ثبت نظر</button>
            </div>
          </form>
          <?php if ($myReview): ?>
            <form method="post" action="<?= e($selfUrl) ?>#reviews" onsubmit="return confirm('نظرت حذف بشه؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= (int) $myReview['id'] ?>"><button class="link-danger" type="submit" data-i18n="store.deleteMyReview">حذف نظر من</button></form>
          <?php endif; ?>
        <?php else: ?>
          <div class="login-cta glass-card"><p data-i18n="store.reviewLogin">برای ثبت نظر و امتیاز باید وارد حساب کاربری شوی.</p><a class="cta-btn primary" href="/account?next=<?= e(rawurlencode($selfUrl)) ?>" data-i18n="auth.headerBtn">ورود / ثبت‌نام</a></div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="review-list">
        <?php foreach ($reviews as $r): ?>
          <div class="review">
            <div class="review__head"><b><?= e(store_public_name((string) $r['user_name'])) ?></b><?= $r['is_verified_purchase'] ? '<span class="badge badge--ok" data-i18n="store.verifiedBuyer">خریدار تأییدشده</span>' : '' ?><?= store_stars_html((float) $r['rating']) ?><time datetime="<?= e(substr($r['created_at'], 0, 10)) ?>"><?= e(fa_digits(substr($r['created_at'], 0, 10))) ?></time></div>
            <?php if ($r['title'] !== ''): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
            <p><?= nl2br(e($r['body'])) ?></p>
          </div>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><p class="muted" data-i18n="store.noReviews">هنوز نظری ثبت نشده؛ اولین نفر باش.</p><?php endif; ?>
      </div>
      <?= store_pagination_html($reviewPage, max(1, (int) ceil((int) $p['rating_count'] / 10)), $selfUrl, []) ?>
    </article>

    <?php if ($related): ?>
      <div class="pg-section__head"><h2 data-i18n="store.related">محصولات مرتبط</h2></div>
      <div class="product-grid"><?php foreach ($related as $rp) { echo store_card_html($rp); } ?></div>
    <?php endif; ?>
  </section>
  <script type="application/json" id="l10nData"><?= json_encode($l10n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="تصویر محصول"><button type="button" class="lightbox__close" aria-label="بستن">×</button><img alt="" src=""></div>
<?php store_layout_end(['nav' => 'shop']);
