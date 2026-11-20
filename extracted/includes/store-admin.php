<?php
/**
 * store-admin.php — helpers shared by the admin pages for the store
 * (admin/shop-*.php): guards, statistics, pagination markup, product/category
 * persistence with full validation, license tools.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/store-payment.php';
require_once __DIR__ . '/store-reviews.php';
require_once __DIR__ . '/store-images.php';

/** True when the store tables exist (so admin pages can show a friendly "run the update" page instead of a SQL error). */
function shop_schema_ready(): bool
{
    try {
        return (int) store_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('shop_products','shop_orders','shop_categories','shop_licenses','shop_reviews')") === 5;
    } catch (Throwable $e) {
        return false;
    }
}

/** Financial / sensitive actions are owner-only; editors can manage catalog + reviews. */
function shop_require_owner(array $admin): void
{
    if (($admin['role'] ?? '') !== 'owner') {
        flash_set('error', 'این بخش فقط برای مدیر اصلی (owner) در دسترسه.');
        redirect('shop-dashboard.php');
    }
}

function shop_is_owner(array $admin): bool
{
    return ($admin['role'] ?? '') === 'owner';
}

function shop_money(int $n): string
{
    return fa_digits(number_format($n)) . ' ' . store_currency_label('fa');
}

function shop_date(?string $d, bool $time = true): string
{
    return $d ? fa_digits(substr($d, 0, $time ? 16 : 10)) : '—';
}

function shop_badge(string $status, ?string $label = null): string
{
    return '<span class="a-badge ' . store_badge_class($status) . '">' . e($label ?? $status) . '</span>';
}

/** Admin pagination (reuses .a-pagination). */
function shop_pager(int $page, int $totalPages, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $h = '<div class="a-pagination">';
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    for ($i = $start; $i <= $end; $i++) {
        $q = $query;
        $q['page'] = $i;
        $h .= $i === $page ? '<span class="is-active">' . fa_digits((string) $i) . '</span>' : '<a href="?' . e(http_build_query($q)) . '">' . fa_digits((string) $i) . '</a>';
    }
    return $h . '</div>';
}

/** Cached counters for the sidebar badges + dashboards. */
function shop_counters(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = ['pending_reviews' => 0, 'attention_orders' => 0];
    try {
        $c['pending_reviews'] = store_pending_review_count();
        $c['attention_orders'] = (int) store_val("SELECT COUNT(*) FROM shop_orders WHERE payment_status = 'paid' AND delivery_status IN ('failed','partial','pending') AND status <> 'completed'")
            + (int) store_val("SELECT COUNT(*) FROM shop_payments WHERE status = 'verifying'");
    } catch (Throwable $e) {
    }
    return $c;
}

/** Revenue = money actually received: PAID orders only (refunded ones are excluded). */
function shop_stats(?string $from = null, ?string $to = null): array
{
    $w = "payment_status = 'paid'";
    $t = '';
    $p = [];
    if ($from) { $w .= ' AND paid_at >= ?'; $t .= 's'; $p[] = $from . ' 00:00:00'; }
    if ($to) { $w .= ' AND paid_at <= ?'; $t .= 's'; $p[] = $to . ' 23:59:59'; }
    $paid = store_row("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS revenue FROM shop_orders WHERE $w", $t, $p);
    return [
        'users' => (int) store_val('SELECT COUNT(*) FROM users'),
        'orders_total' => (int) store_val('SELECT COUNT(*) FROM shop_orders'),
        'orders_paid' => (int) $paid['n'],
        'revenue' => (int) $paid['revenue'],
        'orders_pending' => (int) store_val("SELECT COUNT(*) FROM shop_orders WHERE status = 'pending' AND payment_status IN ('unpaid','pending','failed','cancelled')"),
        'orders_refunded' => (int) store_val("SELECT COUNT(*) FROM shop_orders WHERE payment_status = 'refunded'"),
        'refunded_amount' => (int) store_val("SELECT COALESCE(SUM(total),0) FROM shop_orders WHERE payment_status = 'refunded'"),
        'items_sold' => (int) store_val("SELECT COALESCE(SUM(i.quantity),0) FROM shop_order_items i JOIN shop_orders o ON o.id = i.order_id WHERE o.payment_status = 'paid'"),
        'reviews_total' => (int) store_val('SELECT COUNT(*) FROM shop_reviews'),
        'reviews_pending' => (int) store_val("SELECT COUNT(*) FROM shop_reviews WHERE status = 'pending'"),
        'reviews_approved' => (int) store_val("SELECT COUNT(*) FROM shop_reviews WHERE status = 'approved'"),
        'reviews_rejected' => (int) store_val("SELECT COUNT(*) FROM shop_reviews WHERE status = 'rejected'"),
        'products_published' => (int) store_val("SELECT COUNT(*) FROM shop_products WHERE status = 'published' AND deleted_at IS NULL"),
        'products_draft' => (int) store_val("SELECT COUNT(*) FROM shop_products WHERE status = 'draft' AND deleted_at IS NULL"),
        'categories' => (int) store_val('SELECT COUNT(*) FROM shop_categories'),
        'delivery_issues' => (int) store_val("SELECT COUNT(*) FROM shop_orders WHERE payment_status = 'paid' AND delivery_status IN ('failed','partial')"),
        'verifying' => (int) store_val("SELECT COUNT(*) FROM shop_payments WHERE status = 'verifying'"),
    ];
}

