<?php
/**
 * store-payment.php — gateway-independent payment orchestration.
 *
 * Rules enforced here (see STORE_SETUP.md):
 *  - the browser returning to a "success" URL proves nothing; only a
 *    server-to-server verify() answer of 'paid' marks money received
 *  - verification runs under a row lock on the payment, so duplicate or
 *    concurrent callbacks can never double-deliver or double-count revenue
 *  - amount and order are re-checked against OUR records, not the gateway's word alone
 *  - an unclear answer (timeout) leaves the payment in 'verifying', never 'failed'
 */
require_once __DIR__ . '/store-delivery.php';
require_once __DIR__ . '/gateways/PaymentGateway.php';

function store_gateway(): PaymentGateway
{
    static $gw = null;
    if ($gw !== null) {
        return $gw;
    }
    $name = strtolower((string) STORE_GATEWAY);
    if (!preg_match('/^[a-z0-9_]{2,20}$/', $name) || $name === 'none') {
        require_once __DIR__ . '/gateways/NullGateway.php';
        return $gw = new NullGateway();
    }
    // The mock driver exists ONLY in tests/ and only loads when the process itself was started in test mode.
    if ($name === 'mock') {
        $mock = BASE_PATH . '/tests/MockGateway.php';
        if (getenv('STORE_TEST_MODE') === '1' && is_file($mock)) {
            require_once $mock;
            return $gw = new MockGateway();
        }
        require_once __DIR__ . '/gateways/NullGateway.php';
        return $gw = new NullGateway();
    }
    $file = __DIR__ . '/gateways/' . ucfirst($name) . 'Gateway.php';
    $class = ucfirst($name) . 'Gateway';
    if (is_file($file)) {
        require_once $file;
        if (class_exists($class) && is_subclass_of($class, 'PaymentGateway')) {
            return $gw = new $class();
        }
    }
    require_once __DIR__ . '/gateways/NullGateway.php';
    return $gw = new NullGateway();
}

function store_gateway_ready(): bool
{
    return store_gateway()->isConfigured();
}

function store_callback_url(string $gateway): string
{
    return site_url('/payment/callback/' . $gateway);
}

/**
 * Starts (or resumes) a payment for an order that belongs to the caller.
 * @return array{ok:bool,redirect_url?:string,error?:string,message?:string}
 */
