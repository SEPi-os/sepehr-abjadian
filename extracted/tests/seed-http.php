<?php
/** Seeds a buyer, an admin and three demo products for the HTTP-level tests (test DB only). */
if (PHP_SAPI !== 'cli') { exit; }
if (getenv('STORE_ALLOW_TESTS') !== '1') { fwrite(STDERR, "Refusing to run: these tests create and delete rows. Use a TEST database and set STORE_ALLOW_TESTS=1.\n"); exit(1); }
putenv('STORE_TEST_MODE=1');
define('STORE_GATEWAY', 'mock');
define('STORE_TESTING', true);
require_once __DIR__ . '/../includes/store-payment.php';
require_once __DIR__ . '/../includes/store-images.php';

store_exec("DELETE FROM users WHERE username IN ('buyer1','buyer2')");
foreach (['buyer1', 'buyer2'] as $u) {
    store_exec("INSERT INTO users (name, username, email, password) VALUES (?,?,?,?)", 'ssss', ["خریدار " . $u, $u, "$u@example.com", password_hash('Buyer#12345', PASSWORD_ARGON2ID)]);
}
store_exec("DELETE FROM admins WHERE username = 'tadmin'");
store_exec("INSERT INTO admins (name, username, email, password, role) VALUES ('Test Admin','tadmin','tadmin@example.com',?, 'owner')", 's', [password_hash('Admin#12345', PASSWORD_ARGON2ID)]);

function img(int $w, int $h, array $rgb): array {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    imagestring($im, 5, 20, 20, 'DEMO', imagecolorallocate($im, 255, 255, 255));
    $f = tempnam(sys_get_temp_dir(), 'seed'); imagejpeg($im, $f, 85);
    return ['name' => 'demo.jpg', 'tmp_name' => $f, 'error' => 0, 'size' => filesize($f)];
}
function mkp(array $d): int {
    store_exec("INSERT INTO shop_products (sku, slug, category_id, product_type, delivery_method, title_fa, title_en, summary_fa, summary_en, description_fa, description_en, price, sale_price, max_quantity, status, is_active, published_at, seo_title, seo_description, focus_keyword, features, install_guide, version, brand, faq_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'published', 1, NOW(), ?,?,?,?,?,?,?, ?)", 'ssissssssssiiissssssss',
        [$d['sku'], $d['slug'], $d['cat'], $d['type'], $d['delivery'], $d['fa'], $d['en'], 'خلاصه ' . $d['fa'], 'Summary ' . $d['en'], "<p>توضیحات کامل <strong>" . $d['fa'] . "</strong></p><script>alert(1)</script>", "<p>Full description of " . $d['en'] . "</p>", $d['price'], $d['sale'], $d['max'],
         $d['fa'] . ' | فروشگاه', 'خرید ' . $d['fa'] . ' با تحویل فوری', $d['kw'], "ریسپانسیو\nسئو شده\nسورس کامل", "۱. فایل را استخراج کن\n۲. نصب کن", '1.0.0', 'Sepehr', json_encode([['q' => 'آیا آپدیت دارد؟', 'a' => 'بله، یک سال آپدیت رایگان.']], JSON_UNESCAPED_UNICODE)]);
    return store_last_id();
}
$ids = [];
$ids['tpl'] = mkp(['sku' => 'TPL-001', 'slug' => 'luxury-landing-template', 'cat' => 2, 'type' => 'website_template', 'delivery' => 'file', 'fa' => 'قالب لندینگ لوکس', 'en' => 'Luxury Landing Template', 'price' => 490000, 'sale' => 390000, 'max' => 1, 'kw' => 'قالب لندینگ']);
$ids['course'] = mkp(['sku' => 'CRS-001', 'slug' => 'n8n-automation-course', 'cat' => 1, 'type' => 'course', 'delivery' => 'license', 'fa' => 'دوره اتوماسیون n8n', 'en' => 'n8n Automation Course', 'price' => 1200000, 'sale' => null, 'max' => 3, 'kw' => 'دوره n8n']);
$ids['plugin'] = mkp(['sku' => 'WPP-001', 'slug' => 'seo-booster-plugin', 'cat' => 3, 'type' => 'wp_plugin', 'delivery' => 'file_license', 'fa' => 'افزونه سئو بوستر', 'en' => 'SEO Booster Plugin', 'price' => 650000, 'sale' => null, 'max' => 2, 'kw' => 'افزونه سئو']);
foreach ([[ 'tpl', 3], ['course', 1], ['plugin', 2]] as [$k, $n]) {
    for ($i = 0; $i < $n; $i++) store_add_product_image($ids[$k], img(1400 + $i * 50, 900, [30 + $i * 40, 80, 190 - $i * 30]), 'تصویر ' . ($i + 1));
}
store_exec("INSERT INTO shop_product_specs (product_id, label_fa, label_en, value_fa, value_en, sort_order) VALUES (?, 'سازگاری', 'Compatibility', 'همه مرورگرها', 'All browsers', 1), (?, 'فریمورک', 'Framework', 'HTML/CSS/JS', 'HTML/CSS/JS', 2)", 'ii', [$ids['tpl'], $ids['tpl']]);
foreach (['tpl', 'plugin'] as $k) {
    $tmp = tempnam(sys_get_temp_dir(), 'z'); file_put_contents($tmp, "PK\x03\x04" . str_repeat('abc', 400000));
    $f = store_store_uploaded_file(['name' => $k . '-source.zip', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]);
    store_exec('INSERT INTO shop_product_files (product_id, title, stored_name, original_name, extension, size_bytes, sha256) VALUES (?,?,?,?,?,?,?)', 'issssis', [$ids[$k], 'سورس کامل', $f['stored_name'], $f['original_name'], $f['extension'], $f['size_bytes'], $f['sha256']]);
}
foreach (['COURSE-KEY-AAAA-1111', 'COURSE-KEY-BBBB-2222', 'COURSE-KEY-CCCC-3333'] as $c) store_license_add($ids['course'], $c);
foreach (['PLUGIN-KEY-1111', 'PLUGIN-KEY-2222'] as $c) store_license_add($ids['plugin'], $c);
echo json_encode($ids), "\n";