/** Simple dependency-free SVG bar chart. $points = [label => value]. */
function shop_bar_chart(array $points, int $height = 160): string
{
    if (!$points) {
        return '<p class="a-muted">داده‌ای برای نمایش نیست.</p>';
    }
    $max = max(1, max($points));
    $n = count($points);
    $w = 700;
    $bw = max(4, (int) floor($w / $n) - 4);
    $svg = '<svg class="shop-chart" viewBox="0 0 ' . $w . ' ' . ($height + 28) . '" role="img" preserveAspectRatio="none">';
    $i = 0;
    foreach ($points as $label => $v) {
        $h = (int) round($v / $max * $height);
        $x = $i * ($w / $n) + 2;
        $svg .= '<g><title>' . e((string) $label) . ': ' . e(shop_money((int) $v)) . '</title><rect x="' . round($x, 1) . '" y="' . ($height - $h) . '" width="' . $bw . '" height="' . max(1, $h) . '" rx="3" fill="url(#g)"/></g>';
        if ($n <= 16 || $i % (int) ceil($n / 12) === 0) {
            $svg .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($height + 18) . '" font-size="11" text-anchor="middle" fill="#8f97b8">' . e(mb_substr((string) $label, -5)) . '</text>';
        }
        $i++;
    }
    return $svg . '<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5d8bff"/><stop offset="1" stop-color="#2dd4bf"/></linearGradient></defs></svg>';
}

// ---- Product / category persistence ---------------------------------------------------------------

function shop_clean_url(?string $u, bool $httpsOnly = false): ?string
{
    $u = trim((string) $u);
    if ($u === '') {
        return null;
    }
    return preg_match($httpsOnly ? '#^https://#i' : '#^https?://#i', $u) && filter_var($u, FILTER_VALIDATE_URL) ? mb_substr($u, 0, 500) : null;
}

function shop_null_str(?string $s, int $max = 0): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    return $max ? mb_substr($s, 0, $max) : $s;
}

