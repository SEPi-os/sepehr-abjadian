<?php
/**
 * Shared SEO fields + live Google-style snippet preview + checklist.
 * Expects: $seo (array of current values), $seoUrlPrefix (e.g. '/shop/'), $seoSlug (current slug), $seoRequired (bool)
 * The preview/checklist is plain JS in assets/shop-admin.js (data-seo-* hooks) — no external services.
 */
$g = static fn(string $k) => e((string) ($seo[$k] ?? ''));
?>
<div class="a-grid a-grid--2" data-seo-root data-url-prefix="<?= e($seoUrlPrefix) ?>" data-site="<?= e(SITE_URL) ?>">
  <div>
    <div class="a-field"><label>کلمه‌ی کلیدی اصلی <?= $seoRequired ? '<span class="a-badge a-badge--warn">برای انتشار الزامی</span>' : '' ?></label>
      <input type="text" name="focus_keyword" maxlength="120" value="<?= $g('focus_keyword') ?>" data-seo-keyword placeholder="مثلاً: قالب آماده فروشگاهی"></div>
    <div class="a-field"><label>عنوان سئو (Title) <span class="counter" data-count-for="seo_title" data-max="60"></span></label>
      <input type="text" name="seo_title" maxlength="190" value="<?= $g('seo_title') ?>" data-seo-title placeholder="اگر خالی بمونه از عنوان محصول ساخته می‌شه"></div>
    <div class="a-field"><label>توضیح متا / اسنیپت <?= $seoRequired ? '<span class="a-badge a-badge--warn">برای انتشار الزامی</span>' : '' ?> <span class="counter" data-count-for="seo_description" data-max="160"></span></label>
      <textarea name="seo_description" rows="3" maxlength="320" data-seo-desc><?= $g('seo_description') ?></textarea></div>
    <div class="a-field"><label>کلمات کلیدی مرتبط (با ویرگول جدا کن)</label>
      <input type="text" name="related_keywords" maxlength="400" value="<?= $g('related_keywords') ?>"></div>
    <div class="a-field"><label>آدرس Canonical (اختیاری)</label>
      <input type="url" name="canonical_url" maxlength="255" dir="ltr" value="<?= $g('canonical_url') ?>" placeholder="خالی = آدرس خود صفحه"></div>
    <div class="a-field"><label>عنوان Open Graph (شبکه‌های اجتماعی)</label>
      <input type="text" name="og_title" maxlength="190" value="<?= $g('og_title') ?>"></div>
    <div class="a-field"><label>توضیح Open Graph</label>
      <textarea name="og_description" rows="2" maxlength="320"><?= $g('og_description') ?></textarea></div>
    <div class="a-field a-field--check"><label><input type="checkbox" name="robots_index" value="1" <?= !isset($seo['robots_index']) || $seo['robots_index'] ? 'checked' : '' ?>> اجازه‌ی ایندکس شدن توسط موتورهای جستجو</label></div>
  </div>
  <div>
    <strong>پیش‌نمایش در گوگل</strong>
    <div class="snippet" style="margin-top:8px;">
      <div class="snippet__url" data-seo-prev-url></div>
      <div class="snippet__title" data-seo-prev-title></div>
      <div class="snippet__desc" data-seo-prev-desc></div>
    </div>
    <ul class="seo-checks" data-seo-checks></ul>
  </div>
</div>
