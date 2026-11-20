<?php
/**
 * store-delivery.php — after a VERIFIED payment: entitlements, license
 * assignment, order completion; plus the secure download endpoint's
 * helpers (signed short-lived tokens, owner checks, streaming).
 * store_fulfill_order() is idempotent: running it twice (duplicate
 * callback, admin retry, cron) never double-assigns or double-counts.
 */
require_once __DIR__ . '/store-orders.php';

// ---- Licenses (encrypted at rest) ----------------------------------------------------
function store_license_clean(string $code): string
{
    return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $code));
}

/** @return 'ok'|'duplicate'|'invalid' */
function store_license_add(int $productId, string $code, string $note = ''): string
{
    $code = store_license_clean($code);
    if (mb_strlen($code) < 3 || mb_strlen($code) > 500) {
        return 'invalid';
    }
    try {
        store_exec(
            "INSERT INTO shop_licenses (product_id, code_enc, code_hash, code_hint, note) VALUES (?,?,?,?,?)",
            'issss',
            [$productId, store_encrypt($code), store_hmac($productId . '|' . $code), mb_substr($code, -4), mb_substr($note, 0, 255)]
        );
        return 'ok';
    } catch (RuntimeException $ex) {
        if ($ex->getCode() === 1062) {
            return 'duplicate';
        }
        throw $ex;
    }
}

function store_license_plain(array $row): ?string
{
    return store_decrypt((string) $row['code_enc']);
}

/** Owner-only reveal: the license must be assigned to THIS user and its order paid. */
function store_license_reveal_for_user(int $licenseId, int $userId): ?string
{
    $row = store_row(
        "SELECT l.code_enc FROM shop_licenses l JOIN shop_orders o ON o.id = l.order_id
         WHERE l.id = ? AND l.user_id = ? AND l.status = 'assigned' AND o.payment_status = 'paid'",
        'ii',
        [$licenseId, $userId]
    );
    return $row ? store_license_plain($row) : null;
}

// ---- Fulfillment -------------------------------------------------------------------------
/**
 * @return array{ok:bool,reason?:string,delivery_status?:string}
 */
