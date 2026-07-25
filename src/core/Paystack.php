<?php

/**
 * Paystack server-side client (adapted from MAM Academy's proven library).
 * Docs: https://paystack.com/docs/api — amounts are in kobo (naira × 100).
 */
class Paystack {
    private static function cfg(): array {
        return (require AFT_ROOT . '/config/payment.php')['paystack'] ?? [];
    }

    /** True when a secret key is configured (otherwise fall back to manual). */
    public static function enabled(): bool {
        return trim((string)(self::cfg()['secret_key'] ?? '')) !== '';
    }

    public static function publicKey(): string {
        return (string)(self::cfg()['public_key'] ?? '');
    }

    private static function request(string $method, string $path, array $payload = []): array {
        $key = trim((string)(self::cfg()['secret_key'] ?? ''));
        if ($key === '') throw new RuntimeException('Paystack not configured');

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => 'https://api.paystack.co' . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Cache-Control: no-cache',
            ],
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        // Respect an outbound proxy if the host requires one.
        if ($proxy = (getenv('HTTPS_PROXY') ?: getenv('https_proxy'))) {
            $opts[CURLOPT_PROXY] = $proxy;
            if ($ca = getenv('CURL_CA_BUNDLE')) $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new RuntimeException('Paystack network error: ' . $err);
        return ['code' => $code, 'data' => json_decode((string)$resp, true), 'raw' => (string)$resp];
    }

    /** Start a transaction. Returns the hosted checkout URL to redirect to. */
    public static function initialize(string $email, int $amountNaira, string $reference, string $callbackUrl, array $metadata = []): array {
        return self::request('POST', '/transaction/initialize', [
            'email'        => $email,
            'amount'       => $amountNaira * 100,   // kobo
            'reference'    => $reference,
            'currency'     => 'NGN',
            'metadata'     => $metadata,
            'callback_url' => $callbackUrl,
        ]);
    }

    public static function verify(string $reference): array {
        return self::request('GET', '/transaction/verify/' . rawurlencode($reference));
    }

    /** HMAC-SHA512 signature check for the webhook. */
    public static function verifySignature(string $rawBody, string $signature): bool {
        $secret = trim((string)(self::cfg()['webhook_secret'] ?? ''));
        if ($secret === '') $secret = trim((string)(self::cfg()['secret_key'] ?? ''));
        if ($secret === '' || $signature === '') return false;
        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }
}
