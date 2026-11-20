<?php
/**
 * /download/{entitlementId}/{fileId}?x=<expiry>&s=<signature>
 * Gatekeeper for every deliverable file. Requires: logged-in OWNER, valid
 * short-lived HMAC token bound to (entitlement,file,user,expiry), active
 * entitlement on a PAID (not refunded) order, active file of that product,
 * under the per-file download limit. The file lives in private storage and
 * is only ever streamed from here — it has no public URL.
 */
require_once __DIR__ . '/includes/store-layout.php';
require_once __DIR__ . '/includes/store-delivery.php';

store_no_cache();
$eid = (int) ($_GET['e'] ?? 0);
$fid = (int) ($_GET['f'] ?? 0);
$user = store_require_user('/dashboard/purchases');
$uid = (int) $user['id'];

if (!store_download_token_valid($eid, $fid, $uid, (int) ($_GET['x'] ?? 0), (string) ($_GET['s'] ?? ''))) {
    store_message_page(403, 'لینک دانلود منقضی شده', 'لینک‌های دانلود موقتی‌اند. صفحه‌ی خریدها را باز کن و دوباره روی دکمه‌ی دانلود بزن.', ['خریدها و دانلودها' => '/dashboard/purchases']);
}
if (!store_rate_limit('download', 'u' . $uid, 30, 600)) {
    store_message_page(429, 'تعداد دانلودها زیاد است', 'چند دقیقه بعد دوباره تلاش کن.', ['خریدها' => '/dashboard/purchases']);
}
$row = store_row(
    "SELECT f.*, e.id AS ent_id FROM shop_entitlements e
     JOIN shop_orders o ON o.id = e.order_id
     JOIN shop_product_files f ON f.product_id = e.product_id
     WHERE e.id = ? AND e.user_id = ? AND e.kind = 'file' AND e.status = 'active' AND o.payment_status = 'paid' AND f.id = ? AND f.is_active = 1",
    'iii',
    [$eid, $uid, $fid]
);
if (!$row) {
    store_message_page(404, 'فایل در دسترس نیست', 'این فایل وجود ندارد یا دسترسی تو به آن فعال نیست.', ['خریدها و دانلودها' => '/dashboard/purchases']);
}
$limit = store_setting_int('shop_download_max_per_file', 10);
if ($limit > 0) {
    $used = (int) store_val('SELECT COUNT(*) FROM shop_download_log WHERE entitlement_id = ? AND file_id = ?', 'ii', [$eid, $fid]);
    // a resumed/partial transfer (Range request) must not burn another download
    if ($used >= $limit && empty($_SERVER['HTTP_RANGE'])) {
        store_message_page(403, 'سقف دانلود تمام شد', 'تعداد دفعات مجاز دانلود این فایل تمام شده. برای افزایش آن با پشتیبانی تماس بگیر.', ['خریدها' => '/dashboard/purchases']);
    }
}
$path = store_file_path($row);
if (!$path) {
    store_log('download: missing file on disk for file #' . $fid);
    store_message_page(500, 'فایل روی سرور پیدا نشد', 'مشکل از سمت ماست؛ به پشتیبانی خبر بده تا سریع درستش کنیم.', ['پشتیبانی' => '/contact']);
}
if (empty($_SERVER['HTTP_RANGE']) || str_starts_with((string) $_SERVER['HTTP_RANGE'], 'bytes=0-')) {
    store_exec('INSERT INTO shop_download_log (entitlement_id, file_id, user_id, ip_address) VALUES (?,?,?,?)', 'iiis', [$eid, $fid, $uid, client_ip()]);
}
session_write_close();        // don't hold the session lock during a long transfer
store_stream_file($path, store_download_filename((string) $row['original_name']));