function shop_parse_datetime(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    $t = strtotime(str_replace('T', ' ', $s));
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

/**
 * Reads the product form, validates, returns [data, errors]. Does not write.
 * @return array{0:array,1:string[]}
 */
function shop_read_product_form(array $post, ?array $existing): array
{
    $err = [];
    $d = [];
    $d['title_fa'] = trim((string) ($post['title_fa'] ?? ''));
    $d['title_en'] = trim((string) ($post['title_en'] ?? ''));
    $d['title_ar'] = trim((string) ($post['title_ar'] ?? ''));
    $d['summary_fa'] = mb_substr(trim((string) ($post['summary_fa'] ?? '')), 0, 500);
    $d['summary_en'] = mb_substr(trim((string) ($post['summary_en'] ?? '')), 0, 500);
    $d['summary_ar'] = mb_substr(trim((string) ($post['summary_ar'] ?? '')), 0, 500);
    foreach (['fa', 'en', 'ar'] as $l) {
        $d['description_' . $l] = store_sanitize_html($post['description_' . $l] ?? '');
    }
    foreach (['install_guide', 'usage_guide', 'changelog'] as $f) {
        $d[$f] = store_sanitize_html($post[$f] ?? '') ?: null;
    }
    foreach (['features', 'requirements', 'compatibility', 'license_terms', 'support_info'] as $f) {
        $d[$f] = shop_null_str($post[$f] ?? '');
    }
    $d['category_id'] = (int) ($post['category_id'] ?? 0);
    $d['product_type'] = isset(store_product_types()[$post['product_type'] ?? '']) ? $post['product_type'] : 'other';
    $d['delivery_method'] = isset(store_delivery_methods()[$post['delivery_method'] ?? '']) ? $post['delivery_method'] : 'file';
    $d['sku'] = strtoupper(preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($post['sku'] ?? '')));
    $d['slug_input'] = trim((string) ($post['slug'] ?? ''));
    $d['version'] = shop_null_str($post['version'] ?? '', 40);
    $d['brand'] = shop_null_str($post['brand'] ?? '', 120);
    $d['tags'] = shop_null_str(implode(',', array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) ($post['tags'] ?? ''))))), 400);
    $d['demo_url'] = shop_clean_url($post['demo_url'] ?? '');
    $d['video_url'] = shop_clean_url($post['video_url'] ?? '');
    $d['is_free'] = isset($post['is_free']) ? 1 : 0;
    $d['show_price'] = isset($post['show_price']) ? 1 : 0;
    $d['price'] = store_int_from_input($post['price'] ?? 0);
    $sale = trim((string) ($post['sale_price'] ?? ''));
    $d['sale_price'] = $sale === '' ? null : store_int_from_input($sale);
    $d['sale_starts_at'] = shop_parse_datetime($post['sale_starts_at'] ?? '');
    $d['sale_ends_at'] = shop_parse_datetime($post['sale_ends_at'] ?? '');
    $d['max_quantity'] = max(1, min(100, (int) ($post['max_quantity'] ?? 1)));
    $d['status'] = ($post['status'] ?? '') === 'published' ? 'published' : 'draft';
    $d['is_active'] = isset($post['is_active']) ? 1 : 0;
    $d['is_featured'] = isset($post['is_featured']) ? 1 : 0;
    $d['display_order'] = max(-32000, min(32000, (int) ($post['display_order'] ?? 0)));
    $d['robots_index'] = isset($post['robots_index']) ? 1 : 0;
    $d['seo_title'] = shop_null_str($post['seo_title'] ?? '', 190);
    $d['seo_description'] = shop_null_str($post['seo_description'] ?? '', 320);
    $d['focus_keyword'] = shop_null_str($post['focus_keyword'] ?? '', 120);
    $d['related_keywords'] = shop_null_str($post['related_keywords'] ?? '', 400);
    $d['canonical_url'] = shop_clean_url($post['canonical_url'] ?? '');
    $d['og_title'] = shop_null_str($post['og_title'] ?? '', 190);
    $d['og_description'] = shop_null_str($post['og_description'] ?? '', 320);

    // FAQ + specs repeaters
    $faq = [];
    foreach ((array) ($post['faq_q'] ?? []) as $i => $q) {
        $q = trim((string) $q);
        $a = trim((string) ($post['faq_a'][$i] ?? ''));
        if ($q !== '' && $a !== '') {
            $faq[] = ['q' => mb_substr($q, 0, 300), 'a' => mb_substr($a, 0, 3000)];
        }
    }
    $d['faq_json'] = $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : null;
    $specs = [];
    foreach ((array) ($post['spec_label_fa'] ?? []) as $i => $lab) {
        $lab = trim((string) $lab);
        $val = trim((string) ($post['spec_value_fa'][$i] ?? ''));
        if ($lab !== '' && $val !== '') {
            $specs[] = [
                'label_fa' => mb_substr($lab, 0, 160), 'value_fa' => mb_substr($val, 0, 500),
                'label_en' => mb_substr(trim((string) ($post['spec_label_en'][$i] ?? '')), 0, 160), 'value_en' => mb_substr(trim((string) ($post['spec_value_en'][$i] ?? '')), 0, 500),
                'label_ar' => mb_substr(trim((string) ($post['spec_label_ar'][$i] ?? '')), 0, 160), 'value_ar' => mb_substr(trim((string) ($post['spec_value_ar'][$i] ?? '')), 0, 500),
            ];
        }
    }
    $d['specs'] = $specs;

    // ---- validation
    if ($d['title_fa'] === '') {
        $err[] = 'عنوان فارسی محصول الزامیه.';
    }
    if (!$d['category_id'] || !store_val('SELECT COUNT(*) FROM shop_categories WHERE id = ?', 'i', [$d['category_id']])) {
        $err[] = 'یک دسته‌بندی معتبر انتخاب کن.';
    }
    if ($d['is_free']) {
        $d['price'] = 0;
        $d['sale_price'] = null;
    } elseif ($d['price'] < 1000 && $d['price'] !== 0) {
        $err[] = 'قیمت باید عدد معتبر باشه (یا محصول رو «رایگان» علامت بزن).';
    } elseif ($d['price'] === 0) {
        $err[] = 'قیمت صفر مجاز نیست؛ اگه محصول رایگانه تیک «رایگان» رو بزن.';
    }
    if ($d['sale_price'] !== null && !$d['is_free'] && $d['sale_price'] >= $d['price']) {
        $err[] = 'قیمت تخفیفی باید کمتر از قیمت اصلی باشه.';
    }
    if ($d['sale_starts_at'] && $d['sale_ends_at'] && $d['sale_ends_at'] <= $d['sale_starts_at']) {
        $err[] = 'تاریخ پایان تخفیف باید بعد از شروع باشه.';
    }
    if ($d['delivery_method'] === 'file' || $d['delivery_method'] === 'manual') {
        $d['max_quantity'] = 1;
    }
    if ($d['status'] === 'published') {
        if ($d['focus_keyword'] === null) {
            $err[] = 'برای انتشار، «کلمه‌ی کلیدی اصلی» (تب سئو) الزامیه.';
        }
        if ($d['seo_description'] === null) {
            $err[] = 'برای انتشار، «توضیح متا / اسنیپت» (تب سئو) الزامیه.';
        }
    }
    return [$d, $err];
}

