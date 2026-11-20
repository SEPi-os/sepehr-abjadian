<?php
/**
 * functions.php — small, self-contained helpers shared by the admin
 * panel and the public-facing dynamic pages. Every function here does
 * one thing; nothing in this file talks to the database directly.
 */

// ---- Output escaping ----------------------------------------------------
// Every value that came from the database or a user goes through this
// before it's echoed into HTML — this is what stops stored XSS.
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---- CSRF protection --------------------------------------------------
// One token per session, checked on every state-changing (POST) request
// in the admin panel and on the public forms.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    return csrf_verify_value($_POST['csrf_token'] ?? '');
}

function csrf_verify_value(string $sent): bool
{
    $real = $_SESSION['csrf_token'] ?? '';
    return $sent !== '' && $real !== '' && hash_equals($real, $sent);
}

function csrf_check_or_die(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Go back and try again.');
    }
}

// ---- Bot protection (login / register) ---------------------------------
// Two independent layers, stacked:
//  1) reCAPTCHA (recaptcha_verify) — strong, but only active once you've
//     pasted real keys into includes/config.php.
//  2) Honeypot + minimum-time-to-submit (bot_check_stamp/bot_check_ok) —
//     zero-config, always on, catches simple scripted bots even before
//     reCAPTCHA is configured. Same honeypot trick already used
//     successfully in api/contact-submit.php.
// A submission must pass BOTH layers.

function recaptcha_enabled(): bool
{
    return RECAPTCHA_SITE_KEY !== '' && RECAPTCHA_SECRET_KEY !== '';
}

/** Echo the widget's container div where you want it to render. Renders
 *  nothing (and asks nothing of the visitor) until real keys are set. */
function recaptcha_widget(): string
{
    if (!recaptcha_enabled()) {
        return '';
    }
    return '<div class="g-recaptcha" data-sitekey="' . e(RECAPTCHA_SITE_KEY) . '" data-theme="dark"></div>';
}

/** Include once, near the end of <body>, only on pages that render the widget. */
function recaptcha_script_tag(): string
{
    return recaptcha_enabled()
        ? '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
        : '';
}

/**
 * Verifies a reCAPTCHA token server-side. Returns true automatically
 * when no keys are configured yet (see recaptcha_enabled()) so the site
 * never locks itself out before you've set up your own keys — once
 * you've configured RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY, an empty
 * or invalid token is always rejected. Fails CLOSED on a network/API
 * error (can't confirm the token, so the attempt isn't allowed through)
 * — if your host cannot make outbound HTTPS requests to google.com this
 * will block logins, which is a hosting-configuration issue to fix
 * rather than something to silently bypass here.
 */
function recaptcha_verify(string $token, string $remoteIp): bool
{
    if (!recaptcha_enabled()) {
        return true;
    }
    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $payload, 'timeout' => 5],
        ]);
        $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    }

    if ($raw === false) {
        error_log('reCAPTCHA verification request failed (network/curl unavailable).');
        return false;
    }

    $result = json_decode($raw, true);
    return !empty($result['success']);
}

/** Call when rendering the form (GET), to timestamp when it was shown. */
function bot_check_stamp(string $key): void
{
    $_SESSION[$key] = time();
}

/**
 * Call when handling the POST. $honeypot must be the value of a hidden
 * field real users never see or fill in (CSS-hidden, not type="hidden"
 * — some bots skip type="hidden" fields specifically). Rejects instant
 * submissions (under 2s) as well as any filled honeypot.
 */
function bot_check_ok(string $honeypot, string $key, int $minSeconds = 2): bool
{
    $renderedAt = (int) ($_SESSION[$key] ?? 0);
    unset($_SESSION[$key]);
    if ($honeypot !== '') {
        return false;
    }
    return $renderedAt > 0 && (time() - $renderedAt) >= $minSeconds;
}

// ---- Slugs --------------------------------------------------------------
// Works well for Latin titles (the site's real posts/projects are all
// named in Latin script — "atlas", "telegram-bot", "nova-cafe" — even
// though their fa/en/ar CONTENT differs). If given non-Latin text it
// falls back to a short random slug rather than producing an empty
// string, so the form never silently fails.
function slugify(string $text): string
{
    $text = trim($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'item-' . bin2hex(random_bytes(3));
    }
    return $text;
}

/** Appends -2, -3, ... until $slug is free in $table.$column (existing row $excludeId is ignored, for edits). */
function unique_slug(string $table, string $baseSlug, ?int $excludeId = null): string
{
    $conn = db();
    $slug = $baseSlug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = ?" . ($excludeId ? " AND id != ?" : "") . " LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($excludeId) {
            $stmt->bind_param('si', $slug, $excludeId);
        } else {
            $stmt->bind_param('s', $slug);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
}

// ---- Pagination -----------------------------------------------------------
function paginate_args(int $totalItems, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [$totalPages, $currentPage, $offset];
}

// ---- Flash messages (session-based, one-time) --------------------------
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ---- Secure file uploads ------------------------------------------------
// Whitelists both the extension AND the real MIME type (checked from the
// file's actual bytes with finfo, not the browser-supplied Content-Type,
// which is easy to spoof), rejects anything else, and always writes the
// file under a random name so an uploaded filename can never be used to
// guess or overwrite another file.
function upload_image(array $file, string $subfolder): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen — not an error, just nothing to do
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    $maxBytes = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image is larger than 5MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, or GIF images are allowed.');
    }

    $dir = UPLOAD_PATH . '/' . trim($subfolder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return UPLOAD_URL . '/' . trim($subfolder, '/') . '/' . $filename;
}

