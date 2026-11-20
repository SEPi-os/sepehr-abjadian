<?php
/** POST: reveals ONE license code to its owner (never embedded in page HTML). Needs session + CSRF. */
require_once __DIR__ . '/../includes/store-delivery.php';
require_once __DIR__ . '/../includes/user-auth.php';

public_session_start();
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    store_json(['ok' => false, 'error' => 'method'], 405);
}
if (!$user) {
    store_json(['ok' => false, 'error' => 'login'], 401);
}
$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!csrf_verify_value((string) $sent)) {
    store_json(['ok' => false, 'error' => 'csrf'], 403);
}
if (!store_rate_limit('license_reveal', 'u' . (int) $user['id'], 40, 60)) {
    store_json(['ok' => false, 'error' => 'rate_limited'], 429);
}
$code = store_license_reveal_for_user((int) ($_POST['id'] ?? 0), (int) $user['id']);
session_write_close();
if ($code === null) {
    store_json(['ok' => false, 'error' => 'not_found'], 404);
}
store_json(['ok' => true, 'code' => $code]);
