<?php
/**
 * PaymentGateway — the ONLY contract between the shop and a payment
 * provider. Checkout, orders, delivery and the dashboard know nothing
 * about any specific gateway; to connect yours you implement these five
 * methods in one class (see CustomGateway.php / ZarinpalGateway.php).
 *
 * Amounts are always integers in the store currency (default Toman).
 */
interface PaymentGateway
{
    /** Short machine name stored in shop_payments.gateway (a-z0-9_). */
    public function name(): string;

    /** False until every credential this driver needs has been filled in. */
    public function isConfigured(): bool;

    /**
     * Ask the gateway for a payment session.
     * @param array $order  shop_orders row (order_number, total, customer_*)
     * @return array{ok:bool,ref?:string,redirect_url?:string,error?:string}
     *         ref = the authority/token the gateway issues (stored, unique per gateway)
     */
    public function request(array $order, string $callbackUrl): array;

    /**
     * Read what the gateway sent the browser back with.
     * @return array{ref:?string,cancelled:bool}
     */
    public function parseCallback(array $query, array $post): array;

    /**
     * Server-to-server confirmation using the gateway's official verify API.
     * 'unknown' = could not get a definitive answer (timeout, 5xx): NEVER mark
     * the payment failed in that case, it will be re-checked later.
     * @param array $payment shop_payments row (gateway_ref, amount)
     * @return array{status:'paid'|'failed'|'unknown',transaction_id?:string,card_mask?:string,amount?:int,message?:string,raw?:string}
     */
    public function verify(array $payment, array $order): array;
}

/** Minimal JSON-over-HTTPS helper for drivers. Returns [httpStatus, decodedArray|null, transportError|null]. */
function store_http_json(string $url, array $payload, int $timeout = 15): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $raw === false ? curl_error($ch) : null;
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        $err = $raw === false ? 'stream request failed' : null;
    }
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return [$status, is_array($decoded) ? $decoded : null, $err];
}
