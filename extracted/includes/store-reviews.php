<?php
/**
 * store-reviews.php — reviews & ratings. A review is plain text, always
 * starts as 'pending', and only 'approved' ones are ever shown or counted
 * (page, product cards, aggregate rating and JSON-LD all read the cached
 * rating fields, which are recomputed from approved reviews only).
 */
require_once __DIR__ . '/store-catalog.php';

function store_recalc_rating(int $productId): void
{
    store_exec(
        "UPDATE shop_products SET
            rating_avg = COALESCE((SELECT ROUND(AVG(rating), 2) FROM shop_reviews WHERE product_id = ? AND status = 'approved'), 0),
            rating_count = (SELECT COUNT(*) FROM shop_reviews WHERE product_id = ? AND status = 'approved')
         WHERE id = ?",
        'iii',
        [$productId, $productId, $productId]
    );
}

function store_review_clean_text(string $s): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace("/\n{3,}/", "\n\n", $s);
    return trim($s);
}

/**
 * @return array{ok:bool,error?:string,field?:string,updated?:bool}
 */
function store_review_submit(int $userId, int $productId, int $rating, string $title, string $body): array
{
    if (store_setting('shop_reviews_enabled', '1') !== '1') {
        return ['ok' => false, 'error' => 'disabled'];
    }
    $user = store_row('SELECT id, status FROM users WHERE id = ?', 'i', [$userId]);
    if (!$user || $user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'login'];
    }
    $p = store_row('SELECT p.id FROM shop_products p JOIN shop_categories c ON c.id = p.category_id WHERE p.id = ? AND ' . STORE_PUBLIC_WHERE, 'i', [$productId]);
    if (!$p) {
        return ['ok' => false, 'error' => 'product'];
    }
    $title = mb_substr(store_review_clean_text($title), 0, 120);
    $body = store_review_clean_text($body);
    $min = max(1, store_setting_int('shop_review_min_chars', 10));
    $max = max($min, store_setting_int('shop_review_max_chars', 2000));

    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'rating', 'field' => 'rating'];
    }
    if (mb_strlen($body) < $min) {
        return ['ok' => false, 'error' => 'too_short', 'field' => 'body'];
    }
    if (mb_strlen($body) > $max) {
        return ['ok' => false, 'error' => 'too_long', 'field' => 'body'];
    }
    // cheap spam guard: more than 2 links in a review is almost never genuine
    if (preg_match_all('#https?://|www\.#i', $title . ' ' . $body) > 2) {
        return ['ok' => false, 'error' => 'links', 'field' => 'body'];
    }
    if (!store_rate_limit('review_submit', 'u' . $userId, 6, 3600)) {
        return ['ok' => false, 'error' => 'rate_limited'];
    }

    $verified = (int) store_val(
        "SELECT COUNT(*) FROM shop_entitlements e JOIN shop_orders o ON o.id = e.order_id WHERE e.user_id = ? AND e.product_id = ? AND e.status = 'active' AND o.payment_status = 'paid'",
        'ii',
        [$userId, $productId]
    ) > 0 ? 1 : 0;

    $existing = store_row('SELECT id FROM shop_reviews WHERE product_id = ? AND user_id = ?', 'ii', [$productId, $userId]);
    // One main review per buyer per product; editing sends it back through moderation.
    store_exec(
        "INSERT INTO shop_reviews (product_id, user_id, rating, title, body, status, is_verified_purchase, ip_address)
         VALUES (?,?,?,?,?, 'pending', ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), body = VALUES(body), status = 'pending',
            is_verified_purchase = VALUES(is_verified_purchase), ip_address = VALUES(ip_address), admin_note = NULL, moderated_by = NULL, moderated_at = NULL",
        'iiissis',
        [$productId, $userId, $rating, $title, $body, $verified, client_ip()]
    );
    store_recalc_rating($productId);
    return ['ok' => true, 'updated' => (bool) $existing];
}

function store_review_delete_own(int $reviewId, int $userId): bool
{
    $r = store_row('SELECT product_id FROM shop_reviews WHERE id = ? AND user_id = ?', 'ii', [$reviewId, $userId]);
    if (!$r) {
        return false;
    }
    store_exec('DELETE FROM shop_reviews WHERE id = ? AND user_id = ?', 'ii', [$reviewId, $userId]);
    store_recalc_rating((int) $r['product_id']);
    return true;
}

function store_review_set_status(int $reviewId, string $status, int $adminId, ?string $note = null): bool
{
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }
    $r = store_row('SELECT product_id FROM shop_reviews WHERE id = ?', 'i', [$reviewId]);
    if (!$r) {
        return false;
    }
    store_exec(
        'UPDATE shop_reviews SET status = ?, moderated_by = ?, moderated_at = NOW(), admin_note = ? WHERE id = ?',
        'sisi',
        [$status, $adminId, $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null, $reviewId]
    );
    store_recalc_rating((int) $r['product_id']);
    store_audit('review.' . $status, 'review', $reviewId);
    return true;
}

function store_review_admin_delete(int $reviewId): bool
{
    $r = store_row('SELECT product_id FROM shop_reviews WHERE id = ?', 'i', [$reviewId]);
    if (!$r) {
        return false;
    }
    store_exec('DELETE FROM shop_reviews WHERE id = ?', 'i', [$reviewId]);
    store_recalc_rating((int) $r['product_id']);
    store_audit('review.delete', 'review', $reviewId);
    return true;
}

function store_approved_reviews(int $productId, int $limit = 20, int $offset = 0): array
{
    return store_rows(
        "SELECT r.id, r.rating, r.title, r.body, r.is_verified_purchase, r.created_at, u.name AS user_name
         FROM shop_reviews r JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT ? OFFSET ?",
        'iii',
        [$productId, $limit, $offset]
    );
}

function store_user_review(int $productId, int $userId): ?array
{
    return store_row('SELECT * FROM shop_reviews WHERE product_id = ? AND user_id = ?', 'ii', [$productId, $userId]);
}

function store_pending_review_count(): int
{
    return (int) store_val("SELECT COUNT(*) FROM shop_reviews WHERE status = 'pending'");
}
