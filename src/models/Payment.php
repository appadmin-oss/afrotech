<?php

class Payment {
    public static function create(array $d): array {
        $ref = Database::available()
            ? Ids::unique('payments', 'reference', fn() => Ids::payment())
            : Ids::payment();
        if (!Database::available()) return ['id' => 0, 'reference' => $ref];

        $id = (int) Database::insert(
            "INSERT INTO payments (reference, registration_id, email, provider, currency, base_amount, discount_code, discount_amount, amount, status)
             VALUES (?,?,?,?,?,?,?,?,?, 'pending')",
            [
                $ref, $d['registration_id'] ?: null, strtolower($d['email'] ?? ''),
                in_array($d['provider'] ?? 'paystack', ['paystack','manual'], true) ? $d['provider'] : 'paystack',
                $d['currency'] ?? 'NGN',
                (int)($d['base_amount'] ?? 0), $d['discount_code'] ?: null,
                (int)($d['discount_amount'] ?? 0), (int)($d['amount'] ?? 0),
            ]
        );
        return ['id' => $id, 'reference' => $ref];
    }

    public static function findByReference(string $ref): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM payments WHERE reference = ?", [$ref]);
    }

    public static function forRegistration(int $regId): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM payments WHERE registration_id = ? ORDER BY id DESC LIMIT 1", [$regId]);
    }

    public static function markSucceeded(string $ref, string $providerRef, string $rawResponse): void {
        if (!Database::available()) return;
        Database::exec(
            "UPDATE payments SET status='succeeded', provider_reference=?, provider_response=?, verified_at=NOW() WHERE reference=?",
            [$providerRef, $rawResponse, $ref]
        );
    }

    public static function markFailed(string $ref, string $status, string $rawResponse = ''): void {
        if (!Database::available()) return;
        if (!in_array($status, ['failed','abandoned'], true)) $status = 'failed';
        Database::exec("UPDATE payments SET status=?, provider_response=? WHERE reference=?", [$status, $rawResponse, $ref]);
    }

    public static function all(int $limit = 200): array {
        if (!Database::available()) return [];
        try { return Database::all("SELECT * FROM payments ORDER BY created_at DESC LIMIT " . (int)$limit); }
        catch (Throwable $e) { return []; }
    }
}