/** Inserts or updates a product (and its specs). Returns the product id. */
function shop_save_product(?array $existing, array $d, int $adminId): int
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $baseSlug = slugify($d['slug_input'] !== '' ? $d['slug_input'] : ($d['title_en'] !== '' ? $d['title_en'] : $d['title_fa']));
        $slug = unique_slug('shop_products', $baseSlug, $existing ? (int) $existing['id'] : null);
        $sku = $d['sku'] !== '' ? $d['sku'] : 'P-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        if ($existing && $d['sku'] === '') {
            $sku = $existing['sku'];
        }
        $publishedAt = ($d['status'] === 'published') ? ($existing['published_at'] ?? date('Y-m-d H:i:s')) : ($existing['published_at'] ?? null);

        $cols = ['category_id' => 'i', 'product_type' => 's', 'delivery_method' => 's', 'title_fa' => 's', 'title_en' => 's', 'title_ar' => 's',
            'summary_fa' => 's', 'summary_en' => 's', 'summary_ar' => 's', 'description_fa' => 's', 'description_en' => 's', 'description_ar' => 's',
            'features' => 's', 'requirements' => 's', 'compatibility' => 's', 'install_guide' => 's', 'usage_guide' => 's', 'changelog' => 's', 'faq_json' => 's',
            'license_terms' => 's', 'support_info' => 's', 'version' => 's', 'brand' => 's', 'tags' => 's', 'demo_url' => 's', 'video_url' => 's',
            'price' => 'i', 'sale_price' => 'i', 'sale_starts_at' => 's', 'sale_ends_at' => 's', 'is_free' => 'i', 'show_price' => 'i', 'max_quantity' => 'i',
            'status' => 's', 'is_active' => 'i', 'is_featured' => 'i', 'display_order' => 'i', 'published_at' => 's',
            'seo_title' => 's', 'seo_description' => 's', 'focus_keyword' => 's', 'related_keywords' => 's', 'canonical_url' => 's', 'og_title' => 's', 'og_description' => 's', 'robots_index' => 'i'];
        $vals = $d + ['published_at' => $publishedAt];
        $types = '';
        $params = [];
        foreach ($cols as $c => $t) {
            $types .= $t;
            $params[] = $vals[$c] ?? null;
        }
        if ($existing) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($cols))) . ', slug = ?, sku = ?';
            store_exec("UPDATE shop_products SET $set WHERE id = ?", $types . 'ssi', array_merge($params, [$slug, $sku, (int) $existing['id']]));
            $id = (int) $existing['id'];
            if ($existing['slug'] !== $slug) {
                // keep every old URL alive: permanent redirect to the new slug
                store_exec('INSERT IGNORE INTO shop_product_redirects (old_slug, product_id) VALUES (?, ?)', 'si', [$existing['slug'], $id]);
                store_exec('DELETE FROM shop_product_redirects WHERE old_slug = ?', 's', [$slug]);
            }
        } else {
            $names = implode(', ', array_keys($cols)) . ', slug, sku';
            $ph = implode(', ', array_fill(0, count($cols) + 2, '?'));
            store_exec("INSERT INTO shop_products ($names) VALUES ($ph)", $types . 'ss', array_merge($params, [$slug, $sku]));
            $id = store_last_id();
        }
        store_exec('DELETE FROM shop_product_specs WHERE product_id = ?', 'i', [$id]);
        foreach ($d['specs'] as $i => $s) {
            store_exec(
                'INSERT INTO shop_product_specs (product_id, label_fa, label_en, label_ar, value_fa, value_en, value_ar, sort_order) VALUES (?,?,?,?,?,?,?,?)',
                'issssssi',
                [$id, $s['label_fa'], $s['label_en'], $s['label_ar'], $s['value_fa'], $s['value_en'], $s['value_ar'], $i]
            );
        }
        store_audit($existing ? 'product.update' : 'product.create', 'product', $id, $d['title_fa']);
        $conn->commit();
        return $id;
    } catch (Throwable $ex) {
        $conn->rollback();
        throw $ex;
    }
}

