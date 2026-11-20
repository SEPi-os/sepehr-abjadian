<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
shop_require_owner($admin);
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $errors = [];
    $int = static fn(string $k, int $min, int $max, int $def) => (string) max($min, min($max, (int) ($_POST[$k] ?? $def)));
    $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_POST['currency_code'] ?? 'IRT'))) ?: 'IRT';
    $new = [
        'shop_enabled' => isset($_POST['shop_enabled']) ? '1' : '0',
        'shop_name' => mb_substr(trim((string) ($_POST['shop_name'] ?? '')), 0, 120),
        'shop_description' => mb_substr(trim((string) ($_POST['shop_description'] ?? '')), 0, 320),
        'shop_currency_code' => substr($code, 0, 4),
        'shop_currency_fa' => mb_substr(trim((string) ($_POST['currency_fa'] ?? 'تومان')) ?: 'تومان', 0, 20),
        'shop_currency_en' => mb_substr(trim((string) ($_POST['currency_en'] ?? 'Toman')) ?: 'Toman', 0, 20),
        'shop_currency_ar' => mb_substr(trim((string) ($_POST['currency_ar'] ?? 'تومان')) ?: 'تومان', 0, 20),
        'shop_price_digits' => ($_POST['price_digits'] ?? 'fa') === 'en' ? 'en' : 'fa',
        'shop_per_page' => $int('per_page', 4, 48, 12),
        'shop_max_qty_per_item' => $int('max_qty', 1, 100, 5),
        'shop_reservation_minutes' => $int('reservation', 5, 1440, 30),
        'shop_download_expiry_minutes' => $int('dl_expiry', 1, 1440, 15),
        'shop_download_max_per_file' => $int('dl_max', 0, 1000, 10),
        'shop_reviews_enabled' => isset($_POST['reviews_enabled']) ? '1' : '0',
        'shop_review_min_chars' => $int('rv_min', 1, 500, 10),
        'shop_review_max_chars' => $int('rv_max', 50, 10000, 2000),
        'shop_seo_title_suffix' => mb_substr((string) ($_POST['seo_suffix'] ?? ''), 0, 100),
    ];
    if ($new['shop_name'] === '') { $errors[] = 'نام فروشگاه الزامیه.'; }
    if (!empty($_FILES['og_image']['name'])) {
        try { $p = store_process_image($_FILES['og_image'], 'shop'); store_unlink_image($p['card_path']); store_unlink_image($p['thumb_path']); store_unlink_image(store_setting('shop_default_og_image')); $new['shop_default_og_image'] = $p['path']; }
        catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    }
    if ($errors) { flash_set('error', implode(' ', $errors)); }
    else {
        foreach ($new as $k => $v) { store_setting_set($k, $v); }
        store_audit('settings.update', 'settings', null, 'shop settings');
        flash_set('ok', 'تنظیمات فروشگاه ذخیره شد.');
    }
    redirect('shop-settings.php');
}
$pageTitle = 'تنظیمات فروشگاه';
require __DIR__ . '/_layout_top.php';
$s = static fn(string $k, string $d = '') => e(store_setting($k, $d));
$checks = shop_system_checks();
$cronBase = 'php ' . BASE_PATH . '/cron/';
?>
<form method="post" enctype="multipart/form-data" class="a-card"><?= csrf_field() ?>
  <h2>عمومی</h2>
  <div class="a-grid a-grid--2">
    <div class="a-field"><label>نام فروشگاه</label><input type="text" name="shop_name" value="<?= $s('shop_name') ?>" maxlength="120" required></div>
    <div class="a-field a-field--check"><label><input type="checkbox" name="shop_enabled" value="1" <?= store_setting('shop_enabled', '1') === '1' ? 'checked' : '' ?>> فروشگاه باز است (خاموش = مشاهده آزاد، ثبت سفارش بسته)</label></div>
  </div>
  <div class="a-field"><label>توضیح فروشگاه (متای صفحه‌ی /shop)</label><textarea name="shop_description" rows="2" maxlength="320"><?= $s('shop_description') ?></textarea></div>
  <div class="a-grid a-grid--2">
    <div class="a-field"><label>پسوند عنوان سئو (وقتی عنوان سئوی محصول خالیه)</label><input type="text" name="seo_suffix" value="<?= $s('shop_seo_title_suffix') ?>" maxlength="100"></div>
    <div class="a-field"><label>تصویر پیش‌فرض Open Graph فروشگاه</label><?php if (store_setting('shop_default_og_image') !== ''): ?><img class="shop-thumb" style="width:100px;height:56px" src="../<?= $s('shop_default_og_image') ?>" alt=""><?php endif; ?><input type="file" name="og_image" accept="image/jpeg,image/png,image/webp"></div>
  </div>
  <h2>واحد پول و نمایش قیمت</h2>
  <div class="a-grid a-grid--4">
    <div class="a-field"><label>کد ارز (IRT=تومان، IRR=ریال)</label><input type="text" name="currency_code" value="<?= $s('shop_currency_code', 'IRT') ?>" maxlength="4" dir="ltr"></div>
    <div class="a-field"><label>برچسب فارسی</label><input type="text" name="currency_fa" value="<?= $s('shop_currency_fa') ?>" maxlength="20"></div>
    <div class="a-field"><label>برچسب انگلیسی</label><input type="text" name="currency_en" value="<?= $s('shop_currency_en') ?>" maxlength="20" dir="ltr"></div>
    <div class="a-field"><label>برچسب عربی</label><input type="text" name="currency_ar" value="<?= $s('shop_currency_ar') ?>" maxlength="20"></div>
  </div>
  <div class="a-field"><label>ارقام قیمت در زبان فارسی</label><select name="price_digits"><option value="fa"<?= store_setting('shop_price_digits', 'fa') === 'fa' ? ' selected' : '' ?>>فارسی (۱۲۳)</option><option value="en"<?= store_setting('shop_price_digits', 'fa') === 'en' ? ' selected' : '' ?>>انگلیسی (123)</option></select></div>
  <h2>فروشگاه و سبد</h2>
  <div class="a-grid a-grid--4">
    <div class="a-field"><label>محصول در هر صفحه</label><input type="number" name="per_page" min="4" max="48" value="<?= $s('shop_per_page', '12') ?>"></div>
    <div class="a-field"><label>سقف تعداد هر محصول در سفارش</label><input type="number" name="max_qty" min="1" max="100" value="<?= $s('shop_max_qty_per_item', '5') ?>"></div>
    <div class="a-field"><label>مدت رزرو لایسنس تا پرداخت (دقیقه)</label><input type="number" name="reservation" min="5" max="1440" value="<?= $s('shop_reservation_minutes', '30') ?>"></div>
  </div>
  <h2>دانلود</h2>
  <div class="a-grid a-grid--4">
    <div class="a-field"><label>اعتبار لینک دانلود (دقیقه)</label><input type="number" name="dl_expiry" min="1" max="1440" value="<?= $s('shop_download_expiry_minutes', '15') ?>"></div>
    <div class="a-field"><label>سقف دانلود هر فایل برای هر خرید (۰ = نامحدود)</label><input type="number" name="dl_max" min="0" max="1000" value="<?= $s('shop_download_max_per_file', '10') ?>"></div>
  </div>
  <h2>نظرات</h2>
  <div class="a-grid a-grid--4">
    <div class="a-field a-field--check"><label><input type="checkbox" name="reviews_enabled" value="1" <?= store_setting('shop_reviews_enabled', '1') === '1' ? 'checked' : '' ?>> ثبت نظر فعال</label></div>
    <div class="a-field"><label>حداقل کاراکتر نظر</label><input type="number" name="rv_min" min="1" max="500" value="<?= $s('shop_review_min_chars', '10') ?>"></div>
    <div class="a-field"><label>حداکثر کاراکتر نظر</label><input type="number" name="rv_max" min="50" max="10000" value="<?= $s('shop_review_max_chars', '2000') ?>"></div>
  </div>
  <p class="a-muted">همه‌ی نظرها همیشه قبل از نمایش نیاز به تأیید مدیر دارن (قابل خاموش‌کردن نیست).</p>
  <button class="a-btn a-btn--primary" type="submit">ذخیره‌ی تنظیمات</button>
