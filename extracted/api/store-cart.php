<?php
/**
 * POST /api/store-cart.php  {"items":[{"id":1,"qty":2,"seen":150000}]}
 * Read-only: returns the authoritative priced cart. The browser's
 * localStorage only ever supplies product ids + quantities.
 */
require_once __DIR__ . '/../includes/store-cart.php';
require_once __DIR__ . '/../includes/user-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    store_json(['ok' => false, 'error' => 'method'], 405);
}
if (!store_rate_limit('cart_api', client_ip(), 120, 60)) {
    store_json(['ok' => false, 'error' => 'rate_limited'], 429);
}
$body = file_get_contents('php://input', false, null, 0, 9000);
$items = store_cart_from_json((string) $body);
$cart = store_cart_resolve($items);
public_session_start();
$logged = (bool) current_user();
session_write_close();
store_json(['ok' => true, 'cart' => $cart, 'loggedIn' => $logged, 'closed' => !store_shop_enabled()]);