/** Readiness checklist shown in the product editor (what stops this product from selling or ranking). */
function shop_product_checklist(array $p, int $imageCount, int $fileCount, int $licenseStock): array
{
    $c = [];
    $c[] = [$imageCount > 0, 'عکس اصلی بارگذاری شده'];
    $c[] = [trim((string) $p['seo_description']) !== '', 'توضیح متا (اسنیپت) پر شده'];
    $c[] = [trim((string) $p['focus_keyword']) !== '', 'کلمه‌ی کلیدی اصلی ثبت شده'];
    $c[] = [trim((string) $p['description_fa']) !== '', 'توضیحات کامل نوشته شده'];
    if (in_array($p['delivery_method'], ['file', 'file_license'], true)) {
        $c[] = [$fileCount > 0, 'فایل دانلودی (ZIP/RAR) آپلود شده — بدون آن محصول قابل خرید نیست'];
    }
    if (in_array($p['delivery_method'], ['license', 'file_license'], true)) {
        $c[] = [$licenseStock > 0, 'حداقل یک لایسنس موجود است — بدون آن محصول «ناموجود» می‌شه'];
    }
    return $c;
}

// ---- License tools --------------------------------------------------------------------------------
/** @return array{added:int,duplicates:int,invalid:int} */
function shop_license_bulk_add(int $productId, string $text, string $note = ''): array
{
    $res = ['added' => 0, 'duplicates' => 0, 'invalid' => 0];
    $seen = [];
    foreach (preg_split('/\R/u', $text) as $line) {
        $code = store_license_clean($line);
        if ($code === '' || isset($seen[$code])) {
            continue;
        }
        $seen[$code] = true;
        $r = store_license_add($productId, $code, $note);
        $res[$r === 'ok' ? 'added' : ($r === 'duplicate' ? 'duplicates' : 'invalid')]++;
    }
    return $res;
}