function store_fulfill_order(int $orderId): array
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $order = store_row('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$order || $order['payment_status'] !== 'paid') {
            $conn->rollback();
            return ['ok' => false, 'reason' => 'not_paid'];
        }
        $userId = (int) $order['user_id'];
        $items = store_rows('SELECT * FROM shop_order_items WHERE order_id = ? ORDER BY id', 'i', [$orderId]);

        $allDone = true;
        $anyDone = false;
        $anyShort = false;

        foreach ($items as $it) {
            $itemId = (int) $it['id'];
            $method = $it['delivery_method'];
            $qty = (int) $it['quantity'];
            $needFile = in_array($method, ['file', 'file_license'], true);
            $needLic = in_array($method, ['license', 'file_license'], true);
            $first = (int) store_val('SELECT COUNT(*) FROM shop_entitlements WHERE order_item_id = ?', 'i', [$itemId]) === 0;
            $complete = true;

            if ($needFile) {
                store_exec('INSERT IGNORE INTO shop_entitlements (user_id, order_id, order_item_id, product_id, kind) VALUES (?,?,?,?,?)', 'iiiis', [$userId, $orderId, $itemId, (int) $it['product_id'], 'file']);
            }
            if ($needLic) {
                $have = (int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_item_id = ? AND status = 'assigned'", 'i', [$itemId]);
                $need = $qty - $have;
                if ($need > 0) {
                    // 1) the ones reserved for this exact order item at checkout
                    $res = store_rows("SELECT id FROM shop_licenses WHERE order_item_id = ? AND status = 'reserved' ORDER BY id LIMIT ? FOR UPDATE", 'ii', [$itemId, $need]);
                    foreach ($res as $l) {
                        store_exec("UPDATE shop_licenses SET status = 'assigned', assigned_at = NOW(), user_id = ?, order_id = ? WHERE id = ? AND status = 'reserved'", 'iii', [$userId, $orderId, (int) $l['id']]);
                        $need--;
                    }
                    // 2) reservation expired meanwhile -> top up from the free pool
                    if ($need > 0) {
                        $pool = store_rows("SELECT id FROM shop_licenses WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT ? FOR UPDATE", 'ii', [(int) $it['product_id'], $need]);
                        foreach ($pool as $l) {
                            store_exec("UPDATE shop_licenses SET status = 'assigned', assigned_at = NOW(), user_id = ?, order_id = ?, order_item_id = ? WHERE id = ? AND status = 'available'", 'iiii', [$userId, $orderId, $itemId, (int) $l['id']]);
                            $need--;
                        }
                    }
                }
                $have = (int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_item_id = ? AND status = 'assigned'", 'i', [$itemId]);
                if ($have > 0) {
                    store_exec('INSERT IGNORE INTO shop_entitlements (user_id, order_id, order_item_id, product_id, kind) VALUES (?,?,?,?,?)', 'iiiis', [$userId, $orderId, $itemId, (int) $it['product_id'], 'license']);
                }
                if ($have < $qty) {
                    $complete = false;
                    $anyShort = true;
                }
            }
            if ($method === 'manual') {
                store_exec('INSERT IGNORE INTO shop_entitlements (user_id, order_id, order_item_id, product_id, kind) VALUES (?,?,?,?,?)', 'iiiis', [$userId, $orderId, $itemId, (int) $it['product_id'], 'manual']);
                $complete = $it['delivered_at'] !== null;
            }

            if ($first) {
                store_exec('UPDATE shop_products SET sales_count = sales_count + ? WHERE id = ?', 'ii', [$qty, (int) $it['product_id']]);
            }
            if ($complete) {
                store_exec('UPDATE shop_order_items SET delivered_at = COALESCE(delivered_at, NOW()) WHERE id = ?', 'i', [$itemId]);
                $anyDone = true;
            } else {
                $allDone = false;
            }
        }

        if ($allDone) {
            $delivery = 'delivered';
            $status = 'completed';
        } elseif ($anyShort) {
            $delivery = $anyDone ? 'partial' : 'failed';
            $status = 'processing';
        } else {
            $delivery = 'pending'; // waiting for a manual delivery
            $status = 'processing';
        }
        $changed = $order['delivery_status'] !== $delivery;
        store_exec('UPDATE shop_orders SET delivery_status = ?, status = ? WHERE id = ?', 'ssi', [$delivery, $status, $orderId]);
        if ($changed) {
            $msg = ['delivered' => 'همه‌ی اقلام تحویل داده شد.', 'partial' => 'تحویل ناقص: لایسنس کافی موجود نبود. لایسنس اضافه کن و «تلاش مجدد تحویل» بزن.', 'failed' => 'خطای تحویل: لایسنس کافی موجود نبود.', 'pending' => 'در انتظار تحویل دستی.'][$delivery];
            store_order_event($orderId, 'delivery_' . $delivery, $msg);
        }
        $conn->commit();
        return ['ok' => true, 'delivery_status' => $delivery];
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('fulfill failed for order ' . $orderId, $ex);
        try {
            store_exec("UPDATE shop_orders SET delivery_status = 'failed' WHERE id = ? AND payment_status = 'paid'", 'i', [$orderId]);
            store_order_event($orderId, 'delivery_error', 'خطای داخلی هنگام تحویل؛ دوباره تلاش کن.');
        } catch (Throwable $inner) {
        }
        return ['ok' => false, 'reason' => 'exception'];
    }
}

