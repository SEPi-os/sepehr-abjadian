<?php
/**
 * store-core.php — foundations every store module builds on:
 * throwing prepared-statement helpers, settings, money, i18n helpers,
 * crypto, rate limiting, safe redirects and the audit log.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/auth.php';      // client_ip(), current_admin()
require_once __DIR__ . '/store-config.php';

// ---- Prepared-statement helpers (throw on failure so transactions can roll back)
function store_q(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $conn = db();
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('SQL prepare failed: ' . $conn->error, $conn->errno);
    }
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $no = $stmt->errno;
        $stmt->close();
        throw new RuntimeException('SQL failed: ' . $err, $no);
    }
    return $stmt;
}

function store_rows(string $sql, string $types = '', array $params = []): array
{
    $stmt = store_q($sql, $types, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function store_row(string $sql, string $types = '', array $params = []): ?array
{
    $rows = store_rows($sql, $types, $params);
    return $rows[0] ?? null;
}

function store_val(string $sql, string $types = '', array $params = [])
{
    $row = store_row($sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

/** Runs a write; returns affected rows. Use store_last_id() right after an INSERT. */
function store_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = store_q($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return (int) $n;
}

function store_last_id(): int
{
    return (int) db()->insert_id;
}

function store_tx(callable $fn)
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }
}

function store_log(string $message, ?Throwable $ex = null): void
{
    error_log('[store] ' . $message . ($ex ? ' :: ' . $ex->getMessage() : ''));
}

// ---- Settings (stored in the existing site_settings table, shop_* keys) ----
function store_settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (store_rows("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'shop\\_%'") as $r) {
            $cache[$r['setting_key']] = (string) $r['setting_value'];
        }
    }
    return $cache;
}

function store_setting(string $key, string $default = ''): string
{
    $all = store_settings_all();
    return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
}

function store_setting_int(string $key, int $default = 0): int
{
    return (int) store_setting($key, (string) $default);
}

function store_setting_set(string $key, string $value): void
{
    store_exec(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        'ss',
        [$key, $value]
    );
    store_settings_all(true);
}

function store_shop_enabled(): bool
{
    return store_setting('shop_enabled', '1') === '1';
}

// ---- Money ---------------------------------------------------------------
function store_currency_label(string $lang = 'fa'): string
{
    $lang = in_array($lang, ['fa', 'en', 'ar'], true) ? $lang : 'fa';
    return store_setting('shop_currency_' . $lang, store_setting('shop_currency_fa', 'تومان'));
}

/** Plain formatted number + currency (server-side, Persian by default). */
function store_money(int $amount, string $lang = 'fa'): string
{
    $num = number_format($amount);
    if ($lang === 'fa' && store_setting('shop_price_digits', 'fa') === 'fa') {
        $num = fa_digits($num);
    }
    return $num . ' ' . store_currency_label($lang);
}

/** Price markup; js/store.js re-renders the number/currency when the language changes. */
function store_price_html(int $amount, string $class = ''): string
{
    if ($amount === 0) {
        return '<span class="price ' . e($class) . '" data-amount="0"><span data-i18n="store.free">رایگان</span></span>';
    }
    return '<span class="price ' . e($class) . '" data-amount="' . $amount . '">' . e(store_money($amount)) . '</span>';
}

/** Schema.org needs an ISO-4217 code: toman is not one, so convert IRT -> IRR (x10). */
function store_schema_price(int $amount): array
{
    $code = strtoupper(store_setting('shop_currency_code', 'IRT'));
    if ($code === 'IRT') {
        return [(string) ($amount * 10), 'IRR'];
    }
    return [(string) $amount, $code];
}

