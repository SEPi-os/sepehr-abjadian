<?php
require_once __DIR__ . '/PaymentGateway.php';

/** Default driver: no gateway configured. It never pretends to take money. */
final class NullGateway implements PaymentGateway
{
    public function name(): string { return 'none'; }
    public function isConfigured(): bool { return false; }
    public function request(array $order, string $callbackUrl): array { return ['ok' => false, 'error' => 'gateway_not_configured']; }
    public function parseCallback(array $query, array $post): array { return ['ref' => null, 'cancelled' => false]; }
    public function verify(array $payment, array $order): array { return ['status' => 'unknown', 'message' => 'gateway_not_configured']; }
}