/** Manual-delivery items: admin writes a note/instructions and marks the item delivered. */
function store_admin_deliver_item(int $itemId, string $note): bool
{
    $it = store_row('SELECT order_id FROM shop_order_items WHERE id = ? AND delivery_method = ?', 'is', [$itemId, 'manual']);
    if (!$it) {
        return false;
    }
    store_exec('UPDATE shop_order_items SET delivery_note = ?, delivered_at = COALESCE(delivered_at, NOW()) WHERE id = ?', 'si', [mb_substr($note, 0, 4000), $itemId]);
    store_fulfill_order((int) $it['order_id']);
    return true;
}

// ---- What a buyer owns (dashboard) -------------------------------------------------------------
/** @return array<int,array> one entry per purchased order item with its files and licenses */
function store_user_purchases(int $userId, ?string $orderNumber = null): array
{
    $sql = "SELECT oi.id AS item_id, oi.order_id, oi.product_id, oi.product_title, oi.delivery_method, oi.quantity, oi.delivery_note, oi.delivered_at,
                   o.order_number, o.paid_at, p.slug AS product_slug,
                   (SELECT i.thumb_path FROM shop_product_images i WHERE i.product_id = oi.product_id AND i.is_main = 1 LIMIT 1) AS thumb,
                   (SELECT e.id FROM shop_entitlements e WHERE e.order_item_id = oi.id AND e.kind = 'file' AND e.status = 'active' LIMIT 1) AS file_entitlement_id,
                   (SELECT COUNT(*) FROM shop_entitlements e2 WHERE e2.order_item_id = oi.id AND e2.status = 'active') AS active_entitlements
            FROM shop_order_items oi
            JOIN shop_orders o ON o.id = oi.order_id
            JOIN shop_products p ON p.id = oi.product_id
            WHERE o.user_id = ? AND o.payment_status = 'paid'" . ($orderNumber ? ' AND o.order_number = ?' : '') . '
            ORDER BY o.id DESC, oi.id';
    $items = $orderNumber ? store_rows($sql, 'is', [$userId, $orderNumber]) : store_rows($sql, 'i', [$userId]);
    if (!$items) {
        return [];
    }

    $productIds = array_values(array_unique(array_map(fn($i) => (int) $i['product_id'], $items)));
    $ph = implode(',', array_fill(0, count($productIds), '?'));
    $files = store_rows("SELECT id, product_id, title, file_kind, description, extension, size_bytes FROM shop_product_files WHERE is_active = 1 AND product_id IN ($ph) ORDER BY sort_order, id", str_repeat('i', count($productIds)), $productIds);
    $filesByProduct = [];
    foreach ($files as $f) {
        $filesByProduct[(int) $f['product_id']][] = $f;
    }

    $itemIds = array_map(fn($i) => (int) $i['item_id'], $items);
    $ph2 = implode(',', array_fill(0, count($itemIds), '?'));
    $lics = store_rows("SELECT id, order_item_id, code_hint, assigned_at FROM shop_licenses WHERE status = 'assigned' AND user_id = ? AND order_item_id IN ($ph2) ORDER BY id", 'i' . str_repeat('i', count($itemIds)), array_merge([$userId], $itemIds));
    $licByItem = [];
    foreach ($lics as $l) {
        $licByItem[(int) $l['order_item_id']][] = $l;
    }

    foreach ($items as &$it) {
        $it['files'] = $it['file_entitlement_id'] ? ($filesByProduct[(int) $it['product_id']] ?? []) : [];
        $it['licenses'] = $licByItem[(int) $it['item_id']] ?? [];
    }
    unset($it);
    return $items;
}

// ---- Secure downloads ---------------------------------------------------------------------------
function store_download_url(int $entitlementId, int $fileId, int $userId): string
{
    $exp = time() + max(1, store_setting_int('shop_download_expiry_minutes', 15)) * 60;
    $sig = store_hmac('dl|' . $entitlementId . '|' . $fileId . '|' . $userId . '|' . $exp);
    return '/download/' . $entitlementId . '/' . $fileId . '?x=' . $exp . '&s=' . $sig;
}