function store_int_from_input($v): int
{
    $v = strtr((string) $v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', ',' => '', '،' => '', ' ' => '']);
    return preg_match('/^\d{1,15}$/', $v) ? (int) $v : 0;
}

// ---- Multilingual field helpers -------------------------------------------
function store_lf(array $row, string $base, string $lang = 'fa'): string
{
    $v = trim((string) ($row[$base . '_' . $lang] ?? ''));
    return $v !== '' ? $v : trim((string) ($row[$base . '_fa'] ?? ''));
}

/** Attribute string so js/store.js can swap the text when the visitor changes language. */
function store_l10n(array $row, string $base): string
{
    $out = ' data-l10n="1"';
    foreach (['fa', 'en', 'ar'] as $l) {
        $v = trim((string) ($row[$base . '_' . $l] ?? ''));
        if ($v !== '') {
            $out .= ' data-' . $l . '="' . e($v) . '"';
        }
    }
    return $out;
}

/** <span> showing the Persian text, translatable client-side. */
function store_t(array $row, string $base, string $tag = 'span', string $attrs = ''): string
{
    return '<' . $tag . ($attrs !== '' ? ' ' . $attrs : '') . store_l10n($row, $base) . '>' . e(store_lf($row, $base, 'fa')) . '</' . $tag . '>';
}

function store_lines(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), static fn($l) => $l !== ''));
}

// ---- Labels ---------------------------------------------------------------
function store_product_types(): array
{
    return [
        'website_template' => 'قالب وب‌سایت', 'source_code' => 'سورس پروژه', 'wp_plugin' => 'افزونه وردپرس',
        'ui_component' => 'کامپوننت / المان UI', 'automation' => 'اتوماسیون / ورک‌فلو', 'ai_model' => 'مدل هوش مصنوعی',
        'app' => 'اپلیکیشن', 'course' => 'دوره آموزشی', 'bundle' => 'بسته دانلودی', 'other' => 'سایر',
    ];
}

function store_delivery_methods(): array
{
    return ['file' => 'فایل دانلودی', 'license' => 'کد / لایسنس', 'file_license' => 'فایل + لایسنس', 'manual' => 'تحویل دستی'];
}

function store_order_status_labels(): array
{
    return ['pending' => 'در انتظار', 'processing' => 'در حال پردازش', 'completed' => 'تکمیل‌شده', 'cancelled' => 'لغو‌شده', 'expired' => 'منقضی‌شده'];
}

function store_payment_status_labels(): array
{
    return ['unpaid' => 'پرداخت‌نشده', 'pending' => 'در انتظار تأیید', 'paid' => 'پرداخت‌شده', 'failed' => 'ناموفق', 'cancelled' => 'لغو توسط کاربر', 'refunded' => 'بازپرداخت‌شده'];
}

function store_delivery_status_labels(): array
{
    return ['none' => '—', 'pending' => 'در انتظار تحویل', 'delivered' => 'تحویل‌شده', 'partial' => 'تحویل ناقص', 'failed' => 'خطای تحویل'];
}

function store_badge_class(string $status): string
{
    return match ($status) {
        'paid', 'completed', 'delivered', 'approved', 'available', 'assigned', 'published', 'active' => 'a-badge--ok',
        'pending', 'processing', 'unpaid', 'reserved', 'draft', 'partial', 'verifying' => 'a-badge--warn',
        'failed', 'rejected', 'cancelled', 'expired', 'disabled', 'revoked', 'refunded' => 'a-badge--danger',
        default => 'a-badge--muted',
    };
}

