<?php
/**
 * store-orders.php — order creation (one DB transaction: order + items +
 * license reservations), history events, expiry, cancel.
 */
require_once __DIR__ . '/store-cart.php';

function store_new_order_number(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $rand = '';
    for ($i = 0; $i < 6; $i++) {
        $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'SA-' . date('ymd') . '-' . $rand;
}

function store_order_event(int $orderId, string $event, string $message = '', string $actor = 'system', ?int $actorId = null): void
{
    try {
        store_exec(
            'INSERT INTO shop_order_events (order_id, event, message, actor_type, actor_id) VALUES (?,?,?,?,?)',
            'isssi',
            [$orderId, $event, mb_substr($message, 0, 500), $actor, $actorId]
        );
    } catch (Throwable $ex) {
        store_log('order event failed', $ex);
    }
}

function store_order_by_number(string $number): ?array
{
    return preg_match('/^SA-\d{6}-[A-Z0-9]{6}$/', $number) ? store_row('SELECT * FROM shop_orders WHERE order_number = ? LIMIT 1', 's', [$number]) : null;
}

/** Ownership enforced in SQL — a user can never load somebody else's order (IDOR-proof). */
function store_order_for_user(string $number, int $userId): ?array
{
    return preg_match('/^SA-\d{6}-[A-Z0-9]{6}$/', $number)
        ? store_row('SELECT * FROM shop_orders WHERE order_number = ? AND user_id = ? LIMIT 1', 'si', [$number, $userId])
        : null;
}

function store_order_items(int $orderId): array
{
    return store_rows('SELECT * FROM shop_order_items WHERE order_id = ? ORDER BY id', 'i', [$orderId]);
}

function store_orders_for_user(int $userId, int $limit = 50, int $offset = 0): array
{
    return store_rows(
        'SELECT o.*, (SELECT COUNT(*) FROM shop_order_items i WHERE i.order_id = o.id) AS item_count
         FROM shop_orders o WHERE o.user_id = ? ORDER BY o.id DESC LIMIT ? OFFSET ?',
        'iii',
        [$userId, $limit, $offset]
    );
}

/** Frees license reservations held by an order (never touches assigned licenses). */
function store_release_reservations(int $orderId): int
{
    return store_exec(
        "UPDATE shop_licenses SET status = 'available', order_id = NULL, order_item_id = NULL, user_id = NULL, reserved_at = NULL
         WHERE order_id = ? AND status = 'reserved'",
        'i',
        [$orderId]
    );
}

/**
 * Expires unpaid orders whose reservation window ended — unless the buyer is
 * mid-payment (a recent redirected/verifying attempt), in which case we wait.
 */
function store_expire_stale_orders(int $limit = 50): int
{
    $rows = store_rows(
        "SELECT o.id FROM shop_orders o
         WHERE o.status = 'pending' AND o.payment_status IN ('unpaid','failed','cancelled','pending')
           AND o.expires_at IS NOT NULL AND o.expires_at < NOW()
           AND NOT EXISTS (SELECT 1 FROM shop_payments pay WHERE pay.order_id = o.id AND pay.status IN ('redirected','verifying') AND pay.updated_at > (NOW() - INTERVAL 45 MINUTE))
         LIMIT ?",
        'i',
        [$limit]
    );
    $n = 0;
    foreach ($rows as $r) {
        $changed = store_exec(
            "UPDATE shop_orders SET status = 'expired' WHERE id = ? AND status = 'pending' AND payment_status <> 'paid'",
            'i',
            [(int) $r['id']]
        );
        if ($changed) {
            store_release_reservations((int) $r['id']);
            store_order_event((int) $r['id'], 'expired', 'مهلت پرداخت تمام شد؛ رزرو لایسنس‌ها آزاد شد.');
            $n++;
        }
    }
    return $n;
}

/**
 * Creates an order from the posted cart. Prices/stock come ONLY from the DB.
 * $expectedTotal is the amount the buyer saw on screen; if the server computes
 * something different the order is NOT created (price shown == price charged).
 *
 * @return array{ok:bool,order?:array,error?:string,cart?:array,existing?:bool}
 */
function store_create_order(array $user, array $rawCart, array $customer, string $token, int $expectedTotal): array
{
    $userId = (int) $user['id'];
    if (!store_shop_enabled()) {
        return ['ok' => false, 'error' => 'shop_closed'];
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return ['ok' => false, 'error' => 'bad_token'];
    }
    $dbUser = store_row('SELECT id, status FROM users WHERE id = ?', 'i', [$userId]);
    if (!$dbUser || $dbUser['status'] !== 'active') {
        return ['ok' => false, 'error' => 'user_disabled'];
    }

    // Double-click / back-button resubmit of the same form -> same order, never a second one.
    $existing = store_row('SELECT * FROM shop_orders WHERE checkout_token = ? AND user_id = ?', 'si', [$token, $userId]);
    if ($existing) {
        return ['ok' => true, 'order' => $existing, 'existing' => true];
    }
    if (!store_rate_limit('order_create', 'u' . $userId, 15, 3600)) {
        return ['ok' => false, 'error' => 'rate_limited'];
    }
    store_expire_stale_orders();

    $conn = db();
    $conn->begin_transaction();
    try {
        $cart = store_cart_resolve($rawCart);
        if (!$cart['lines']) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'empty', 'cart' => $cart];
        }
        if (!$cart['ok'] || $cart['has_issues']) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'cart_invalid', 'cart' => $cart];
        }
        if ($cart['total'] !== $expectedTotal) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'total_changed', 'cart' => $cart];
        }

        $minutes = max(5, store_setting_int('shop_reservation_minutes', 30));
        $number = '';
        $orderId = 0;
        for ($try = 0; $try < 5; $try++) {
            $number = store_new_order_number();
            try {
                store_exec(
                    "INSERT INTO shop_orders (order_number, user_id, subtotal, discount_total, total, currency, customer_name, customer_email, customer_phone, customer_note, checkout_token, expires_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW() + INTERVAL ? MINUTE)",
                    'siiiissssssi',
                    [$number, $userId, $cart['subtotal'], $cart['discount'], $cart['total'], store_setting('shop_currency_code', 'IRT'),
                     $customer['name'], $customer['email'], $customer['phone'] ?: null, $customer['note'] ?: null, $token, $minutes]
                );
                $orderId = store_last_id();
                break;
            } catch (RuntimeException $ex) {
                if ($ex->getCode() !== 1062 || $try === 4) {
                    throw $ex;
                }
            }
        }

        foreach ($cart['lines'] as $line) {
            $p = store_row('SELECT delivery_method, product_type, sku FROM shop_products WHERE id = ?', 'i', [$line['id']]);
            store_exec(
                'INSERT INTO shop_order_items (order_id, product_id, product_title, product_sku, product_type, delivery_method, quantity, unit_price, unit_discount, line_total)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                'iissssiiii',
                [$orderId, $line['id'], $line['title']['fa'], $p['sku'], $p['product_type'], $p['delivery_method'], $line['qty'],
                 $line['unit_list'], $line['unit_list'] - $line['unit_final'], $line['line_total']]
            );
            $itemId = store_last_id();

            if (in_array($p['delivery_method'], ['license', 'file_license'], true)) {
                // Reserve exactly `qty` license codes for this order so "paid but nothing to deliver" can't happen.
                $free = store_rows(
                    "SELECT id FROM shop_licenses WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT ? FOR UPDATE",
                    'ii',
                    [$line['id'], $line['qty']]
                );
                if (count($free) < $line['qty']) {
                    $conn->rollback();
                    return ['ok' => false, 'error' => 'no_stock', 'cart' => $cart];
                }
                foreach ($free as $f) {
                    store_exec(
                        "UPDATE shop_licenses SET status = 'reserved', order_id = ?, order_item_id = ?, user_id = ?, reserved_at = NOW() WHERE id = ? AND status = 'available'",
                        'iiii',
                        [$orderId, $itemId, $userId, (int) $f['id']]
                    );
                }
            }
        }
        store_order_event($orderId, 'created', 'سفارش ثبت شد (' . $cart['count'] . ' مورد، مبلغ ' . $cart['total'] . ').', 'user', $userId);
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        if ($ex->getCode() === 1062) {
            $again = store_row('SELECT * FROM shop_orders WHERE checkout_token = ? AND user_id = ?', 'si', [$token, $userId]);
            if ($again) {
                return ['ok' => true, 'order' => $again, 'existing' => true];
            }
        }
        store_log('create order failed', $ex);
        return ['ok' => false, 'error' => 'server'];
    }

    return ['ok' => true, 'order' => store_row('SELECT * FROM shop_orders WHERE id = ?', 'i', [$orderId])];
}

/** User/admin cancel of an order that has not been paid (and has no live payment attempt). */
function store_cancel_order(int $orderId, string $actor, ?int $actorId, string $reason = ''): bool
{
    $n = store_exec(
        "UPDATE shop_orders SET status = 'cancelled', payment_status = IF(payment_status = 'pending', 'cancelled', payment_status)
         WHERE id = ? AND status = 'pending' AND payment_status IN ('unpaid','failed','cancelled')
           AND NOT EXISTS (SELECT 1 FROM shop_payments p WHERE p.order_id = shop_orders.id AND p.status IN ('redirected','verifying') AND p.updated_at > (NOW() - INTERVAL 45 MINUTE))",
        'i',
        [$orderId]
    );
    if ($n) {
        store_release_reservations($orderId);
        store_order_event($orderId, 'cancelled', $reason ?: 'سفارش لغو شد.', $actor, $actorId);
    }
    return $n > 0;
}