function store_download_token_valid(int $entitlementId, int $fileId, int $userId, int $exp, string $sig): bool
{
    return $exp >= time() && preg_match('/^[a-f0-9]{64}$/', $sig) && hash_equals(store_hmac('dl|' . $entitlementId . '|' . $fileId . '|' . $userId . '|' . $exp), $sig);
}

function store_file_path(array $file): ?string
{
    if (!preg_match('/^[a-f0-9]{40}$/', (string) $file['stored_name'])) {
        return null;
    }
    $path = store_private_dir('products') . '/' . $file['stored_name'];
    return (is_file($path) && is_readable($path)) ? $path : null;
}

/**
 * Validates + stores an uploaded deliverable (ZIP/RAR/7Z/PDF/TXT). The
 * file is NEVER extracted and keeps no extension on disk.
 * @return array{stored_name:string,original_name:string,extension:string,size_bytes:int,sha256:string}
 */
function store_store_uploaded_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        require_once __DIR__ . '/store-images.php';
        throw new RuntimeException(store_upload_error_text((int) ($file['error'] ?? 4)));
    }
    if (($file['size'] ?? 0) > STORE_MAX_FILE_MB * 1024 * 1024) {
        throw new RuntimeException('حجم فایل بیشتر از ' . STORE_MAX_FILE_MB . ' مگابایته (STORE_MAX_FILE_MB).');
    }
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp) && !(defined('STORE_TESTING') && STORE_TESTING && is_file($tmp))) {
        throw new RuntimeException('فایل آپلودشده معتبر نیست.');
    }
    $orig = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', basename((string) $file['name']));
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['zip', 'rar', '7z', 'pdf', 'txt'], true)) {
        throw new RuntimeException('فقط فایل‌های ZIP، RAR، 7Z، PDF و TXT مجازه.');
    }
    // Real content check (magic bytes), not just the extension.
    $fh = fopen($tmp, 'rb');
    $head = fread($fh, 8);
    fclose($fh);
    $ok = match ($ext) {
        'zip' => str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06"),
        'rar' => str_starts_with($head, 'Rar!'),
        '7z' => str_starts_with($head, "7z\xBC\xAF\x27\x1C"),
        'pdf' => str_starts_with($head, '%PDF'),
        'txt' => !preg_match('/[\x00-\x08\x0E-\x1F]/', (string) file_get_contents($tmp, false, null, 0, 4096)),
    };
    if (!$ok) {
        throw new RuntimeException('محتوای فایل با پسوند ' . $ext . ' هم‌خوانی نداره (فایل خراب یا جعلی).');
    }
    $stored = bin2hex(random_bytes(20));
    $dest = store_private_dir('products') . '/' . $stored;
    $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : rename($tmp, $dest);
    if (!$moved) {
        throw new RuntimeException('ذخیره‌ی فایل روی سرور ممکن نشد (دسترسی نوشتن storage/ را بررسی کن).');
    }
    @chmod($dest, 0640);
    return ['stored_name' => $stored, 'original_name' => $orig, 'extension' => $ext, 'size_bytes' => (int) filesize($dest), 'sha256' => hash_file('sha256', $dest)];
}

function store_download_filename(string $name): string
{
    $name = preg_replace('/[\x00-\x1F"\\\\\/]+/', '_', $name);
    return $name !== '' ? $name : 'download';
}

/** Streams a file with Range support. Never buffers the whole file in memory. */
function store_stream_file(string $path, string $downloadName): never
{
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    $status = 200;
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') {
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $end;
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $status = 206;
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    @set_time_limit(0);
    http_response_code($status);
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Accept-Ranges: bytes');
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $downloadName);
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . ($end - $start + 1));
    if ($status === 206) {
        header("Content-Range: bytes $start-$end/$size");
    }
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        // headers are already sent at this point; log loudly so the operator can fix permissions
        store_log('download: cannot open ' . basename($path) . ' for reading (check storage/ permissions)');
        exit;
    }
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, min(1048576, $left));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}