// ---- Crypto (license codes at rest) --------------------------------------------
function store_private_dir(string $sub = ''): string
{
    $base = rtrim(STORE_PRIVATE_PATH, '/\\');
    $dir = $sub === '' ? $base : $base . '/' . trim($sub, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    // Defense in depth even when the folder is inside the web root.
    $ht = $base . '/.htaccess';
    if (is_dir($base) && !file_exists($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\nOptions -Indexes -ExecCGI\n");
    }
    return $dir;
}

function store_app_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    if (preg_match('/^[a-f0-9]{64}$/i', STORE_APP_KEY)) {
        return $key = hex2bin(STORE_APP_KEY);
    }
    $file = store_private_dir() . '/app.key';
    if (is_file($file)) {
        $hex = trim((string) file_get_contents($file));
        if (preg_match('/^[a-f0-9]{64}$/i', $hex)) {
            return $key = hex2bin($hex);
        }
    }
    $raw = random_bytes(32);
    if (@file_put_contents($file, bin2hex($raw), LOCK_EX) === false) {
        throw new RuntimeException('Cannot write the store encryption key (storage/app.key). Make storage/ writable or set STORE_APP_KEY.');
    }
    @chmod($file, 0600);
    return $key = $raw;
}

function store_encrypt(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, store_app_key()));
}

function store_decrypt(string $blob): ?string
{
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, store_app_key());
    return $plain === false ? null : $plain;
}

function store_hmac(string $data): string
{
    return hash_hmac('sha256', $data, store_app_key());
}

// ---- Rate limiting --------------------------------------------------------------
/** Returns true if the action is allowed (and records it), false if over the limit. */
function store_rate_limit(string $bucket, string $ident, int $max, int $windowSeconds): bool
{
    $ident = substr($ident, 0, 80);
    $n = (int) store_val(
        'SELECT COUNT(*) FROM shop_rate_hits WHERE bucket = ? AND ident = ? AND hit_at > (NOW() - INTERVAL ? SECOND)',
        'ssi',
        [$bucket, $ident, $windowSeconds]
    );
    if ($n >= $max) {
        return false;
    }
    store_exec('INSERT INTO shop_rate_hits (bucket, ident) VALUES (?, ?)', 'ss', [$bucket, $ident]);
    if (random_int(1, 100) === 1) {
        store_exec('DELETE FROM shop_rate_hits WHERE hit_at < (NOW() - INTERVAL 2 DAY)');
    }
    return true;
}

// ---- Safe internal redirects (open-redirect proof) --------------------------------
/** Returns a vetted site-internal path or ''. Only a short allow-list of store/account paths is accepted. */
function store_safe_next(?string $next): string
{
    $next = trim((string) $next);
    if ($next === '' || strlen($next) > 200) {
        return '';
    }
    if ($next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\') || preg_match('/[\x00-\x1f\s]/', $next)) {
        return '';
    }
    if (!preg_match('#^/[A-Za-z0-9/_\-.?=&%]*$#', $next) || str_contains($next, '..')) {
        return '';
    }
    $path = parse_url($next, PHP_URL_PATH) ?: '';
    foreach (['/checkout', '/cart', '/shop', '/dashboard', '/payment/start', '/payment/success', '/payment/failed'] as $ok) {
        if ($path === $ok || str_starts_with($path, $ok . '/')) {
            return $next;
        }
    }
    return '';
}

// ---- Audit log --------------------------------------------------------------------
function store_audit(string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void
{
    try {
        $adminId = null;
        if (function_exists('current_admin') && ($a = current_admin())) {
            $adminId = (int) $a['id'];
        }
        store_exec(
            'INSERT INTO shop_audit_log (actor_admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)',
            'ississ',
            [$adminId, $action, $entityType, $entityId, mb_substr($details, 0, 1000), client_ip()]
        );
    } catch (Throwable $ex) {
        store_log('audit failed', $ex);
    }
}

function store_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- Draft preview links (admin only generates them; valid ~1-2 hours) ----------------
function store_preview_token(string $slug): string
{
    return substr(store_hmac('preview|' . $slug . '|' . (int) floor(time() / 3600)), 0, 32);
}

function store_preview_valid(string $slug, string $token): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return false;
    }
    foreach ([0, 1] as $back) {
        if (hash_equals(substr(store_hmac('preview|' . $slug . '|' . ((int) floor(time() / 3600) - $back)), 0, 32), $token)) {
            return true;
        }
    }
    return false;
}

/** Store pages that show private data must never be cached by browsers/proxies. */
function store_no_cache(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
}