function store_payment_begin(array $order): array
{
    $orderId = (int) $order['id'];
    $fresh = store_row('SELECT * FROM shop_orders WHERE id = ?', 'i', [$orderId]);
    if (!$fresh) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $order = $fresh;
    if ($order['payment_status'] === 'paid') {
        return ['ok' => false, 'error' => 'already_paid'];
    }
    if ($order['status'] !== 'pending' || $order['payment_status'] === 'refunded') {
        return ['ok' => false, 'error' => 'order_closed'];
    }
    if ((int) $order['total'] <= 0) {
        return ['ok' => false, 'error' => 'free_order'];
    }
    if (!store_shop_enabled()) {
        return ['ok' => false, 'error' => 'shop_closed'];
    }
    $gw = store_gateway();
    if (!$gw->isConfigured()) {
        store_order_event($orderId, 'payment_unavailable', 'درگاه پرداخت هنوز پیکربندی نشده؛ پرداخت شروع نشد.');
        return ['ok' => false, 'error' => 'gateway_not_configured'];
    }

    // Resume a very recent live session instead of opening a second one.
    $recent = store_row(
        "SELECT redirect_url FROM shop_payments WHERE order_id = ? AND gateway = ? AND status = 'redirected' AND redirect_url IS NOT NULL AND created_at > (NOW() - INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 1",
        'is',
        [$orderId, $gw->name()]
    );
    if ($recent) {
        return ['ok' => true, 'redirect_url' => $recent['redirect_url']];
    }

    // Re-verify the order is still deliverable (license reservations still ours?) before taking money.
    $short = store_row(
        "SELECT oi.id FROM shop_order_items oi
         WHERE oi.order_id = ? AND oi.delivery_method IN ('license','file_license')
           AND (SELECT COUNT(*) FROM shop_licenses l WHERE l.order_item_id = oi.id AND l.status IN ('reserved','assigned')) < oi.quantity LIMIT 1",
        'i',
        [$orderId]
    );
    if ($short) {
        // Reservation lapsed: try to re-reserve from the free pool, otherwise refuse BEFORE payment.
        if (!store_rereserve($orderId, (int) $order['user_id'])) {
            store_order_event($orderId, 'no_stock', 'لایسنس کافی برای ادامه‌ی پرداخت موجود نیست.');
            return ['ok' => false, 'error' => 'no_stock'];
        }
    }

    $payId = 0;
    try {
        store_exec(
            "INSERT INTO shop_payments (order_id, gateway, amount, currency, status) VALUES (?,?,?,?, 'initiated')",
            'isis',
            [$orderId, $gw->name(), (int) $order['total'], $order['currency']]
        );
        $payId = store_last_id();
        $res = $gw->request($order, store_callback_url($gw->name()));
    } catch (Throwable $ex) {
        store_log('payment request threw', $ex);
        $res = ['ok' => false, 'error' => 'exception'];
    }

    if (!empty($res['ok']) && !empty($res['ref']) && !empty($res['redirect_url'])) {
        try {
            store_exec("UPDATE shop_payments SET gateway_ref = ?, redirect_url = ?, status = 'redirected' WHERE id = ?", 'ssi', [$res['ref'], $res['redirect_url'], $payId]);
        } catch (Throwable $ex) {
            store_exec("UPDATE shop_payments SET status = 'failed', error_message = 'duplicate gateway reference' WHERE id = ?", 'i', [$payId]);
            return ['ok' => false, 'error' => 'gateway_error'];
        }
        $minutes = max(5, store_setting_int('shop_reservation_minutes', 30));
        store_exec("UPDATE shop_orders SET payment_status = 'pending', expires_at = NOW() + INTERVAL ? MINUTE WHERE id = ? AND payment_status <> 'paid'", 'ii', [$minutes, $orderId]);
        store_order_event($orderId, 'payment_started', 'کاربر به درگاه ' . $gw->name() . ' هدایت شد.', 'user', (int) $order['user_id']);
        return ['ok' => true, 'redirect_url' => $res['redirect_url']];
    }

    $msg = mb_substr((string) ($res['error'] ?? 'unknown'), 0, 400);
    if ($payId) {
        store_exec("UPDATE shop_payments SET status = 'failed', error_message = ? WHERE id = ?", 'si', [$msg, $payId]);
    }
    store_order_event($orderId, 'payment_request_failed', 'خطا در ایجاد تراکنش: ' . $msg);
    return ['ok' => false, 'error' => 'gateway_error', 'message' => $msg];
}

/** Re-reserves licenses for an order whose reservation lapsed. All-or-nothing. */
function store_rereserve(int $orderId, int $userId): bool
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $items = store_rows("SELECT id, product_id, quantity FROM shop_order_items WHERE order_id = ? AND delivery_method IN ('license','file_license')", 'i', [$orderId]);
        foreach ($items as $it) {
            $have = (int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE order_item_id = ? AND status IN ('reserved','assigned')", 'i', [(int) $it['id']]);
            $need = (int) $it['quantity'] - $have;
            if ($need <= 0) {
                continue;
            }
            $free = store_rows("SELECT id FROM shop_licenses WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT ? FOR UPDATE", 'ii', [(int) $it['product_id'], $need]);
            if (count($free) < $need) {
                $conn->rollback();
                return false;
            }
            foreach ($free as $f) {
                store_exec("UPDATE shop_licenses SET status = 'reserved', order_id = ?, order_item_id = ?, user_id = ?, reserved_at = NOW() WHERE id = ? AND status = 'available'", 'iiii', [$orderId, (int) $it['id'], $userId, (int) $f['id']]);
            }
        }
        $conn->commit();
        return true;
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('rereserve failed', $ex);
        return false;
    }
}

/**
 * The single place a payment is judged. Used by the browser callback,
 * the admin "re-verify" button and the cron job.
 * @return array{outcome:string,order_id:int}  outcome: paid|failed|cancelled|pending|noop
 */