/** Swaps a faulty assigned license for a fresh one on the same order item (old one is disabled). */
function shop_license_replace(int $licenseId, int $adminId, string $reason): array
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $old = store_row("SELECT * FROM shop_licenses WHERE id = ? AND status = 'assigned' FOR UPDATE", 'i', [$licenseId]);
        if (!$old) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'فقط لایسنس تحویل‌شده قابل جایگزینیه.'];
        }
        $new = store_row("SELECT id FROM shop_licenses WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT 1 FOR UPDATE", 'i', [(int) $old['product_id']]);
        if (!$new) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'لایسنس آزادی برای جایگزینی موجود نیست؛ اول لایسنس اضافه کن.'];
        }
        store_exec("UPDATE shop_licenses SET status = 'assigned', order_id = ?, order_item_id = ?, user_id = ?, assigned_at = NOW(), note = ? WHERE id = ?", 'iiisi', [(int) $old['order_id'], (int) $old['order_item_id'], (int) $old['user_id'], mb_substr('replacement for #' . $licenseId, 0, 255), (int) $new['id']]);
        store_exec("UPDATE shop_licenses SET status = 'disabled', note = ? WHERE id = ?", 'si', [mb_substr('replaced: ' . $reason, 0, 255), $licenseId]);
        store_order_event((int) $old['order_id'], 'license_replaced', 'لایسنس جایگزین شد: ' . $reason, 'admin', $adminId);
        store_audit('license.replace', 'license', $licenseId, $reason);
        $conn->commit();
        return ['ok' => true];
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('license replace failed', $ex);
        return ['ok' => false, 'error' => 'خطای داخلی.'];
    }
}

/** Physical delete is only allowed when no order ever referenced the product. */
function shop_product_has_orders(int $productId): bool
{
    return (int) store_val('SELECT COUNT(*) FROM shop_order_items WHERE product_id = ?', 'i', [$productId]) > 0;
}

function shop_delete_product_files_from_disk(int $productId): void
{
    foreach (store_rows('SELECT * FROM shop_product_images WHERE product_id = ?', 'i', [$productId]) as $img) {
        store_delete_image_row($img);
    }
    foreach (store_rows('SELECT stored_name FROM shop_product_files WHERE product_id = ?', 'i', [$productId]) as $f) {
        if (preg_match('/^[a-f0-9]{40}$/', $f['stored_name'])) {
            @unlink(store_private_dir('products') . '/' . $f['stored_name']);
        }
    }
}

// ---- System status (shown in settings) ---------------------------------------------------------------
function shop_system_checks(): array
{
    $ini = static function (string $k): int {
        $v = trim((string) ini_get($k));
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1024, 'm' => $n, 'k' => (int) ($n / 1024), default => (int) ($n / 1048576) };
    };
    $gw = store_gateway();
    $priv = store_private_dir('products');
    return [
        ['درگاه پرداخت', $gw->isConfigured(), $gw->isConfigured() ? 'فعال: ' . $gw->name() : 'پیکربندی نشده — پرداخت آنلاین غیرفعاله (راهنما: STORE_SETUP.md)'],
        ['تبدیل تصاویر به WebP (GD)', store_webp_supported(), store_webp_supported() ? 'فعال' : 'GD با پشتیبانی WebP روی این سرور نصب نیست؛ آپلود تصویر کار نمی‌کنه'],
        ['رمزنگاری لایسنس (sodium)', function_exists('sodium_crypto_secretbox'), function_exists('sodium_crypto_secretbox') ? 'فعال' : 'افزونه sodium لازمه'],
        ['پوشه‌ی خصوصی فایل‌ها', is_dir($priv) && is_writable($priv), $priv . (is_writable($priv) ? '' : ' — قابل نوشتن نیست') . (str_starts_with(realpath($priv) ?: $priv, realpath(BASE_PATH) ?: BASE_PATH) ? ' (داخل web-root؛ با .htaccess محافظت می‌شه — بهتره بیرون از web-root باشه)' : ' (بیرون از web-root ✔)')],
        ['کلید رمزنگاری لایسنس', is_file(store_private_dir() . '/app.key') || preg_match('/^[a-f0-9]{64}$/i', STORE_APP_KEY) === 1, 'از storage/app.key پشتیبان بگیر؛ بدون آن لایسنس‌های ذخیره‌شده خوانده نمی‌شن'],
        ['حد آپلود PHP', $ini('upload_max_filesize') >= 32 && $ini('post_max_size') >= 32, 'upload_max_filesize=' . ini_get('upload_max_filesize') . ' ، post_max_size=' . ini_get('post_max_size') . ' (برای فایل‌های بزرگ ZIP این‌ها رو افزایش بده)'],
        ['افزونه fileinfo', function_exists('finfo_open'), 'برای تشخیص نوع واقعی فایل'],
        ['افزونه DOM', class_exists('DOMDocument'), 'برای پاک‌سازی HTML توضیحات'],
        ['نسخه PHP', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
    ];
}
