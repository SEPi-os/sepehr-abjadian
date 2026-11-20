<?php
/**
 * store-config.php — store-specific constants. This file is separate from
 * includes/config.php on purpose: your real DB credentials / reCAPTCHA keys
 * live in config.php and are never touched by the store code. Every value
 * here is wrapped in `if (!defined())`, so you can also override any of
 * them from config.php (it is loaded first).
 *
 * ---- PAYMENT GATEWAY — the only thing you must fill in to go live -------
 *  1. STORE_GATEWAY            'none'     -> checkout creates the order but
 *                                           shows "gateway not configured"
 *                                           (no fake payments, ever)
 *                              'zarinpal' -> ready-made driver (just add the
 *                                           merchant id below)
 *                              'custom'   -> your own gateway: fill in
 *                                           includes/gateways/CustomGateway.php
 *  2. STORE_GATEWAY_MERCHANT_ID  the merchant id / terminal id your gateway gave you
 *  See STORE_SETUP.md ("اتصال درگاه پرداخت") for the exact steps.
 */
require_once __DIR__ . '/config.php';

if (!defined('STORE_GATEWAY')) {
    define('STORE_GATEWAY', 'none');
}
if (!defined('STORE_GATEWAY_MERCHANT_ID')) {
    define('STORE_GATEWAY_MERCHANT_ID', '');   // <-- paste your merchant id here
}
if (!defined('STORE_GATEWAY_SANDBOX')) {
    define('STORE_GATEWAY_SANDBOX', false);    // true = use the gateway's sandbox endpoints (if the driver has them)
}

// ---- Private storage (deliverable ZIP/RAR files) ------------------------
// Best: point this OUTSIDE the web root, e.g. '/home/youruser/shop-private'.
// Default: <project>/storage, which is protected by its own .htaccess
// (Require all denied) AND is only ever served through download.php.
if (!defined('STORE_PRIVATE_PATH')) {
    define('STORE_PRIVATE_PATH', BASE_PATH . '/storage');
}

// Optional: 64 hex chars used to encrypt license codes at rest. If left
// empty a random key is generated once and saved to storage/app.key
// (back that file up — without it stored license codes cannot be read).
if (!defined('STORE_APP_KEY')) {
    define('STORE_APP_KEY', '');
}

// Max size of one uploaded product file / image, in megabytes. The real
// ceiling is also limited by php.ini (upload_max_filesize, post_max_size).
if (!defined('STORE_MAX_FILE_MB')) {
    define('STORE_MAX_FILE_MB', 512);
}
if (!defined('STORE_MAX_IMAGE_MB')) {
    define('STORE_MAX_IMAGE_MB', 10);
}
define('STORE_IMAGE_DIR', UPLOAD_PATH . '/products');
define('STORE_IMAGE_URL', UPLOAD_URL . '/products');
