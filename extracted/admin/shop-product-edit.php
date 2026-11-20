<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $id ? store_row('SELECT * FROM shop_products WHERE id = ?', 'i', [$id]) : null;
if ($id && !$product) { flash_set('error', 'محصول پیدا نشد.'); redirect('shop-products.php'); }
$errors = [];
$form = $product ?: [
    'sku' => '', 'slug' => '', 'category_id' => 0, 'product_type' => 'website_template', 'delivery_method' => 'file', 'title_fa' => '', 'title_en' => '', 'title_ar' => '',
    'summary_fa' => '', 'summary_en' => '', 'summary_ar' => '', 'description_fa' => '', 'description_en' => '', 'description_ar' => '', 'features' => '', 'requirements' => '',
    'compatibility' => '', 'install_guide' => '', 'usage_guide' => '', 'changelog' => '', 'faq_json' => '', 'license_terms' => '', 'support_info' => '', 'version' => '', 'brand' => '',
    'tags' => '', 'demo_url' => '', 'video_url' => '', 'price' => 0, 'sale_price' => null, 'sale_starts_at' => null, 'sale_ends_at' => null, 'is_free' => 0, 'show_price' => 1,
    'max_quantity' => 1, 'status' => 'draft', 'is_active' => 1, 'is_featured' => 0, 'display_order' => 0, 'seo_title' => '', 'seo_description' => '', 'focus_keyword' => '',
    'related_keywords' => '', 'canonical_url' => '', 'og_title' => '', 'og_description' => '', 'robots_index' => 1,
];
$specsForm = $id ? store_product_specs($id) : [];
$tabAfter = 'general';

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $op = (string) ($_POST['op'] ?? 'save');
    $opName = explode(':', $op)[0];
    $opArg = (int) (explode(':', $op)[1] ?? 0);

    if ($opName === 'save') {
        [$d, $errors] = shop_read_product_form($_POST, $product);
        $form = array_merge($form, $d);
        $form['slug'] = $d['slug_input'] !== '' ? $d['slug_input'] : ($product['slug'] ?? '');
        $specsForm = array_map(fn($s) => $s, $d['specs']);
        if ($d['sku'] !== '' && store_val('SELECT COUNT(*) FROM shop_products WHERE sku = ? AND id <> ?', 'si', [$d['sku'], $id])) {
            $errors[] = 'این SKU قبلاً برای محصول دیگری ثبت شده.';
        }
        // Convert images FIRST (so a bad file stops the save before anything is written).
        $mainUp = !empty($_FILES['main_image']['name']) ? [$_FILES['main_image']] : [];
        $galleryUp = store_normalize_files($_FILES['gallery'] ?? null);
        $processed = [];
        if (!$errors) {
            try {
                foreach (array_merge($mainUp, $galleryUp) as $i => $file) {
                    $processed[] = ['file' => $file, 'main' => $i < count($mainUp)];
                }
                if ($processed && !store_webp_supported()) { throw new RuntimeException('GD با WebP روی سرور فعال نیست؛ آپلود تصویر ممکن نیست.'); }
            } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
        }
        if (!$errors) {
            try {
                $savedId = shop_save_product($product, $d, (int) $admin['id']);
                $warn = [];
                foreach ($processed as $it) {
                    try { store_add_product_image($savedId, $it['file'], $d['title_fa'], $it['main']); }
                    catch (RuntimeException $ex) { $warn[] = ($it['file']['name'] ?? 'image') . ': ' . $ex->getMessage(); }
                }
                store_ensure_main_image($savedId);
                flash_set($warn ? 'error' : 'ok', $warn ? 'محصول ذخیره شد ولی بعضی تصاویر آپلود نشدن — ' . implode(' | ', $warn) : 'محصول ذخیره شد.');
                redirect('shop-product-edit.php?id=' . $savedId . ($processed ? '#images' : ''));
            } catch (Throwable $ex) {
                store_log('product save failed', $ex);
                $errors[] = 'ذخیره‌ی محصول با خطای داخلی مواجه شد: ' . (APP_DEBUG ?? false ? $ex->getMessage() : 'لاگ سرور رو ببین.');
            }
        }
    } elseif ($product) {
        $pid = (int) $product['id'];
        $back = 'shop-product-edit.php?id=' . $pid;
        switch ($opName) {
            case 'delete_image':
                $img = store_row('SELECT * FROM shop_product_images WHERE id = ? AND product_id = ?', 'ii', [$opArg, $pid]);
                if ($img) { store_exec('DELETE FROM shop_product_images WHERE id = ?', 'i', [$opArg]); store_delete_image_row($img); store_ensure_main_image($pid); flash_set('ok', 'تصویر حذف شد.'); }
                redirect($back . '#images');
            case 'main_image':
                if (store_val('SELECT COUNT(*) FROM shop_product_images WHERE id = ? AND product_id = ?', 'ii', [$opArg, $pid])) {
                    store_exec('UPDATE shop_product_images SET is_main = 0 WHERE product_id = ?', 'i', [$pid]);
                    store_exec('UPDATE shop_product_images SET is_main = 1 WHERE id = ?', 'i', [$opArg]);
                    flash_set('ok', 'عکس اصلی تغییر کرد.');
                }
                redirect($back . '#images');
            case 'images_update':
                foreach ((array) ($_POST['img_alt'] ?? []) as $iid => $alt) {
                    store_exec('UPDATE shop_product_images SET alt = ?, sort_order = ? WHERE id = ? AND product_id = ?', 'siii', [mb_substr(trim((string) $alt), 0, 255), (int) ($_POST['img_sort'][$iid] ?? 0), (int) $iid, $pid]);
                }
                flash_set('ok', 'متن جایگزین و ترتیب تصاویر ذخیره شد.');
                redirect($back . '#images');
            case 'file_upload':
                try {
                    $f = store_store_uploaded_file($_FILES['product_file'] ?? []);
                    $title = trim((string) ($_POST['file_title'] ?? '')) ?: $f['original_name'];
                    $kind = in_array($_POST['file_kind'] ?? '', ['source', 'docs', 'install', 'sample', 'extra'], true) ? $_POST['file_kind'] : 'source';
                    store_exec('INSERT INTO shop_product_files (product_id, title, file_kind, description, stored_name, original_name, extension, size_bytes, sha256, sort_order) VALUES (?,?,?,?,?,?,?,?,?, (SELECT COALESCE(MAX(x.sort_order),0)+1 FROM shop_product_files x WHERE x.product_id = ?))', 'issssssisi', [$pid, mb_substr($title, 0, 190), $kind, mb_substr(trim((string) ($_POST['file_desc'] ?? '')), 0, 500), $f['stored_name'], $f['original_name'], $f['extension'], $f['size_bytes'], $f['sha256'], $pid]);
                    store_audit('file.upload', 'product', $pid, $f['original_name']);
                    flash_set('ok', 'فایل آپلود شد و فقط بعد از خرید معتبر برای خریدار دانلودپذیره.');
                } catch (RuntimeException $ex) { flash_set('error', $ex->getMessage()); }
                redirect($back . '#files');
            case 'file_toggle':
                store_exec('UPDATE shop_product_files SET is_active = 1 - is_active WHERE id = ? AND product_id = ?', 'ii', [$opArg, $pid]);
                redirect($back . '#files');
            case 'file_delete':
                $f = store_row('SELECT stored_name FROM shop_product_files WHERE id = ? AND product_id = ?', 'ii', [$opArg, $pid]);
                if ($f) {
                    store_exec('DELETE FROM shop_product_files WHERE id = ?', 'i', [$opArg]);
                    if (preg_match('/^[a-f0-9]{40}$/', $f['stored_name'])) { @unlink(store_private_dir('products') . '/' . $f['stored_name']); }
                    store_audit('file.delete', 'product', $pid);
                    flash_set('ok', 'فایل حذف شد.');
                }
                redirect($back . '#files');
            case 'license_bulk':
                $r = shop_license_bulk_add($pid, (string) ($_POST['license_codes'] ?? ''), trim((string) ($_POST['license_note'] ?? '')));
                store_audit('license.add', 'product', $pid, json_encode($r));
                flash_set($r['added'] ? 'ok' : 'error', 'لایسنس‌های جدید: ' . $r['added'] . ' | تکراری: ' . $r['duplicates'] . ' | نامعتبر: ' . $r['invalid']);
                redirect($back . '#licenses');
        }
        redirect($back);
    }
}

