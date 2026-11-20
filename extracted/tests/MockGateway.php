<?php
/**
 * TEST-ONLY gateway. It is loaded exclusively when the PHP process was
 * started with the environment variable STORE_TEST_MODE=1 AND
 * STORE_GATEWAY is set to 'mock' (see includes/store-payment.php). It
 * can't be activated from config alone and tests/ is denied over HTTP.
 * Behaviour is steered by the authority string, so tests can simulate:
 *   paid, failed, unknown (timeout), amount mismatch.
 */
require_once __DIR__ . '/../includes/gateways/PaymentGateway.php';

final class MockGateway implements PaymentGateway
{
    public function name(): string { return 'mock'; }
    public function isConfigured(): bool { return true; }

    public function request(array $order, string $callbackUrl): array
    {
        $ref = 'MOCK' . strtoupper(bin2hex(random_bytes(8)));
        return ['ok' => true, 'ref' => $ref, 'redirect_url' => 'https://mock-gateway.invalid/pay/' . $ref];
    }

    public function parseCallback(array $query, array $post): array
    {
        $ref = (string) ($query['ref'] ?? '');
        return ['ref' => preg_match('/^MOCK[A-F0-9]{16}$/', $ref) ? $ref : null, 'cancelled' => ($query['status'] ?? 'ok') !== 'ok'];
    }

    public function verify(array $payment, array $order): array
    {
        $file = sys_get_temp_dir() . '/mock_gateway_' . $payment['gateway_ref'];
        $mode = is_file($file) ? trim((string) file_get_contents($file)) : 'unpaid';
        return match ($mode) {
            'paid' => ['status' => 'paid', 'transaction_id' => 'TX' . substr($payment['gateway_ref'], 4), 'card_mask' => '603799******1234', 'amount' => (int) $payment['amount']],
            'paid_wrong_amount' => ['status' => 'paid', 'transaction_id' => 'TXW' . substr($payment['gateway_ref'], 4), 'amount' => (int) $payment['amount'] + 1000],
            'unknown' => ['status' => 'unknown', 'message' => 'timeout'],
            default => ['status' => 'failed', 'message' => 'not paid'],
        };
    }
}
