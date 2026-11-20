<?php
/**
 * cron: php cron/release-reservations.php   (every ~10 minutes)
 * Expires unpaid orders past their reservation window and frees their license codes.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/store-orders.php';
echo date('c') . ' expired_orders=' . store_expire_stale_orders(200) . "\n";
