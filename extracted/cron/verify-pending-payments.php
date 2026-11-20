<?php
/**
 * cron: php cron/verify-pending-payments.php   (every ~5 minutes)
 * Re-asks the payment gateway about payments stuck in "verifying" (timeouts)
 * or abandoned "redirected" sessions, and releases/finishes them safely.
 * A payment is only ever marked paid by a verified gateway answer.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/store-payment.php';
$r = store_payments_recheck_pending(50);
echo date('c') . ' checked=' . $r['checked'] . ' paid=' . $r['paid'] . ' failed=' . $r['failed'] . ' pending=' . $r['pending'] . "\n";
