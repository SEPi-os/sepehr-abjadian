<?php
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-reviews.php';

store_no_cache();
$user = store_require_user('/dashboard/reviews');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit('Invalid or expired form submission.'); }
    if (($_POST['action'] ?? '') === 'delete') {
        store_review_delete_own((int) ($_POST['review_id'] ?? 0), (int) $user['id']);
        flash_set('ok', 'نظرت حذف شد.');
    }
    redirect('/dashboard/reviews');
}
$rows = store_rows('SELECT r.id, r.rating, r.title, r.body, r.status, r.admin_note, r.created_at, p.title_fa, p.slug FROM shop_reviews r JOIN shop_products p ON p.id = r.product_id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 100', 'i', [(int) $user['id']]);
$labels = ['pending' => ['در انتظار تأیید', 'store.rv.pending'], 'approved' => ['منتشر شده', 'store.rv.approved'], 'rejected' => ['رد شده', 'store.rv.rejected']];

store_layout_start(['title' => 'نظرات من | سپهر ابجدیان', 'robots' => 'noindex,nofollow', 'user' => $user, 'nav' => 'shop', 'title_key' => 'store.reviewsTitle']);
?>
  <section class="page-hero store-hero"><div class="kicker page-hero__eyebrow">// REVIEWS</div><h1 data-i18n="store.navReviews">نظرات من</h1></section>
  <section class="pg-section store-section">
    <?= store_account_nav('reviews') ?><?= store_flash_html() ?>
    <?php if (!$rows): ?><div class="empty-state glass-card"><div class="empty-state__icon" aria-hidden="true">💬</div><h2 data-i18n="store.noMyReviews">هنوز نظری ثبت نکرده‌ای</h2></div>
    <?php else: foreach ($rows as $r): ?>
      <article class="glass-card review-card">
        <div class="review__head"><a href="<?= e(store_product_url($r['slug'])) ?>"><b><?= e($r['title_fa']) ?></b></a><?= store_stars_html((float) $r['rating']) ?><span class="status status--<?= e($r['status']) ?>" data-i18n="<?= $labels[$r['status']][1] ?>"><?= e($labels[$r['status']][0]) ?></span></div>
        <?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
        <p><?= nl2br(e($r['body'])) ?></p>
        <?php if ($r['status'] === 'rejected' && $r['admin_note']): ?><p class="muted">— <?= e($r['admin_note']) ?></p><?php endif; ?>
        <form method="post" onsubmit="return confirm('نظر حذف بشه؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>"><button class="link-danger" type="submit" data-i18n="store.deleteMyReview">حذف</button></form>
      </article>
    <?php endforeach; endif; ?>
  </section>
<?php store_layout_end(['nav' => 'shop']);