function store_payment_process(int $paymentId, bool $userCancelled = false): array
{
    $conn = db();
    $outcome = 'pending';
    $orderId = 0;
    $conn->begin_transaction();
    try {
        $pay = store_row('SELECT * FROM shop_payments WHERE id = ? FOR UPDATE', 'i', [$paymentId]);
        if (!$pay) {
            $conn->rollback();
            return ['outcome' => 'noop', 'order_id' => 0];
        }
        $orderId = (int) $pay['order_id'];
        $order = store_row('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);

        if ($pay['status'] === 'paid' || $pay['status'] === 'refunded') {
            $conn->commit();                       // duplicate callback: nothing to do, nothing re-delivered
            $outcome = $pay['status'] === 'paid' ? 'paid' : 'noop';
            goto after;
        }

        if ($userCancelled) {
            store_exec("UPDATE shop_payments SET status = 'cancelled', error_message = 'کاربر پرداخت را لغو کرد' WHERE id = ? AND status IN ('initiated','redirected','verifying')", 'i', [$paymentId]);
            store_exec("UPDATE shop_orders SET payment_status = 'cancelled' WHERE id = ? AND payment_status IN ('unpaid','pending','failed')", 'i', [$orderId]);
            store_order_event($orderId, 'payment_cancelled', 'پرداخت توسط کاربر لغو شد.', 'gateway');
            $conn->commit();
            return ['outcome' => 'cancelled', 'order_id' => $orderId];
        }

        $gw = store_gateway();
        if ($gw->name() !== $pay['gateway'] || !$gw->isConfigured()) {
            // Driver for this payment isn't active right now: keep it verifying, never guess.
            store_exec("UPDATE shop_payments SET status = 'verifying', verify_attempts = verify_attempts + 1 WHERE id = ?", 'i', [$paymentId]);
            $conn->commit();
            return ['outcome' => 'pending', 'order_id' => $orderId];
        }

        $v = $gw->verify($pay, $order);
        $raw = mb_substr((string) ($v['raw'] ?? ''), 0, 2000);

        if (($v['status'] ?? '') === 'paid') {
            $okAmount = (int) $pay['amount'] === (int) $order['total'] && (!isset($v['amount']) || (int) $v['amount'] === (int) $pay['amount']);
            $txId = trim((string) ($v['transaction_id'] ?? ''));
            if (!$okAmount) {
                store_exec("UPDATE shop_payments SET status = 'failed', error_message = 'amount_mismatch', raw_response = ? WHERE id = ?", 'si', [$raw, $paymentId]);
                store_order_event($orderId, 'amount_mismatch', 'مبلغ تأییدشده با مبلغ سفارش مطابقت ندارد؛ نیازمند بررسی دستی.', 'gateway');
                $conn->commit();
                return ['outcome' => 'failed', 'order_id' => $orderId];
            }
            try {
                store_exec(
                    "UPDATE shop_payments SET status = 'paid', transaction_id = ?, card_mask = ?, raw_response = ?, error_message = NULL, paid_at = NOW(), verify_attempts = verify_attempts + 1 WHERE id = ?",
                    'sssi',
                    [$txId !== '' ? $txId : null, mb_substr((string) ($v['card_mask'] ?? ''), 0, 40) ?: null, $raw, $paymentId]
                );
            } catch (RuntimeException $ex) {
                if ($ex->getCode() !== 1062) {
                    throw $ex;
                }
                // the same bank reference was already used by another payment -> replay attempt
                store_exec("UPDATE shop_payments SET status = 'failed', error_message = 'duplicate_transaction', raw_response = ? WHERE id = ?", 'si', [$raw, $paymentId]);
                store_order_event($orderId, 'duplicate_transaction', 'شناسه‌ی تراکنش تکراری است؛ رد شد.', 'gateway');
                $conn->commit();
                return ['outcome' => 'failed', 'order_id' => $orderId];
            }
            $late = in_array($order['status'], ['expired', 'cancelled'], true);
            store_exec(
                "UPDATE shop_orders SET payment_status = 'paid', status = 'processing', delivery_status = 'pending', paid_at = NOW() WHERE id = ? AND payment_status <> 'paid'",
                'i',
                [$orderId]
            );
            store_order_event($orderId, 'paid', 'پرداخت تأیید شد' . ($txId !== '' ? ' (مرجع: ' . $txId . ')' : '') . ($late ? ' — پرداخت دیرهنگام روی سفارش ' . $order['status'] : '') . '.', 'gateway');
            $outcome = 'paid';
        } elseif (($v['status'] ?? '') === 'failed') {
            store_exec("UPDATE shop_payments SET status = 'failed', error_message = ?, raw_response = ?, verify_attempts = verify_attempts + 1 WHERE id = ?", 'ssi', [mb_substr((string) ($v['message'] ?? 'failed'), 0, 400), $raw, $paymentId]);
            store_exec("UPDATE shop_orders SET payment_status = 'failed' WHERE id = ? AND payment_status IN ('unpaid','pending','cancelled')", 'i', [$orderId]);
            store_order_event($orderId, 'payment_failed', 'پرداخت ناموفق: ' . mb_substr((string) ($v['message'] ?? ''), 0, 200), 'gateway');
            $outcome = 'failed';
        } else {
            store_exec("UPDATE shop_payments SET status = 'verifying', error_message = ?, raw_response = ?, verify_attempts = verify_attempts + 1 WHERE id = ?", 'ssi', [mb_substr((string) ($v['message'] ?? 'unknown'), 0, 400), $raw, $paymentId]);
            store_order_event($orderId, 'verify_unknown', 'نتیجه‌ی تأیید پرداخت نامشخص بود؛ دوباره بررسی می‌شود (پرداخت ناموفق ثبت نشد).', 'gateway');
            $outcome = 'pending';
        }
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('payment process failed #' . $paymentId, $ex);
        return ['outcome' => 'pending', 'order_id' => $orderId];
    }

    after:
    if ($outcome === 'paid') {
        $r = store_fulfill_order($orderId);   // delivery trouble never undoes the recorded payment
        if (empty($r['ok'])) {
            store_order_event($orderId, 'delivery_error', 'پرداخت ثبت شد اما تحویل کامل نشد؛ از پنل مدیریت دوباره تلاش کن.');
        }
    }
    return ['outcome' => $outcome, 'order_id' => $orderId];
}

/** Browser/gateway return: resolve the payment by the gateway's own reference and judge it. */
function store_payment_callback(string $gatewayName, array $query, array $post): array
{
    $gw = store_gateway();
    if ($gw->name() !== $gatewayName) {
        return ['outcome' => 'unknown', 'order_number' => null];
    }
    $parsed = $gw->parseCallback($query, $post);
    if (empty($parsed['ref'])) {
        return ['outcome' => 'unknown', 'order_number' => null];
    }
    $pay = store_row('SELECT id, order_id FROM shop_payments WHERE gateway = ? AND gateway_ref = ?', 'ss', [$gw->name(), $parsed['ref']]);
    if (!$pay) {
        return ['outcome' => 'unknown', 'order_number' => null];
    }
    $r = store_payment_process((int) $pay['id'], !empty($parsed['cancelled']));
    $num = store_val('SELECT order_number FROM shop_orders WHERE id = ?', 'i', [(int) $pay['order_id']]);
    return ['outcome' => $r['outcome'], 'order_number' => $num];
}

/** Zero-total orders (admin-marked free products): no gateway, no fake payment — a real $0 settlement. */
function store_complete_free_order(array $order): bool
{
    if ((int) $order['total'] !== 0 || $order['payment_status'] === 'paid') {
        return false;
    }
    $conn = db();
    $conn->begin_transaction();
    try {
        $locked = store_row("SELECT * FROM shop_orders WHERE id = ? AND total = 0 AND payment_status = 'unpaid' AND status = 'pending' FOR UPDATE", 'i', [(int) $order['id']]);
        if (!$locked) {
            $conn->rollback();
            return false;
        }
        store_exec("INSERT INTO shop_payments (order_id, gateway, amount, currency, status, paid_at) VALUES (?, 'free', 0, ?, 'paid', NOW())", 'is', [(int) $order['id'], $order['currency']]);
        store_exec("UPDATE shop_orders SET payment_status = 'paid', status = 'processing', delivery_status = 'pending', paid_at = NOW() WHERE id = ?", 'i', [(int) $order['id']]);
        store_order_event((int) $order['id'], 'paid', 'سفارش رایگان (مبلغ صفر) ثبت شد.', 'system');
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('free order failed', $ex);
        return false;
    }
    store_fulfill_order((int) $order['id']);
    return true;
}

// ---- Admin financial actions (owner only; every one needs a written reason + audit) ----------------
function store_admin_mark_paid(int $orderId, int $adminId, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        return ['ok' => false, 'error' => 'reason_required'];
    }
    $conn = db();
    $conn->begin_transaction();
    try {
        $o = store_row('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$o || $o['payment_status'] === 'paid' || $o['payment_status'] === 'refunded') {
            $conn->rollback();
            return ['ok' => false, 'error' => 'invalid_state'];
        }
        store_exec("INSERT INTO shop_payments (order_id, gateway, amount, currency, status, paid_at, error_message) VALUES (?, 'manual', ?, ?, 'paid', NOW(), ?)", 'iiss', [$orderId, (int) $o['total'], $o['currency'], mb_substr('manual: ' . $reason, 0, 400)]);
        store_exec("UPDATE shop_orders SET payment_status = 'paid', status = 'processing', delivery_status = 'pending', paid_at = NOW() WHERE id = ?", 'i', [$orderId]);
        store_order_event($orderId, 'manual_paid', 'پرداخت به‌صورت دستی تأیید شد. دلیل: ' . $reason, 'admin', $adminId);
        store_audit('order.manual_paid', 'order', $orderId, $reason);
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('manual paid failed', $ex);
        return ['ok' => false, 'error' => 'server'];
    }
    store_rereserve($orderId, (int) $o['user_id']);
    store_fulfill_order($orderId);
    return ['ok' => true];
}