// ---------------------------------------------------------------- view data
$images = $id ? store_product_gallery($id) : [];
$files = $id ? store_rows('SELECT * FROM shop_product_files WHERE product_id = ? ORDER BY sort_order, id', 'i', [$id]) : [];
$stock = $id ? store_rows("SELECT status, COUNT(*) c FROM shop_licenses WHERE product_id = ? GROUP BY status", 'i', [$id]) : [];
$stockMap = array_column($stock, 'c', 'status');
$cats = store_rows('SELECT id, name_fa FROM shop_categories ORDER BY display_order, id');
$faqRows = []; foreach (json_decode((string) ($form['faq_json'] ?? ''), true) ?: [] as $r) { $faqRows[] = $r; }
$checklist = shop_product_checklist($form + ['delivery_method' => $form['delivery_method']], count($images), count(array_filter($files, fn($f) => $f['is_active'])), (int) ($stockMap['available'] ?? 0));
$pageTitle = $product ? 'ویرایش محصول: ' . $product['title_fa'] : 'محصول جدید';
require __DIR__ . '/_layout_top.php';
$f = static fn(string $k) => e((string) ($form[$k] ?? ''));
$dt = static fn(?string $v) => $v ? e(str_replace(' ', 'T', substr($v, 0, 16))) : '';
$seo = $form; $seoUrlPrefix = '/shop/'; $seoSlug = $form['slug']; $seoRequired = true;
$previewUrl = $product ? '../shop/' . $product['slug'] . '?preview=' . store_preview_token($product['slug']) : '';
?>
<?php if ($errors): ?><div class="a-alert a-alert--error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<?php if ($product): ?>
<div class="a-btn-row" style="margin-bottom:14px;">
  <a class="a-btn" href="<?= e($previewUrl) ?>" target="_blank">پیش‌نمایش صفحه‌ی محصول ↗</a>
  <?php if ($product['status'] === 'published' && $product['is_active'] && !$product['deleted_at']): ?><a class="a-btn" href="../shop/<?= e($product['slug']) ?>" target="_blank">مشاهده در فروشگاه ↗</a><?php endif; ?>
  <a class="a-btn" href="shop-products.php">بازگشت به لیست</a>