</form>

<div class="a-card" style="margin-top:16px"><h2>وضعیت سیستم</h2>
  <div class="a-table-wrap"><table class="a-table"><?php foreach ($checks as [$name, $ok, $info]): ?><tr><td><?= e($name) ?></td><td><?= $ok ? '<span class="a-badge a-badge--ok">OK</span>' : '<span class="a-badge a-badge--warn">نیاز به توجه</span>' ?></td><td><small><?= e($info) ?></small></td></tr><?php endforeach; ?></table></div>
  <p class="a-muted" style="margin-top:12px">اطلاعات درگاه (merchant id و…) هیچ‌وقت در دیتابیس ذخیره یا اینجا نمایش داده نمی‌شه؛ فقط در <code>includes/store-config.php</code> قرار می‌گیره. راهنما: <code>STORE_SETUP.md</code>.</p>
</div>
<div class="a-card" style="margin-top:16px"><h2>کارهای زمان‌بندی‌شده (cron) — توصیه‌شده</h2>
  <p class="a-muted">این دو دستور رو در cron هاست (هر ۵ دقیقه) اضافه کن تا پرداخت‌های «تأیید نامشخص» خودکار پیگیری و رزروهای منقضی آزاد بشن:</p>
  <pre class="code-chip" style="display:block;white-space:pre-wrap;padding:12px">*/5 * * * * <?= e($cronBase) ?>verify-pending-payments.php
*/10 * * * * <?= e($cronBase) ?>release-reservations.php</pre>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