function store_admin_refund(int $orderId, int $adminId, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5) {
        return ['ok' => false, 'error' => 'reason_required'];
    }
    $conn = db();
    $conn->begin_transaction();
    try {
        $o = store_row('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE', 'i', [$orderId]);
        if (!$o || $o['payment_status'] !== 'paid') {
            $conn->rollback();
            return ['ok' => false, 'error' => 'invalid_state'];
        }
        store_exec("UPDATE shop_payments SET status = 'refunded' WHERE order_id = ? AND status = 'paid'", 'i', [$orderId]);
        store_exec("UPDATE shop_orders SET payment_status = 'refunded', status = 'cancelled', admin_note = CONCAT(COALESCE(admin_note,''), ?) WHERE id = ?", 'si', ["\n[refund] " . $reason, $orderId]);
        store_exec("UPDATE shop_entitlements SET status = 'revoked', revoked_at = NOW(), revoked_reason = 'refund' WHERE order_id = ? AND status = 'active'", 'i', [$orderId]);
        // refunded license codes were exposed to the buyer: retire them instead of re-selling
        store_exec("UPDATE shop_licenses SET status = 'disabled', note = 'refunded order' WHERE order_id = ? AND status = 'assigned'", 'i', [$orderId]);
        store_release_reservations($orderId);
        store_exec('UPDATE shop_products p JOIN shop_order_items i ON i.product_id = p.id AND i.order_id = ? SET p.sales_count = GREATEST(0, p.sales_count - i.quantity)', 'i', [$orderId]);
        store_order_event($orderId, 'refunded', 'بازپرداخت ثبت شد. دلیل: ' . $reason, 'admin', $adminId);
        store_audit('order.refund', 'order', $orderId, $reason);
        $conn->commit();
    } catch (Throwable $ex) {
        $conn->rollback();
        store_log('refund failed', $ex);
        return ['ok' => false, 'error' => 'server'];
    }
    return ['ok' => true];
}

/** Re-checks payments left in verifying (and long-open redirected ones). Used by cron + admin. */
function store_payments_recheck_pending(int $limit = 30): array
{
    $rows = store_rows(
        "SELECT id FROM shop_payments
         WHERE gateway NOT IN ('free','manual')
           AND ((status = 'verifying' AND updated_at < (NOW() - INTERVAL 2 MINUTE))
             OR (status = 'redirected' AND created_at < (NOW() - INTERVAL 15 MINUTE) AND created_at > (NOW() - INTERVAL 2 DAY)))
         ORDER BY id LIMIT ?",
        'i',
        [$limit]
    );
    $res = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'pending' => 0];
    foreach ($rows as $r) {
        $o = store_payment_process((int) $r['id']);
        $res['checked']++;
        if (isset($res[$o['outcome']])) {
            $res[$o['outcome']]++;
        }
    }
    return $res;
}
