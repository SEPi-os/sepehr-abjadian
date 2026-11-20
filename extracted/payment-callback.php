<?php
/**
 * Gateway return URL:  /payment/callback/{gateway}
 * No login required (the gateway redirects the browser here), and the URL
 * itself proves nothing: the payment is resolved by the gateway's own
 * reference and judged ONLY by a server-to-server verify call.
 */
require_once __DIR__ . '/includes/store-payment.php';

store_no_cache();
if (!store_rate_limit('pay_callback', client_ip(), 60, 60)) {
    http_response_code(429);
    exit('Too many requests');
}
$gateway = strtolower((string) ($_GET['gateway'] ?? ''));
if (!preg_match('/^[a-z0-9_]{2,20}$/', $gateway)) {
    http_response_code(404);
    exit;
}
$res = store_payment_callback($gateway, $_GET, $_POST);
if (empty($res['order_number'])) {
    redirect('/dashboard/orders');
}
redirect('/payment/' . ($res['outcome'] === 'paid' || $res['outcome'] === 'pending' ? 'success' : 'failed') . '/' . $res['order_number']);
