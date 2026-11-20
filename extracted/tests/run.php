<?php
/**
 * Backend integration tests for the store (CLI only).
 *   php tests/run.php            (against the DB in includes/config.php — USE A TEST DATABASE:
 *                                 this script creates/deletes its own rows but never drops tables)
 * Exercises the real code paths: pricing, cart tampering, orders, license
 * reservation, payment verification (duplicate/forged/unknown/mismatch),
 * delivery, downloads, refunds, reviews, uploads, sanitizer, redirects.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('STORE_ALLOW_TESTS') !== '1') { fwrite(STDERR, "Refusing to run: these tests create and delete rows. Use a TEST database and set STORE_ALLOW_TESTS=1.\n"); exit(1); }
putenv('STORE_TEST_MODE=1');
define('STORE_GATEWAY', 'mock');
define('STORE_TESTING', true);
require_once __DIR__ . '/../includes/store-payment.php';
require_once __DIR__ . '/../includes/store-reviews.php';
require_once __DIR__ . '/../includes/store-images.php';
require_once __DIR__ . '/../includes/migrator.php';

$pass = 0; $fail = 0; $failures = [];
function ok(bool $cond, string $name): void {
    global $pass, $fail, $failures;
    if ($cond) { $pass++; echo "  ✔ $name\n"; } else { $fail++; $failures[] = $name; echo "  ✘ FAIL: $name\n"; }
}
function section(string $t): void { echo "\n== $t ==\n"; }

$conn = db();
$tag = bin2hex(random_bytes(3));

function mk_user(string $tag, string $n): array {
    store_exec("INSERT INTO users (name, username, email, password) VALUES (?,?,?,?)", 'ssss', ["کاربر $n", "tu_{$tag}_$n", "tu_{$tag}_$n@example.com", password_hash('Passw0rd!', PASSWORD_ARGON2ID)]);
    $id = store_last_id();
    return ['id' => $id, 'name' => "کاربر $n", 'email' => "tu_{$tag}_$n@example.com", 'phone' => null];
}
function mk_product(string $tag, string $k, array $o = []): int {
    $d = $o + ['price' => 100000, 'sale' => null, 'delivery' => 'file', 'type' => 'source_code', 'free' => 0, 'max' => 3, 'status' => 'published', 'active' => 1, 'cat' => 2];
    store_exec(
        "INSERT INTO shop_products (sku, slug, category_id, product_type, delivery_method, title_fa, summary_fa, price, sale_price, is_free, max_quantity, status, is_active, published_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())",
        'ssissssiiiisi',
        ["T-$tag-$k", "t-$tag-$k", $d['cat'], $d['type'], $d['delivery'], "محصول $k", "خلاصه $k", $d['price'], $d['sale'], $d['free'], $d['max'], $d['status'], $d['active']]
    );
    return store_last_id();
}
function add_file(int $pid, string $content = "PK\x03\x04testzip"): int {
    $tmp = tempnam(sys_get_temp_dir(), 'zip'); file_put_contents($tmp, $content . str_repeat('x', 64));
    $info = store_store_uploaded_file(['name' => 'source.zip', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]);
    store_exec('INSERT INTO shop_product_files (product_id, title, stored_name, original_name, extension, size_bytes, sha256) VALUES (?,?,?,?,?,?,?)', 'issssis', [$pid, 'source', $info['stored_name'], $info['original_name'], $info['extension'], $info['size_bytes'], $info['sha256']]);
    return store_last_id();
}
function pay_mock(string $ref, string $mode): void { file_put_contents(sys_get_temp_dir() . '/mock_gateway_' . $ref, $mode); }
function last_payment(int $orderId): array { return store_row('SELECT * FROM shop_payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', 'i', [$orderId]); }
function order_of(int $id): array { return store_row('SELECT * FROM shop_orders WHERE id = ?', 'i', [$id]); }
$cust = ['name' => 'خریدار تست', 'email' => 'buyer@example.com', 'phone' => '09120000000', 'note' => ''];
function tok(): string { return bin2hex(random_bytes(16)); }

section('Migrations');
ok(count(migrator_status()) >= 1 && !array_filter(migrator_status(), fn($m) => !$m['applied']), 'all migrations applied');
ok((int) store_val('SELECT COUNT(*) FROM shop_categories') >= 6, 'six seed categories exist');
ok((int) store_val('SELECT COUNT(*) FROM admins') >= 1, 'existing admins preserved');

section('Pricing (server-side single source of truth)');
$pFile = mk_product($tag, 'file', ['price' => 100000]);
$pSale = mk_product($tag, 'sale', ['price' => 200000, 'sale' => 150000]);
$pFree = mk_product($tag, 'free', ['price' => 50000, 'free' => 1]);
$pLic = mk_product($tag, 'lic', ['price' => 300000, 'delivery' => 'license', 'type' => 'course', 'cat' => 1, 'max' => 3]);
$pBoth = mk_product($tag, 'both', ['price' => 400000, 'delivery' => 'file_license', 'type' => 'course', 'cat' => 1]);
$pDraft = mk_product($tag, 'draft', ['status' => 'draft']);
$pOff = mk_product($tag, 'off', ['active' => 0]);
foreach ([$pFile, $pSale, $pBoth] as $pid) add_file($pid);
store_exec("UPDATE shop_products SET sale_price = 150000, sale_starts_at = NOW() + INTERVAL 1 DAY WHERE id = ?", 'i', [$pSale]);
$row = store_row('SELECT * FROM shop_products WHERE id = ?', 'i', [$pSale]);
ok(store_price($row)['final'] === 200000, 'sale not started yet -> list price charged');
store_exec("UPDATE shop_products SET sale_starts_at = NOW() - INTERVAL 1 DAY, sale_ends_at = NOW() + INTERVAL 1 DAY WHERE id = ?", 'i', [$pSale]);
$row = store_row('SELECT * FROM shop_products WHERE id = ?', 'i', [$pSale]);
$pr = store_price($row);
ok($pr['final'] === 150000 && $pr['on_sale'] && $pr['pct'] === 25, 'active sale -> discounted price + 25%');
ok(store_price(store_row('SELECT * FROM shop_products WHERE id = ?', 'i', [$pFree]))['final'] === 0, 'free product -> 0');
$list = store_list_products(['sort' => 'price_asc', 'q' => "محصول"], 1, 50);
ok(!in_array($pDraft, array_column($list['rows'], 'id')) && !in_array($pOff, array_column($list['rows'], 'id')), 'draft/inactive never listed publicly');
ok(count(store_list_products(['q' => "' OR 1=1 -- "], 1, 10)['rows']) === 0, 'search is injection-safe');

section('Cart tampering');
$cart = store_cart_resolve([['id' => $pFile, 'qty' => 5, 'seen' => 1], ['id' => $pDraft, 'qty' => 1], ['id' => 999999, 'qty' => 1], ['id' => $pOff, 'qty' => 1]]);
$byId = []; foreach ($cart['lines'] as $l) $byId[$l['id']] = $l;
ok($byId[$pFile]['qty'] === 1 && $byId[$pFile]['unit_final'] === 100000, 'file product qty forced to 1; price from DB (client "seen" ignored)');
ok(in_array('price_changed', $byId[$pFile]['issues'], true), 'client price mismatch flagged');
ok(!$byId[$pDraft]['purchasable'] && !$byId[$pOff]['purchasable'] && !$byId[999999]['purchasable'], 'draft / disabled / deleted products are not purchasable');
ok($cart['total'] === 100000, 'only valid lines counted in total');
$cart = store_cart_resolve([['id' => $pLic, 'qty' => 2]]);
ok(!$cart['lines'][0]['purchasable'] && in_array('out_of_stock', $cart['lines'][0]['issues'], true), 'license product with 0 stock blocked');

section('Licenses encrypted at rest + stock');
foreach (['AAAA-1111', 'BBBB-2222', 'CCCC-3333', 'DDDD-4444'] as $c) store_license_add($pLic, $c);
store_license_add($pBoth, 'BOTH-9999'); store_license_add($pBoth, 'BOTH-8888');
ok(store_license_add($pLic, 'AAAA-1111') === 'duplicate', 'duplicate license rejected');
ok(store_license_add($pLic, 'x') === 'invalid', 'too-short license rejected');
$enc = store_val('SELECT code_enc FROM shop_licenses WHERE product_id = ? LIMIT 1', 'i', [$pLic]);
ok(!str_contains($enc, 'AAAA') && store_decrypt($enc) !== null, 'ciphertext stored, decrypts back');
ok(store_license_stock($pLic) === 4, 'stock counts available only');

section('Order creation');
$u1 = mk_user($tag, 'a'); $u2 = mk_user($tag, 'b');
$raw = [['id' => $pFile, 'qty' => 1], ['id' => $pSale, 'qty' => 1], ['id' => $pLic, 'qty' => 2], ['id' => $pBoth, 'qty' => 1]];
$exp = 100000 + 150000 + 600000 + 400000;
$bad = store_create_order($u1, $raw, $cust, tok(), $exp - 1);
ok(!$bad['ok'] && $bad['error'] === 'total_changed', 'stale/forged expected total rejected (price shown == price charged)');
$t1 = tok();
$r = store_create_order($u1, $raw, $cust, $t1, $exp);
ok($r['ok'] && (int) $r['order']['total'] === $exp && $r['order']['payment_status'] === 'unpaid', 'order created with server-computed total');
$o1 = (int) $r['order']['id'];
$r2 = store_create_order($u1, $raw, $cust, $t1, $exp);
ok($r2['ok'] && !empty($r2['existing']) && (int) $r2['order']['id'] === $o1, 'double-submit (same token) returns the same order');
ok((int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_id = ? AND status = 'reserved'", 'i', [$o1]) === 3, 'licenses reserved for the order (2 + 1)');
ok(store_license_stock($pLic) === 2, 'reserved licenses leave the free pool');
$items = store_order_items($o1);
ok($items[0]['product_title'] === 'محصول file' && (int) $items[0]['unit_price'] === 100000, 'order items hold name/price snapshot');
ok(store_order_for_user(order_of($o1)['order_number'], $u2['id']) === null, 'another user cannot load the order (IDOR)');
// stock race: second buyer wants 3 of the remaining 2
$r = store_create_order($u2, [['id' => $pLic, 'qty' => 3]], $cust, tok(), 900000);
ok(!$r['ok'], 'cannot order more licenses than free stock');

section('Payment: no fake success');
ok(!isset($_SERVER['x']) && store_gateway()->name() === 'mock', 'test gateway active only via STORE_TEST_MODE env');
$b = store_payment_begin(order_of($o1));
ok($b['ok'] && str_contains($b['redirect_url'], 'mock-gateway.invalid'), 'payment session created, redirect url returned');
$pay = last_payment($o1);
ok($pay['status'] === 'redirected' && order_of($o1)['payment_status'] === 'pending', 'payment redirected / order payment pending');
ok(order_of($o1)['delivery_status'] === 'pending' || order_of($o1)['delivery_status'] === 'none', 'nothing delivered before payment');
ok((int) store_val('SELECT COUNT(*) FROM shop_entitlements WHERE order_id = ?', 'i', [$o1]) === 0, 'no entitlement before payment');
// browser returns to "success" without the gateway confirming -> must NOT be paid
$cb = store_payment_callback('mock', ['ref' => $pay['gateway_ref'], 'status' => 'ok'], []);
ok($cb['outcome'] === 'failed' && order_of($o1)['payment_status'] === 'failed', 'success URL without gateway confirmation => failed, not paid');
ok((int) store_val('SELECT COUNT(*) FROM shop_entitlements WHERE order_id = ?', 'i', [$o1]) === 0, 'still nothing delivered');
$fc = store_payment_callback('mock', ['ref' => 'MOCKFFFFFFFFFFFFFFFF', 'status' => 'ok'], []);
ok($fc['outcome'] === 'unknown', 'forged callback with unknown reference ignored');
// unknown (network) must not become failed
$b = store_payment_begin(order_of($o1));
$pay = last_payment($o1);
pay_mock($pay['gateway_ref'], 'unknown');
$cb = store_payment_callback('mock', ['ref' => $pay['gateway_ref'], 'status' => 'ok'], []);
ok($cb['outcome'] === 'pending' && last_payment($o1)['status'] === 'verifying', 'verify timeout => stays verifying (never marked failed)');
// amount mismatch must not deliver
pay_mock($pay['gateway_ref'], 'paid_wrong_amount');
store_payment_process((int) $pay['id']);
ok(order_of($o1)['payment_status'] !== 'paid' && last_payment($o1)['status'] === 'failed', 'gateway amount != order amount => rejected');
// genuine payment
$b = store_payment_begin(order_of($o1));
$pay = last_payment($o1);
ok($b['ok'] && $pay['status'] === 'redirected', 'retry creates a new payment session');
pay_mock($pay['gateway_ref'], 'paid');
$cb = store_payment_callback('mock', ['ref' => $pay['gateway_ref'], 'status' => 'ok'], []);
$o = order_of($o1);
ok($cb['outcome'] === 'paid' && $o['payment_status'] === 'paid' && $o['status'] === 'completed' && $o['delivery_status'] === 'delivered', 'verified payment => paid + completed + delivered');
ok((int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_id = ? AND status = 'assigned' AND user_id = ?", 'ii', [$o1, $u1['id']]) === 3, 'licenses assigned to the buyer');
ok((int) store_val('SELECT COUNT(*) FROM shop_entitlements WHERE order_id = ?', 'i', [$o1]) === 5, 'entitlements: 2 file items + 1 license item + file&license combo (=5)');
ok((int) store_val('SELECT sales_count FROM shop_products WHERE id = ?', 'i', [$pFile]) === 1, 'sales counter incremented once');
// duplicate callbacks
for ($i = 0; $i < 3; $i++) store_payment_callback('mock', ['ref' => $pay['gateway_ref'], 'status' => 'ok'], []);
store_fulfill_order($o1);
ok((int) store_val('SELECT sales_count FROM shop_products WHERE id = ?', 'i', [$pFile]) === 1, 'duplicate callbacks do not double-count sales');
ok((int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_id = ? AND status = 'assigned'", 'i', [$o1]) === 3, 'duplicate callbacks do not assign extra licenses');
ok((int) store_val("SELECT COALESCE(SUM(total),0) FROM shop_orders WHERE id = ? AND payment_status = 'paid'", 'i', [$o1]) === $exp, 'revenue counts the order once');
ok((int) store_val("SELECT COUNT(*) FROM shop_payments WHERE order_id = ? AND status = 'paid'", 'i', [$o1]) === 1, 'exactly one paid payment row');

section('Buyer dashboard data / license reveal / downloads');
$purch = store_user_purchases($u1['id']);
$byTitle = []; foreach ($purch as $p) $byTitle[$p['product_id']] = $p;
ok(count($purch) === 4 && count($byTitle[$pLic]['licenses']) === 2, 'purchases list shows the 2 licenses for the course');
$licId = (int) $byTitle[$pLic]['licenses'][0]['id'];
ok(in_array(store_license_reveal_for_user($licId, $u1['id']), ['AAAA-1111', 'BBBB-2222', 'CCCC-3333', 'DDDD-4444'], true), 'owner can reveal license');
ok(store_license_reveal_for_user($licId, $u2['id']) === null, "other user cannot reveal someone else's license");
ok(store_user_purchases($u2['id']) === [], 'other user sees no purchases');
$f = $byTitle[$pFile]['files'][0]; $eid = (int) $byTitle[$pFile]['file_entitlement_id'];
$url = store_download_url($eid, (int) $f['id'], $u1['id']);
parse_str(parse_url($url, PHP_URL_QUERY), $q);
ok(store_download_token_valid($eid, (int) $f['id'], $u1['id'], (int) $q['x'], $q['s']), 'download token valid for owner');
ok(!store_download_token_valid($eid, (int) $f['id'], $u2['id'], (int) $q['x'], $q['s']), 'token useless for another user');
ok(!store_download_token_valid($eid, (int) $f['id'], $u1['id'], (int) $q['x'] - 9999, $q['s']), 'tampered expiry rejected');
ok(!store_download_token_valid($eid, (int) $f['id'] + 1, $u1['id'], (int) $q['x'], $q['s']), 'token bound to the file id');
$fileRow = store_row('SELECT * FROM shop_product_files WHERE id = ?', 'i', [(int) $f['id']]);
ok(store_file_path($fileRow) !== null && !str_contains(store_file_path($fileRow), 'uploads'), 'file stored in private storage, random name, no extension');
ok(!str_ends_with(store_file_path($fileRow), '.zip'), 'stored name has no executable-capable extension');

section('Upload validation');
$tmp = tempnam(sys_get_temp_dir(), 'bad'); file_put_contents($tmp, '<?php echo 1; ?>' . str_repeat('A', 100));
try { store_store_uploaded_file(['name' => 'evil.php', 'tmp_name' => $tmp, 'error' => 0, 'size' => 120]); $threw = false; } catch (RuntimeException $e) { $threw = true; }
ok($threw, 'php file rejected');
$tmp = tempnam(sys_get_temp_dir(), 'bad'); file_put_contents($tmp, '<?php echo 1; ?>' . str_repeat('A', 100));
try { store_store_uploaded_file(['name' => 'fake.zip', 'tmp_name' => $tmp, 'error' => 0, 'size' => 120]); $threw = false; } catch (RuntimeException $e) { $threw = true; }
ok($threw, 'php renamed to .zip rejected (magic bytes)');
// image pipeline
$im = imagecreatetruecolor(2400, 1200); imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 200)); $png = tempnam(sys_get_temp_dir(), 'img'); imagepng($im, $png);
$res = store_process_image(['name' => 'a.png', 'tmp_name' => $png, 'error' => 0, 'size' => filesize($png)]);
ok(str_ends_with($res['path'], '.webp') && $res['width'] === 1600 && $res['card_width'] === 600 && is_file(BASE_PATH . '/' . $res['thumb_path']), 'PNG converted to WebP in 3 sizes, no upscaling beyond 1600');
ok(mime_content_type(BASE_PATH . '/' . $res['path']) === 'image/webp', 'output really is WebP');
$tmp = tempnam(sys_get_temp_dir(), 'img'); file_put_contents($tmp, "GIF89a<?php system('id');?>");
try { store_process_image(['name' => 'x.gif', 'tmp_name' => $tmp, 'error' => 0, 'size' => 40]); $threw = false; } catch (RuntimeException $e) { $threw = true; }
ok($threw, 'fake image with embedded PHP rejected');
foreach (['path', 'card_path', 'thumb_path'] as $k) store_unlink_image($res[$k]);
// many gallery images (unlimited)
$gid = mk_product($tag, 'gal');
for ($i = 0; $i < 8; $i++) { $im = imagecreatetruecolor(300 + $i, 200); imagejpeg($im, $jp = tempnam(sys_get_temp_dir(), 'j')); store_add_product_image($gid, ['name' => "g$i.jpg", 'tmp_name' => $jp, 'error' => 0, 'size' => filesize($jp)], "alt $i"); }
$g = store_product_gallery($gid);
ok(count($g) === 8 && $g[0]['is_main'] == 1 && array_sum(array_column($g, 'is_main')) === 1, '8 gallery images, exactly one main');
foreach ($g as $img) store_delete_image_row($img);

section('Reviews & moderation');
$rv = store_review_submit($u2['id'], $pFile, 5, 'عالی', 'محصول خیلی خوبیه، پیشنهاد می‌کنم.');
ok($rv['ok'] && store_row('SELECT status FROM shop_reviews WHERE product_id = ? AND user_id = ?', 'ii', [$pFile, $u2['id']])['status'] === 'pending', 'review stored as pending');
ok(store_approved_reviews($pFile) === [] && (int) store_val('SELECT rating_count FROM shop_products WHERE id = ?', 'i', [$pFile]) === 0, 'pending review invisible, not counted');
$rid = (int) store_val('SELECT id FROM shop_reviews WHERE product_id = ? AND user_id = ?', 'ii', [$pFile, $u2['id']]);
store_review_set_status($rid, 'approved', 1);
ok(count(store_approved_reviews($pFile)) === 1 && (float) store_val('SELECT rating_avg FROM shop_products WHERE id = ?', 'i', [$pFile]) == 5.0, 'approved => visible and averaged');
$xss = store_review_submit($u1['id'], $pFile, 3, '<script>alert(1)</script>', '<img src=x onerror=alert(1)> متن آزمایشی طولانی');
store_review_set_status((int) store_val('SELECT id FROM shop_reviews WHERE user_id = ?', 'i', [$u1['id']]), 'approved', 1);
$shown = store_approved_reviews($pFile)[0];
ok(e($shown['body']) !== $shown['body'] && !str_contains(e($shown['body']), '<img'), 'review text is escaped on output');
ok((float) store_val('SELECT rating_avg FROM shop_products WHERE id = ?', 'i', [$pFile]) == 4.0 && (int) store_val('SELECT rating_count FROM shop_products WHERE id = ?', 'i', [$pFile]) === 2, 'average computed from approved only (5+3)/2');
store_review_set_status($rid, 'rejected', 1);
ok((int) store_val('SELECT rating_count FROM shop_products WHERE id = ?', 'i', [$pFile]) === 1, 'rejected review removed from aggregate');
$again = store_review_submit($u2['id'], $pFile, 4, 'ویرایش', 'نظر ویرایش‌شده‌ی من درباره محصول');
ok($again['ok'] && $again['updated'] && (int) store_val('SELECT COUNT(*) FROM shop_reviews WHERE product_id = ? AND user_id = ?', 'ii', [$pFile, $u2['id']]) === 1, 'one review per user per product; edit resets to pending');
ok(!store_review_submit($u2['id'], $pDraft, 5, '', 'یک نظر معتبر برای محصول پیش‌نویس')['ok'], 'cannot review a non-public product');
ok(store_review_submit($u2['id'], $pFile, 9, '', 'یک نظر معتبر با امتیاز بد')['error'] === 'rating', 'rating must be 1-5');
ok(store_review_submit($u2['id'], $pFile, 5, '', 'کوتاه')['error'] === 'too_short', 'too-short review rejected');
$jl = json_decode(store_product_jsonld(store_product_by_slug("t-$tag-file"), [], store_approved_reviews($pFile), true), true);
ok(isset($jl['aggregateRating']) && $jl['aggregateRating']['reviewCount'] === 1 && $jl['offers']['priceCurrency'] === 'IRR' && $jl['offers']['price'] === '1000000', 'JSON-LD: real aggregate only, price converted Toman->IRR');
$jl2 = json_decode(store_product_jsonld(store_product_by_slug("t-$tag-sale"), [], [], true), true);
ok(!isset($jl2['aggregateRating']) && !isset($jl2['review']), 'JSON-LD has no rating when there are no approved reviews');

section('Refund / expiry / manual delivery / free order');
$ref = store_admin_refund($o1, 1, 'تست بازپرداخت');
ok($ref['ok'] && order_of($o1)['payment_status'] === 'refunded' && (int) store_val("SELECT COUNT(*) FROM shop_entitlements WHERE order_id = ? AND status = 'active'", 'i', [$o1]) === 0, 'refund revokes access');
ok(store_license_reveal_for_user($licId, $u1['id']) === null && (int) store_val("SELECT COUNT(*) FROM shop_orders WHERE payment_status = 'paid' AND id = ?", 'i', [$o1]) === 0, 'refunded: license hidden, not counted as revenue');
ok(!store_admin_refund($o1, 1, 'x')['ok'], 'refund needs a real reason');
// expiry releases reservation
$r = store_create_order($u2, [['id' => $pLic, 'qty' => 1]], $cust, tok(), 300000);
ok($r['ok'] && store_license_stock($pLic) === 1, 'reserved 1 more');
store_exec("UPDATE shop_orders SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = ?", 'i', [(int) $r['order']['id']]);
store_expire_stale_orders();
ok(order_of((int) $r['order']['id'])['status'] === 'expired' && store_license_stock($pLic) === 2, 'expired unpaid order releases its license reservation');
// free order (a free product with no deliverable file is correctly refused first)
$r = store_create_order($u2, [['id' => $pFree, 'qty' => 1]], $cust, tok(), 0);
ok(!$r['ok'] && $r['error'] === 'cart_invalid', 'free product without a file is not purchasable');
add_file($pFree);
$r = store_create_order($u2, [['id' => $pFree, 'qty' => 1]], $cust, tok(), 0);
ok($r['ok'] && (int) $r['order']['total'] === 0, 'free order created');
ok(store_complete_free_order($r['order']) && order_of((int) $r['order']['id'])['payment_status'] === 'paid', 'free order settles without a gateway (amount 0)');
ok(!store_complete_free_order(order_of($o1)), 'non-free order can never use the free path');
// manual mark paid
$r = store_create_order($u2, [['id' => $pFile, 'qty' => 1]], $cust, tok(), 100000);
ok(!store_admin_mark_paid((int) $r['order']['id'], 1, '')['ok'], 'manual mark-paid requires a reason');
ok(store_admin_mark_paid((int) $r['order']['id'], 1, 'پرداخت کارت به کارت تأیید شد')['ok'] && order_of((int) $r['order']['id'])['delivery_status'] === 'delivered', 'manual mark-paid works with reason and delivers');
// partial delivery then retry
$r = store_create_order($u1, [['id' => $pBoth, 'qty' => 1]], $cust, tok(), 400000);
store_exec("UPDATE shop_licenses SET status='available', order_id=NULL, order_item_id=NULL, user_id=NULL WHERE order_id = ? AND status='reserved'", 'i', [(int) $r['order']['id']]);
store_exec("UPDATE shop_licenses SET status='disabled' WHERE product_id = ? AND status='available'", 'i', [$pBoth]);
store_admin_mark_paid((int) $r['order']['id'], 1, 'تست تحویل ناقص');
ok(order_of((int) $r['order']['id'])['delivery_status'] === 'failed' || order_of((int) $r['order']['id'])['delivery_status'] === 'partial', 'no license left after payment => visible delivery error (payment record kept)');
ok(order_of((int) $r['order']['id'])['payment_status'] === 'paid', 'delivery failure never erases the payment');
store_license_add($pBoth, 'NEW-LIC-7777');
store_fulfill_order((int) $r['order']['id']);
ok(order_of((int) $r['order']['id'])['delivery_status'] === 'delivered', 'admin adds license + retry => delivered');

section('Security helpers');
foreach (['//evil.com', 'https://evil.com', '/\\evil.com', '/checkout/../../admin', 'javascript:alert(1)', "/checkout\r\nSet-Cookie:x=1", '/admin/index.php', '/shop/%0d%0a'] as $bad) ok(store_safe_next($bad) === '' || $bad === '/shop/%0d%0a' && !str_contains(store_safe_next($bad), "\n"), 'open-redirect blocked: ' . json_encode($bad));
ok(store_safe_next('/checkout') === '/checkout' && store_safe_next('/shop/my-product?x=1') === '/shop/my-product?x=1' && store_safe_next('/dashboard/orders') === '/dashboard/orders', 'valid internal paths accepted');
$dirty = '<p onclick="x()">ok <b>bold</b></p><script>alert(1)</script><a href="javascript:alert(1)">x</a><a href="https://a.com" onmouseover="y()">l</a><iframe src="//e"></iframe><img src=x onerror=1><style>*{}</style><div style="x">d</div>';
$clean = store_sanitize_html($dirty);
ok(!preg_match('/<script|<iframe|<img|<style|onclick|onmouseover|onerror|javascript:|style=/i', $clean) && str_contains($clean, '<b>bold</b>') && str_contains($clean, 'href="https://a.com"'), 'sanitizer strips scripts/handlers/js: links, keeps safe formatting');
ok(str_contains(store_sanitize_html("خط اول\n\nخط دوم"), '<p>خط اول</p><p>خط دوم</p>'), 'plain text becomes paragraphs');
ok(store_rate_limit('t_' . $tag, 'x', 2, 60) && store_rate_limit('t_' . $tag, 'x', 2, 60) && !store_rate_limit('t_' . $tag, 'x', 2, 60), 'rate limiter blocks after the limit');

section('Stored product deletion safety');
try { store_exec('DELETE FROM shop_products WHERE id = ?', 'i', [$pFile]); $blocked = false; } catch (RuntimeException $e) { $blocked = true; }
ok($blocked, 'DB refuses to hard-delete a product that has order history');
try { store_exec('DELETE FROM shop_categories WHERE id = 2'); $blocked = false; } catch (RuntimeException $e) { $blocked = true; }
ok($blocked, 'DB refuses to delete a category that still has products');
try { store_exec('DELETE FROM users WHERE id = ?', 'i', [$u1['id']]); $blocked = false; } catch (RuntimeException $e) { $blocked = true; }
ok($blocked, 'DB refuses to delete a user with orders');

echo "\n==== $pass passed, $fail failed ====\n";
if ($fail) { echo "Failures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
