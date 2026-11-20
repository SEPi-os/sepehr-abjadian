<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cat = $id ? store_row('SELECT * FROM shop_categories WHERE id = ?', 'i', [$id]) : null;
if ($id && !$cat) { flash_set('error', 'دسته پیدا نشد.'); redirect('shop-categories.php'); }
$errors = [];
$form = $cat ?: ['slug' => '', 'name_fa' => '', 'name_en' => '', 'name_ar' => '', 'short_desc_fa' => '', 'short_desc_en' => '', 'short_desc_ar' => '', 'description_fa' => '', 'description_en' => '', 'description_ar' => '', 'glyph' => '🛍️', 'is_active' => 1, 'display_order' => 0, 'seo_title' => '', 'seo_description' => '', 'focus_keyword' => '', 'related_keywords' => '', 'canonical_url' => '', 'og_title' => '', 'og_description' => '', 'robots_index' => 1, 'image' => null, 'og_image' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $d = [
        'name_fa' => trim($_POST['name_fa'] ?? ''), 'name_en' => trim($_POST['name_en'] ?? ''), 'name_ar' => trim($_POST['name_ar'] ?? ''),
        'short_desc_fa' => mb_substr(trim($_POST['short_desc_fa'] ?? ''), 0, 400), 'short_desc_en' => mb_substr(trim($_POST['short_desc_en'] ?? ''), 0, 400), 'short_desc_ar' => mb_substr(trim($_POST['short_desc_ar'] ?? ''), 0, 400),
        'description_fa' => store_sanitize_html($_POST['description_fa'] ?? ''), 'description_en' => store_sanitize_html($_POST['description_en'] ?? ''), 'description_ar' => store_sanitize_html($_POST['description_ar'] ?? ''),
        'glyph' => mb_substr(trim($_POST['glyph'] ?? '') ?: '🛍️', 0, 8), 'is_active' => isset($_POST['is_active']) ? 1 : 0, 'display_order' => (int) ($_POST['display_order'] ?? 0),
        'seo_title' => shop_null_str($_POST['seo_title'] ?? '', 190), 'seo_description' => shop_null_str($_POST['seo_description'] ?? '', 320), 'focus_keyword' => shop_null_str($_POST['focus_keyword'] ?? '', 120),
        'related_keywords' => shop_null_str($_POST['related_keywords'] ?? '', 400), 'canonical_url' => shop_clean_url($_POST['canonical_url'] ?? ''),
        'og_title' => shop_null_str($_POST['og_title'] ?? '', 190), 'og_description' => shop_null_str($_POST['og_description'] ?? '', 320), 'robots_index' => isset($_POST['robots_index']) ? 1 : 0,
    ];
    $form = $d + $form;
    if ($d['name_fa'] === '') { $errors[] = 'نام فارسی دسته الزامیه.'; }
    $image = $cat['image'] ?? null; $ogImage = $cat['og_image'] ?? null;
    $newFiles = [];
    foreach (['image' => 'cover_image', 'og_image' => 'og_image_file'] as $col => $field) {
        if (!$errors && !empty($_FILES[$field]['name'])) {
            try { $p = store_process_image($_FILES[$field], 'categories'); $newFiles[$col] = $p; } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
        }
    }
    if (!$errors) {
        $old = [];
        if (isset($newFiles['image'])) { $old[] = $image; $image = $newFiles['image']['path']; store_unlink_image($newFiles['image']['card_path']); store_unlink_image($newFiles['image']['thumb_path']); }
        if (isset($newFiles['og_image'])) { $old[] = $ogImage; $ogImage = $newFiles['og_image']['path']; store_unlink_image($newFiles['og_image']['card_path']); store_unlink_image($newFiles['og_image']['thumb_path']); }
        if (!empty($_POST['remove_image']) && !isset($newFiles['image'])) { $old[] = $image; $image = null; }
        $slug = unique_slug('shop_categories', slugify(trim($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : ($d['name_en'] !== '' ? $d['name_en'] : $d['name_fa'])), $id ?: null);
        $cols = ['name_fa','name_en','name_ar','short_desc_fa','short_desc_en','short_desc_ar','description_fa','description_en','description_ar','glyph','is_active','display_order','seo_title','seo_description','focus_keyword','related_keywords','canonical_url','og_title','og_description','robots_index'];
        $types = 'ssssssssssiisssssssi'; $vals = array_map(fn($c) => $d[$c], $cols);
        if ($cat) {
            store_exec('UPDATE shop_categories SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ', slug = ?, image = ?, og_image = ? WHERE id = ?', $types . 'sssi', array_merge($vals, [$slug, $image, $ogImage, $id]));
        } else {
            store_exec('INSERT INTO shop_categories (' . implode(', ', $cols) . ', slug, image, og_image) VALUES (' . implode(', ', array_fill(0, count($cols) + 3, '?')) . ')', $types . 'sss', array_merge($vals, [$slug, $image, $ogImage]));
            $id = store_last_id();
        }
        foreach ($old as $o) { store_unlink_image($o); }
        store_audit($cat ? 'category.update' : 'category.create', 'category', $id, $d['name_fa']);
        flash_set('ok', 'دسته ذخیره شد.');
        redirect('shop-category-edit.php?id=' . $id);
    }
}
$pageTitle = $cat ? 'ویرایش دسته: ' . $cat['name_fa'] : 'دسته‌ی جدید';
require __DIR__ . '/_layout_top.php';
$seo = $form; $seoUrlPrefix = '/shop/category/'; $seoSlug = $form['slug']; $seoRequired = false;
?>
<?php if ($errors): ?><div class="a-alert a-alert--error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="a-card" id="mainForm">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="shop-tabs" data-tabs><button type="button" class="is-active" data-tab="general">اطلاعات دسته</button><button type="button" data-tab="seo">سئو</button></div>
  <div class="shop-panel is-active" data-panel="general">
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>نام فارسی *</label><input type="text" name="name_fa" value="<?= e($form['name_fa']) ?>" required maxlength="160"></div>
      <div class="a-field"><label>نام انگلیسی</label><input type="text" name="name_en" value="<?= e($form['name_en']) ?>" maxlength="160" dir="ltr" data-slug-source></div>
      <div class="a-field"><label>نام عربی</label><input type="text" name="name_ar" value="<?= e($form['name_ar']) ?>" maxlength="160"></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>اسلاگ (آدرس) — خالی = خودکار</label><input type="text" name="slug" value="<?= e($form['slug']) ?>" dir="ltr" data-slug-target maxlength="160"></div>
      <div class="a-field"><label>ایموجی (وقتی تصویر نیست)</label><input type="text" name="glyph" value="<?= e($form['glyph']) ?>" maxlength="8"></div>
      <div class="a-field"><label>ترتیب نمایش</label><input type="number" name="display_order" value="<?= (int) $form['display_order'] ?>"></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>توضیح کوتاه (فارسی)</label><textarea name="short_desc_fa" rows="3" maxlength="400"><?= e($form['short_desc_fa']) ?></textarea></div>
      <div class="a-field"><label>توضیح کوتاه (انگلیسی)</label><textarea name="short_desc_en" rows="3" maxlength="400" dir="ltr"><?= e($form['short_desc_en']) ?></textarea></div>
      <div class="a-field"><label>توضیح کوتاه (عربی)</label><textarea name="short_desc_ar" rows="3" maxlength="400"><?= e($form['short_desc_ar']) ?></textarea></div>
    </div>
    <div class="a-field"><label>توضیح کامل صفحه‌ی دسته — فارسی (متن ساده یا HTML امن؛ برای سئو)</label><textarea name="description_fa" rows="6"><?= e($form['description_fa']) ?></textarea></div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>توضیح کامل — انگلیسی</label><textarea name="description_en" rows="4" dir="ltr"><?= e($form['description_en']) ?></textarea></div>
      <div class="a-field"><label>توضیح کامل — عربی</label><textarea name="description_ar" rows="4"><?= e($form['description_ar']) ?></textarea></div>
    </div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>تصویر دسته (تبدیل خودکار به WebP)</label>
        <?php if ($form['image']): ?><img class="shop-thumb" style="width:120px;height:80px" src="../<?= e($form['image']) ?>" alt=""><label><input type="checkbox" name="remove_image" value="1"> حذف تصویر</label><?php endif; ?>
        <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif"></div>
      <div class="a-field a-field--check"><label><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> دسته فعال و قابل نمایش در فروشگاه</label></div>
    </div>
  </div>
  <div class="shop-panel" data-panel="seo">
    <?php require __DIR__ . '/_shop_seo_panel.php'; ?>
    <div class="a-field"><label>تصویر Open Graph (اختیاری)</label><?php if ($form['og_image']): ?><img class="shop-thumb" style="width:120px;height:64px" src="../<?= e($form['og_image']) ?>" alt=""><?php endif; ?><input type="file" name="og_image_file" accept="image/jpeg,image/png,image/webp"></div>
  </div>
  <div class="a-btn-row" style="margin-top:18px;"><button class="a-btn a-btn--primary" type="submit">ذخیره</button><a class="a-btn" href="shop-categories.php">بازگشت</a></div>
</form>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
