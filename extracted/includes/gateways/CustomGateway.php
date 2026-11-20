<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * ======================================================================
 *  YOUR OWN GATEWAY — the one place you edit to connect payments.
 * ======================================================================
 * 1. In includes/store-config.php set  STORE_GATEWAY = 'custom'  and put
 *    your merchant/terminal id in STORE_GATEWAY_MERCHANT_ID.
 * 2. Fill in the 4 methods below using your gateway's documentation.
 *    Everything else (cart, checkout, order, callback route, verification
 *    idempotency, delivery of files/licenses, dashboard, revenue stats)
 *    is already done and calls ONLY these methods.
 * 3. Flip $implemented to true when you're done.
 *
 * Your callback URL is passed to request(); the shop receives the user's
 * return at /payment/callback/custom, calls parseCallback() then verify(),
 * and only a verify() answer of status 'paid' ever delivers anything.
 */
final class CustomGateway implements PaymentGateway
{
    private bool $implemented = false; // <-- set to true after you implement the methods below

    public function name(): string
    {
        return 'custom';
    }

    public function isConfigured(): bool
    {
        return $this->implemented && STORE_GATEWAY_MERCHANT_ID !== '';
    }

    public function request(array $order, string $callbackUrl): array
    {
        // Example outline (replace with your gateway's real API):
        //   [$http, $json, $err] = store_http_json('https://YOUR-GATEWAY/api/request', [
        //       'merchant' => STORE_GATEWAY_MERCHANT_ID,
        //       'amount'   => (int) $order['total'],      // convert to Rial here if your gateway needs it
        //       'callback' => $callbackUrl,
        //       'order_id' => $order['order_number'],
        //   ]);
        //   if ($err === null && !empty($json['token'])) {
        //       return ['ok' => true, 'ref' => $json['token'], 'redirect_url' => 'https://YOUR-GATEWAY/pay/' . $json['token']];
        //   }
        //   return ['ok' => false, 'error' => 'gateway error'];
        return ['ok' => false, 'error' => 'gateway_not_configured'];
    }

    public function parseCallback(array $query, array $post): array
    {
        // Return the token/authority your gateway sends back (same value request() returned as 'ref')
        // and whether the user cancelled. Example:
        //   return ['ref' => $query['token'] ?? null, 'cancelled' => ($query['status'] ?? '') !== 'success'];
        return ['ref' => null, 'cancelled' => false];
    }

    public function verify(array $payment, array $order): array
    {
        // Call your gateway's verify API with $payment['gateway_ref'] and $payment['amount'].
        //   'paid'    -> money is confirmed. Include 'transaction_id' (bank reference) and optionally 'card_mask'.
        //   'failed'  -> the gateway definitively says it was NOT paid.
        //   'unknown' -> timeout / 5xx / no clear answer: it will be re-checked automatically.
        return ['status' => 'unknown', 'message' => 'gateway_not_configured'];
    }
}
