<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * ZarinPal (v4 REST API). Ready to use: set in includes/store-config.php
 *   STORE_GATEWAY = 'zarinpal'  and  STORE_GATEWAY_MERCHANT_ID = '<your 36-char merchant id>'
 * Endpoints/flow follow ZarinPal's official v4 docs (payment.zarinpal.com
 * request.json -> StartPay/{authority} -> verify.json; verify code 100 =
 * paid, 101 = already verified). It has been exercised here against a local
 * mock of that API, NOT against ZarinPal's live servers — make one real
 * small test payment (or STORE_GATEWAY_SANDBOX = true) before announcing the store.
 */
final class ZarinpalGateway implements PaymentGateway
{
    private function base(): string
    {
        // Test harness only: a local mock server can be injected, and only when the
        // process was started with STORE_TEST_MODE=1 (never set in production).
        if (getenv('STORE_TEST_MODE') === '1' && getenv('STORE_TEST_ZARINPAL_BASE')) {
            return rtrim((string) getenv('STORE_TEST_ZARINPAL_BASE'), '/');
        }
        return STORE_GATEWAY_SANDBOX ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    private function toRial(int $amount): int
    {
        return strtoupper(store_setting('shop_currency_code', 'IRT')) === 'IRT' ? $amount * 10 : $amount;
    }

    public function name(): string { return 'zarinpal'; }

    public function isConfigured(): bool
    {
        return preg_match('/^[a-zA-Z0-9-]{30,40}$/', STORE_GATEWAY_MERCHANT_ID) === 1;
    }

    public function request(array $order, string $callbackUrl): array
    {
        [$http, $json, $err] = store_http_json($this->base() . '/pg/v4/payment/request.json', [
            'merchant_id' => STORE_GATEWAY_MERCHANT_ID,
            'amount' => $this->toRial((int) $order['total']),
            'callback_url' => $callbackUrl,
            'description' => 'Order ' . $order['order_number'],
            'metadata' => array_filter(['email' => $order['customer_email'] ?? null, 'mobile' => $order['customer_phone'] ?? null]),
        ]);
        $authority = $json['data']['authority'] ?? null;
        if ($err === null && (int) ($json['data']['code'] ?? 0) === 100 && is_string($authority) && $authority !== '') {
            return ['ok' => true, 'ref' => $authority, 'redirect_url' => $this->base() . '/pg/StartPay/' . rawurlencode($authority)];
        }
        $code = $json['errors']['code'] ?? $json['data']['code'] ?? null;
        return ['ok' => false, 'error' => 'zarinpal request failed' . ($code !== null ? ' (code ' . $code . ')' : '') . ($err ? ': ' . $err : ($http ? ' [HTTP ' . $http . ']' : ''))];
    }

    public function parseCallback(array $query, array $post): array
    {
        $ref = (string) ($query['Authority'] ?? '');
        return [
            'ref' => preg_match('/^[A-Za-z0-9]{10,64}$/', $ref) ? $ref : null,
            'cancelled' => strtoupper((string) ($query['Status'] ?? '')) !== 'OK',
        ];
    }

    public function verify(array $payment, array $order): array
    {
        [$http, $json, $err] = store_http_json($this->base() . '/pg/v4/payment/verify.json', [
            'merchant_id' => STORE_GATEWAY_MERCHANT_ID,
            'amount' => $this->toRial((int) $payment['amount']),
            'authority' => $payment['gateway_ref'],
        ]);
        $raw = $json ? json_encode($json, JSON_UNESCAPED_UNICODE) : '';
        if ($err !== null || $json === null || $http >= 500 || $http === 0) {
            return ['status' => 'unknown', 'message' => $err ?: 'no/invalid response (HTTP ' . $http . ')', 'raw' => $raw];
        }
        $code = (int) ($json['data']['code'] ?? 0);
        if ($code === 100 || $code === 101) {
            // 101 = "already verified": still a genuinely paid transaction (idempotent re-check).
            return [
                'status' => 'paid',
                'transaction_id' => (string) ($json['data']['ref_id'] ?? ''),
                'card_mask' => (string) ($json['data']['card_pan'] ?? ''),
                'amount' => (int) $payment['amount'],
                'raw' => $raw,
            ];
        }
        $errCode = (int) ($json['errors']['code'] ?? $code);
        return ['status' => 'failed', 'message' => 'zarinpal code ' . $errCode, 'raw' => $raw];
    }
}