</div>
<?php endif; ?>

<!-- sibling forms used by buttons elsewhere in the page via the form="" attribute (HTML forms cannot nest) -->
<form id="opForm" method="post" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
<form id="fileForm" method="post" enctype="multipart/form-data" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="op" value="file_upload"></form>

<form method="post" enctype="multipart/form-data" class="a-card" id="productForm" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="op" value="save">
  <div class="shop-tabs" data-tabs>
    <button type="button" class="is-active" data-tab="general">اطلاعات اصلی</button>
    <button type="button" data-tab="price">قیمت و فروش</button>
    <button type="button" data-tab="content">توضیحات</button>
    <button type="button" data-tab="specs">مشخصات و FAQ</button>
    <button type="button" data-tab="images">تصاویر</button>
    <button type="button" data-tab="files">فایل‌ها</button>
    <button type="button" data-tab="licenses">لایسنس</button>
    <button type="button" data-tab="seo">سئو</button>
  </div>

  <!-- GENERAL -->
  <div class="shop-panel is-active" data-panel="general">
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>عنوان فارسی *</label><input type="text" name="title_fa" value="<?= $f('title_fa') ?>" required maxlength="255"></div>
      <div class="a-field"><label>عنوان انگلیسی</label><input type="text" name="title_en" value="<?= $f('title_en') ?>" maxlength="255" dir="ltr" data-slug-source></div>
      <div class="a-field"><label>عنوان عربی</label><input type="text" name="title_ar" value="<?= $f('title_ar') ?>" maxlength="255"></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>آدرس یکتا (slug) — خالی = خودکار</label><input type="text" name="slug" value="<?= $f('slug') ?>" dir="ltr" maxlength="190" data-slug-target><small class="a-muted">آدرس نهایی: /shop/<?= $f('slug') ?: '…' ?> — تغییر آدرس، ریدایرکت ۳۰۱ از آدرس قبلی می‌سازه.</small></div>
      <div class="a-field"><label>کد محصول (SKU)</label><input type="text" name="sku" value="<?= $f('sku') ?>" dir="ltr" maxlength="60" placeholder="خالی = خودکار"></div>
      <div class="a-field"><label>دسته‌بندی *</label><select name="category_id" required><option value="">انتخاب کن…</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $form['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name_fa']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>نوع محصول</label><select name="product_type"><?php foreach (store_product_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $form['product_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="a-field"><label>روش تحویل</label><select name="delivery_method"><?php foreach (store_delivery_methods() as $k => $l): ?><option value="<?= e($k) ?>"<?= $form['delivery_method'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><small class="a-muted">«فایل»: ZIP/RAR · «لایسنس»: کد دوره/اسپات‌پلیر · «فایل+لایسنس»: هر دو</small></div>
      <div class="a-field"><label>نسخه</label><input type="text" name="version" value="<?= $f('version') ?>" maxlength="40" dir="ltr" placeholder="1.0.0"></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>سازنده / برند</label><input type="text" name="brand" value="<?= $f('brand') ?>" maxlength="120"></div>
      <div class="a-field"><label>تگ‌ها (با ویرگول)</label><input type="text" name="tags" value="<?= $f('tags') ?>" maxlength="400"></div>
      <div class="a-field"><label>ترتیب نمایش</label><input type="number" name="display_order" value="<?= (int) $form['display_order'] ?>"></div>
    </div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>لینک دمو (اختیاری)</label><input type="url" name="demo_url" value="<?= $f('demo_url') ?>" dir="ltr" placeholder="https://"></div>
      <div class="a-field"><label>لینک ویدیو معرفی (اختیاری)</label><input type="url" name="video_url" value="<?= $f('video_url') ?>" dir="ltr" placeholder="https://"></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>خلاصه (فارسی) — زیر عنوان و روی کارت</label><textarea name="summary_fa" rows="3" maxlength="500"><?= $f('summary_fa') ?></textarea></div>
      <div class="a-field"><label>خلاصه (انگلیسی)</label><textarea name="summary_en" rows="3" maxlength="500" dir="ltr"><?= $f('summary_en') ?></textarea></div>
      <div class="a-field"><label>خلاصه (عربی)</label><textarea name="summary_ar" rows="3" maxlength="500"><?= $f('summary_ar') ?></textarea></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>وضعیت انتشار</label><select name="status"><option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>پیش‌نویس (عمومی نیست)</option><option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>منتشرشده</option></select></div>
      <div class="a-field a-field--check"><label><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> فروش فعال (غیرفعال = مخفی و غیرقابل خرید)</label></div>
      <div class="a-field a-field--check"><label><input type="checkbox" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>> محصول ویژه</label></div>
    </div>
    <?php if ($product): ?><div class="a-card" style="background:var(--a-panel-2)"><strong>آمادگی انتشار</strong><ul class="seo-checks"><?php foreach ($checklist as [$ok, $label]): ?><li class="<?= $ok ? 'is-ok' : 'is-bad' ?>"><?= e($label) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  </div>

  <!-- PRICE -->
  <div class="shop-panel" data-panel="price">
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>قیمت (<?= e(store_currency_label('fa')) ?>)</label><input type="text" inputmode="numeric" name="price" value="<?= (int) $form['price'] ? e(number_format((int) $form['price'])) : '' ?>" data-money dir="ltr"></div>
      <div class="a-field"><label>قیمت با تخفیف (اختیاری)</label><input type="text" inputmode="numeric" name="sale_price" value="<?= $form['sale_price'] !== null ? e(number_format((int) $form['sale_price'])) : '' ?>" data-money dir="ltr"></div>
      <div class="a-field a-field--check"><label><input type="checkbox" name="is_free" value="1" <?= $form['is_free'] ? 'checked' : '' ?>> محصول رایگان (بدون پرداخت)</label></div>
    </div>
    <div class="a-grid a-grid--3">
      <div class="a-field"><label>شروع تخفیف (اختیاری)</label><input type="datetime-local" name="sale_starts_at" value="<?= $dt($form['sale_starts_at']) ?>"></div>
      <div class="a-field"><label>پایان تخفیف (اختیاری)</label><input type="datetime-local" name="sale_ends_at" value="<?= $dt($form['sale_ends_at']) ?>"></div>
      <div class="a-field" data-qty-field><label>حداکثر تعداد در هر سفارش (برای لایسنس)</label><input type="number" name="max_quantity" min="1" max="100" value="<?= (int) $form['max_quantity'] ?>"></div>
    </div>
    <div class="a-field a-field--check"><label><input type="checkbox" name="show_price" value="1" <?= $form['show_price'] ? 'checked' : '' ?>> نمایش قیمت در صفحه</label></div>
    <p class="a-muted">قیمت نهایی همیشه روی سرور از همین فیلدها محاسبه می‌شه؛ مرورگر خریدار نمی‌تونه آن را تغییر بده. تخفیف بدون تاریخ، همیشه فعاله.</p>
  </div>

  <!-- CONTENT -->
  <div class="shop-panel" data-panel="content">
    <p class="a-muted">می‌تونی متن ساده بنویسی (پاراگراف‌ها با یک خط خالی جدا می‌شن) یا HTML امن (p, h2–h4, ul/ol, strong, a, table, pre/code…). اسکریپت و استایل خودکار حذف می‌شن.</p>
    <div class="a-field"><label>توضیحات کامل — فارسی</label><textarea name="description_fa" rows="10"><?= $f('description_fa') ?></textarea></div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>توضیحات — انگلیسی</label><textarea name="description_en" rows="6" dir="ltr"><?= $f('description_en') ?></textarea></div>
      <div class="a-field"><label>توضیحات — عربی</label><textarea name="description_ar" rows="6"><?= $f('description_ar') ?></textarea></div>
    </div>
    <div class="a-field"><label>ویژگی‌ها (هر خط یک ویژگی)</label><textarea name="features" rows="5"><?= $f('features') ?></textarea></div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>پیش‌نیازها</label><textarea name="requirements" rows="4"><?= $f('requirements') ?></textarea></div>
      <div class="a-field"><label>سازگاری</label><textarea name="compatibility" rows="4"><?= $f('compatibility') ?></textarea></div>
    </div>
    <div class="a-field"><label>راهنمای نصب</label><textarea name="install_guide" rows="6"><?= $f('install_guide') ?></textarea></div>
    <div class="a-field"><label>راهنمای استفاده</label><textarea name="usage_guide" rows="5"><?= $f('usage_guide') ?></textarea></div>
    <div class="a-field"><label>تاریخچه‌ی تغییرات (Changelog)</label><textarea name="changelog" rows="5"><?= $f('changelog') ?></textarea></div>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label>شرایط لایسنس</label><textarea name="license_terms" rows="4"><?= $f('license_terms') ?></textarea></div>
      <div class="a-field"><label>پشتیبانی</label><textarea name="support_info" rows="4"><?= $f('support_info') ?></textarea></div>
    </div>
  </div>

  <!-- SPECS + FAQ -->
  <div class="shop-panel" data-panel="specs">
    <h3>جدول مشخصات</h3>
    <div data-repeater data-seed>
      <div data-rows>
        <?php foreach ($specsForm as $s): ?><div class="shop-repeater__row" data-row>
          <input name="spec_label_fa[]" placeholder="عنوان (فا)" value="<?= e($s['label_fa']) ?>"><input name="spec_label_en[]" placeholder="Label (en)" dir="ltr" value="<?= e($s['label_en']) ?>"><input name="spec_label_ar[]" placeholder="عنوان (عر)" value="<?= e($s['label_ar']) ?>">
          <input name="spec_value_fa[]" placeholder="مقدار (فا)" value="<?= e($s['value_fa']) ?>"><input name="spec_value_en[]" placeholder="Value (en)" dir="ltr" value="<?= e($s['value_en']) ?>"><input name="spec_value_ar[]" placeholder="مقدار (عر)" value="<?= e($s['value_ar']) ?>">
          <button type="button" class="a-btn a-btn--sm a-btn--danger" data-remove-row>×</button></div><?php endforeach; ?>
      </div>
      <template><div class="shop-repeater__row" data-row>
        <input name="spec_label_fa[]" placeholder="عنوان (فا)"><input name="spec_label_en[]" placeholder="Label (en)" dir="ltr"><input name="spec_label_ar[]" placeholder="عنوان (عر)">
        <input name="spec_value_fa[]" placeholder="مقدار (فا)"><input name="spec_value_en[]" placeholder="Value (en)" dir="ltr"><input name="spec_value_ar[]" placeholder="مقدار (عر)">
        <button type="button" class="a-btn a-btn--sm a-btn--danger" data-remove-row>×</button></div></template>
      <button type="button" class="a-btn a-btn--sm" data-add>+ ردیف مشخصات</button>
    </div>
    <h3 style="margin-top:26px;">سوالات متداول (FAQ — با اسکیمای FAQPage)</h3>
    <div data-repeater>
      <div data-rows>
        <?php foreach ($faqRows as $r): ?><div class="shop-repeater__row shop-repeater__row--faq" data-row><input name="faq_q[]" placeholder="سوال" value="<?= e($r['q']) ?>"><textarea name="faq_a[]" rows="2" placeholder="پاسخ"><?= e($r['a']) ?></textarea><button type="button" class="a-btn a-btn--sm a-btn--danger" data-remove-row>×</button></div><?php endforeach; ?>
      </div>
      <template><div class="shop-repeater__row shop-repeater__row--faq" data-row><input name="faq_q[]" placeholder="سوال"><textarea name="faq_a[]" rows="2" placeholder="پاسخ"></textarea><button type="button" class="a-btn a-btn--sm a-btn--danger" data-remove-row>×</button></div></template>
      <button type="button" class="a-btn a-btn--sm" data-add>+ سوال جدید</button>
    </div>
  </div>

  <!-- IMAGES -->
  <div class="shop-panel" data-panel="images">
    <p class="a-muted">همه‌ی تصاویر پس از آپلود خودکار به <b>WebP</b> تبدیل و در سه اندازه (بزرگ، کارت، بندانگشتی) ذخیره می‌شن. تعداد تصاویر گالری محدودیت نداره. فرمت‌های مجاز: JPG، PNG، WEBP، GIF — حداکثر <?= (int) STORE_MAX_IMAGE_MB ?>MB برای هر عکس.</p>
    <?php if (!store_webp_supported()): ?><div class="a-alert a-alert--error">GD با WebP روی این سرور فعال نیست؛ آپلود تصویر کار نمی‌کنه.</div><?php endif; ?>
    <div class="a-grid a-grid--2">
      <div class="a-field"><label><?= $images ? 'جایگزینی عکس اصلی (عکس قبلی به گالری می‌ره)' : 'عکس اصلی' ?></label><input type="file" name="main_image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#mainPrev"><div id="mainPrev"></div></div>
      <div class="a-field"><label>عکس‌های فرعی (چندتایی، هر تعداد)</label><input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#galPrev"><div id="galPrev" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px"></div></div>
    </div>
    <p class="a-muted">پس از انتخاب فایل‌ها، دکمه‌ی «ذخیره‌ی محصول» پایین صفحه رو بزن.</p>
    <?php if ($images): ?>
      <h3>تصاویر فعلی</h3>
      <div class="shop-img-grid">
        <?php foreach ($images as $img): ?>
          <div class="shop-img-card<?= $img['is_main'] ? ' is-main' : '' ?>">
            <img src="../<?= e($img['card_path']) ?>" alt="">
            <?= $img['is_main'] ? '<span class="a-badge a-badge--ok">عکس اصلی</span>' : '<button class="a-btn a-btn--sm" type="submit" form="opForm" name="op" value="main_image:' . (int) $img['id'] . '">انتخاب به‌عنوان اصلی</button>' ?>
            <input form="opForm" name="img_alt[<?= (int) $img['id'] ?>]" value="<?= e($img['alt']) ?>" placeholder="متن جایگزین (alt)">
            <input form="opForm" type="number" name="img_sort[<?= (int) $img['id'] ?>]" value="<?= (int) $img['sort_order'] ?>" title="ترتیب">
            <small class="a-muted" dir="ltr"><?= (int) $img['width'] ?>×<?= (int) $img['height'] ?> WebP</small>
            <button class="a-btn a-btn--sm a-btn--danger" type="submit" form="opForm" name="op" value="delete_image:<?= (int) $img['id'] ?>" data-confirm="این تصویر حذف بشه؟">حذف</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="a-btn" type="submit" form="opForm" name="op" value="images_update">ذخیره‌ی alt و ترتیب</button>
    <?php endif; ?>
  </div>

  <!-- FILES -->
  <div class="shop-panel" data-panel="files">
    <?php if (!$product): ?><div class="a-empty">اول محصول رو ذخیره کن، بعد فایل‌های تحویلی رو اینجا آپلود کن.</div><?php else: ?>
      <p class="a-muted">فایل‌ها (ZIP / RAR / 7Z / PDF / TXT) در پوشه‌ی خصوصی ذخیره می‌شن و هیچ آدرس عمومی ندارن؛ فقط خریدار با پرداخت تأییدشده از داشبورد خودش دانلود می‌کنه. حداکثر حجم: <?= (int) STORE_MAX_FILE_MB ?>MB (و محدودیت php.ini: <?= e(ini_get('upload_max_filesize')) ?>).</p>
      <?php if ($files): ?><div class="a-table-wrap"><table class="a-table"><tr><th>عنوان</th><th>نوع</th><th>حجم</th><th>SHA-256</th><th>وضعیت</th><th></th></tr>
        <?php foreach ($files as $fl): ?><tr><td><?= e($fl['title']) ?><br><small class="a-muted" dir="ltr"><?= e($fl['original_name']) ?></small></td><td><?= e($fl['file_kind']) ?></td><td dir="ltr"><?= $fl['size_bytes'] >= 1048576 ? round($fl['size_bytes'] / 1048576, 1) . ' MB' : round($fl['size_bytes'] / 1024) . ' KB' ?></td><td><small class="code-chip"><?= e(substr($fl['sha256'], 0, 12)) ?>…</small></td><td><?= $fl['is_active'] ? '<span class="a-badge a-badge--ok">فعال</span>' : '<span class="a-badge a-badge--muted">غیرفعال</span>' ?></td>
          <td><div class="a-btn-row"><button class="a-btn a-btn--sm" type="submit" form="opForm" name="op" value="file_toggle:<?= (int) $fl['id'] ?>"><?= $fl['is_active'] ? 'غیرفعال' : 'فعال' ?></button><button class="a-btn a-btn--sm a-btn--danger" type="submit" form="opForm" name="op" value="file_delete:<?= (int) $fl['id'] ?>" data-confirm="فایل برای همیشه حذف بشه؟ خریداران قبلی دیگر نمی‌تونن دانلودش کنن.">حذف</button></div></td></tr><?php endforeach; ?></table></div><?php endif; ?>
      <h3>آپلود فایل جدید</h3>
      <div class="a-grid a-grid--3">
        <div class="a-field"><label>عنوان فایل</label><input form="fileForm" type="text" name="file_title" maxlength="190" placeholder="مثلاً: سورس کامل v1.0"></div>
        <div class="a-field"><label>نوع</label><select form="fileForm" name="file_kind"><option value="source">سورس</option><option value="docs">مستندات</option><option value="install">نصب</option><option value="sample">نمونه</option><option value="extra">اضافی</option></select></div>
        <div class="a-field"><label>فایل</label><input form="fileForm" type="file" name="product_file" accept=".zip,.rar,.7z,.pdf,.txt" required></div>
      </div>
      <div class="a-field"><label>توضیح کوتاه (اختیاری)</label><input form="fileForm" type="text" name="file_desc" maxlength="500"></div>
      <button class="a-btn a-btn--primary" type="submit" form="fileForm">آپلود فایل</button>
    <?php endif; ?>
  </div>

  <!-- LICENSES -->
  <div class="shop-panel" data-panel="licenses">
    <?php if (!$product): ?><div class="a-empty">اول محصول رو ذخیره کن.</div><?php else: ?>
      <div class="a-grid a-grid--4" style="margin-bottom:14px">
        <?php foreach (['available' => 'آزاد', 'reserved' => 'رزرو', 'assigned' => 'تحویل‌شده', 'disabled' => 'غیرفعال'] as $k => $l): ?><div class="a-card a-stat"><b><?= fa_digits((string) (int) ($stockMap[$k] ?? 0)) ?></b><span><?= $l ?></span></div><?php endforeach; ?>
      </div>
      <?php if (!in_array($form['delivery_method'], ['license', 'file_license'], true)): ?><div class="a-alert a-alert--error">روش تحویل این محصول «لایسنس» نیست؛ لایسنس‌ها فقط وقتی استفاده می‌شن که روش تحویل «کد/لایسنس» یا «فایل+لایسنس» باشه.</div><?php endif; ?>
      <div class="a-field"><label>افزودن لایسنس (هر خط یک کد — برای دوره‌ها: لایسنس اسپات‌پلیر)</label><textarea form="opForm" name="license_codes" rows="6" dir="ltr" placeholder="XXXX-XXXX-XXXX&#10;YYYY-YYYY-YYYY"></textarea></div>
      <div class="a-field"><label>یادداشت داخلی (اختیاری)</label><input form="opForm" type="text" name="license_note" maxlength="255"></div>
      <div class="a-btn-row"><button class="a-btn a-btn--primary" type="submit" form="opForm" name="op" value="license_bulk">افزودن لایسنس‌ها</button><a class="a-btn" href="shop-licenses.php?product=<?= (int) $id ?>">مدیریت همه‌ی لایسنس‌ها</a></div>
      <p class="a-muted">کدها رمزنگاری‌شده ذخیره می‌شن، تکراری‌ها رد می‌شن و هر کد فقط به یک خریدار تحویل داده می‌شه.</p>
    <?php endif; ?>
  </div>

  <!-- SEO -->
  <div class="shop-panel" data-panel="seo">
    <div class="a-btn-row" style="margin-bottom:12px"><button type="button" class="a-btn a-btn--sm" data-seo-autofill>پر کردن خودکار از عنوان و خلاصه</button></div>
    <?php require __DIR__ . '/_shop_seo_panel.php'; ?>
    <p class="a-muted" style="margin-top:12px">اسکیمای Product (قیمت، موجودی، امتیاز واقعی)، BreadcrumbList و FAQPage خودکار به صفحه اضافه می‌شن. امتیاز فقط از نظرات تأییدشده محاسبه می‌شه.</p>
  </div>

  <div class="a-btn-row" style="margin-top:20px;position:sticky;bottom:0;background:var(--a-panel);padding:12px 0;">
    <button class="a-btn a-btn--primary" type="submit">ذخیره‌ی محصول</button>
    <a class="a-btn" href="shop-products.php">انصراف</a>
  </div>
</form>

<?php if ($product): ?>
<div class="a-card" style="margin-top:16px;">
  <strong>عملیات</strong>
  <div class="a-btn-row" style="margin-top:10px;">
    <form method="post" action="shop-products.php" class="a-inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="duplicate"><button class="a-btn" type="submit">کپی به‌عنوان پیش‌نویس</button></form>
    <form method="post" action="shop-products.php" class="a-inline-form" data-confirm="محصول به سطل زباله بره؟ از فروشگاه مخفی می‌شه ولی خریداران قبلی همچنان دسترسی دارن."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="trash"><button class="a-btn a-btn--danger" type="submit">انتقال به سطل زباله</button></form>
  </div>
</div>
<?php endif; ?>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