// ---- Persian (Jalali) calendar ------------------------------------------
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100)) + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * (int) ($days / 12053));
    $days %= 12053;
    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function fa_digits(string $s): string
{
    return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** Formats a Y-m-d date for display in the given site language. */
function format_post_date(string $ymd, string $lang): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));

    if ($lang === 'fa') {
        [$jy, $jm, $jd] = gregorian_to_jalali($y, $m, $d);
        return fa_digits(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
    }

    $monthsEn = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $monthsAr = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    if ($lang === 'ar') {
        return $d . ' ' . $monthsAr[$m - 1] . ' ' . $y;
    }
    return $monthsEn[$m - 1] . ' ' . $d . ', ' . $y;
}


/** Builds embeddable HTML for a blog post's optional video field — accepts
 *  a YouTube/Aparat page URL (converted to their embed format) or a direct
 *  video file URL (rendered with a native <video> tag). Returns '' if the
 *  URL doesn't match anything embeddable, rather than guessing. */
function video_embed_html(?string $url): string
{
    if (!$url) {
        return '';
    }
    $url = trim($url);

    // Only http(s) is ever allowed into an iframe/video src — blocks a
    // lower-privileged 'editor' admin from entering a javascript:/data:
    // URI here (already HTML-escaped below either way, so this is
    // defense-in-depth, not a fix for an active exploit).
    if (!preg_match('#^https://#i', $url) && !preg_match('#^http://#i', $url)) {
        return '';
    }

    if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $url, $m) || preg_match('#youtube\.com/watch\?v=([a-zA-Z0-9_-]+)#', $url, $m)) {
        $id = $m[1];
        return '<iframe src="https://www.youtube.com/embed/' . e($id) . '" title="Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
    }
    if (preg_match('#youtube\.com/embed/#', $url)) {
        return '<iframe src="' . e($url) . '" title="Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
    }
    if (preg_match('#aparat\.com/v/([a-zA-Z0-9]+)#', $url, $m)) {
        return '<iframe src="https://www.aparat.com/video/video/embed/videohash/' . e($m[1]) . '/vt/frame" title="Video" frameborder="0" allowfullscreen loading="lazy"></iframe>';
    }
    if (preg_match('#\.(mp4|webm|ogg)(\?.*)?$#i', $url)) {
        return '<video controls preload="metadata" src="' . e($url) . '"></video>';
    }
    return '<iframe src="' . e($url) . '" title="Video" frameborder="0" allowfullscreen loading="lazy"></iframe>';
}

function csv_to_chips(?string $csv): array
{
    if (!$csv) {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/**
 * Renders the header's login/register button OR, when a regular visitor is
 * already signed in, an "Account / Dashboard" button pointing at
 * dashboard.php instead — same markup shape (.auth-btn + svg + span) both
 * ways, so every existing CSS rule for .auth-btn (including the mobile
 * icon-only collapse in style.css) keeps working unmodified.
 *
 * $user is whatever current_user() returns (null when signed out). Every
 * page that prints this must have called public_session_start() first.
 */
function render_auth_button(?array $user): string
{
    // Store addition: cart button with a live count badge (localStorage cart, key sa_cart_v1),
    // rendered right next to the account button on every page. Root-absolute links so it also
    // works from nested store URLs such as /shop/my-product.
    $cart = '<a class="cart-btn" href="/cart" id="cartBtn" aria-label="سبد خرید" data-i18n-aria-label="bottomNav.cart">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>'
        . '<span class="cart-btn__badge" id="cartBadge" hidden>0</span></a>'
        . '<script>(function(){function u(){try{var c=JSON.parse(localStorage.getItem("sa_cart_v1")||"{}"),n=0;(c.items||[]).forEach(function(i){n+=(+i.qty||0)});var b=document.getElementById("cartBadge");if(b){b.textContent=n>99?"99+":n;b.hidden=!n}}catch(e){}}u();window.addEventListener("storage",u);window.addEventListener("sa:cart-changed",u)})();</script>';
    if ($user) {
        return $cart . '<a class="auth-btn auth-btn--account" href="/dashboard">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.42 3.58-8 8-8s8 3.58 8 8"/></svg>'
            . '<span data-i18n="auth.dashboardBtn">داشبورد</span>'
            . '</a>';
    }
    return $cart . '<a class="auth-btn" href="/account">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>'
        . '<span data-i18n="auth.headerBtn">ورود / ثبت‌نام</span>'
        . '</a>';
}
