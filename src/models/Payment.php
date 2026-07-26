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

    /**
     * Claim a payment as succeeded — exactly once.
     *
     * The gateway callback (the parent's browser) and the webhook (Paystack's
     * server) routinely arrive within milliseconds of each other, and an
     * operator can be clearing a transfer at the same moment. A read-then-write
     * check leaves a window where all of them see 'pending' and each runs the
     * success side effects: two receipts, a discount counted twice.
     *
     * So the guard IS the write. `WHERE status <> 'succeeded'` makes the row the
     * lock, and only the caller whose UPDATE actually changed a row gets true —
     * that one owns the side effects. Everyone else is a no-op, which is what
     * idempotent confirmation means.
     */
    public static function claimSucceeded(string $ref, string $providerRef, string $rawResponse, string $via = 'callback', int $adminId = 0, string $note = ''): bool {
        if (!Database::available()) return false;
        $via = in_array($via, ['callback', 'webhook', 'operator', 'manual'], true) ? $via : 'callback';

        if (!self::hasConfirmationColumns()) {
            // Pre-migration: still atomic, just without the audit trail.
            return Database::exec(
                "UPDATE payments SET status='succeeded', provider_reference=?, provider_response=?, verified_at=NOW()
                  WHERE reference=? AND status <> 'succeeded'",
                [$providerRef, $rawResponse, $ref]
            ) > 0;
        }

        return Database::exec(
            "UPDATE payments
                SET status='succeeded', provider_reference=?, provider_response=?, verified_at=NOW(),
                    confirmed_via=?, confirmed_by=?, confirmation_note=?
              WHERE reference=? AND status <> 'succeeded'",
            [$providerRef, $rawResponse, $via, $adminId ?: null, $note !== '' ? mb_substr($note, 0, 300) : null, $ref]
        ) > 0;
    }

    /**
     * Claim the right to send the receipt. Separate from the status claim
     * because a payment confirmed before this migration ran has no
     * receipt_sent_at, and a re-verification shouldn't email again.
     * Returns true at most once per payment.
     */
    public static function claimReceipt(string $ref): bool {
        if (!Database::available()) return false;
        if (!self::hasConfirmationColumns()) return true;   // best effort pre-migration
        return Database::exec(
            "UPDATE payments SET receipt_sent_at = NOW() WHERE reference = ? AND receipt_sent_at IS NULL",
            [$ref]
        ) > 0;
    }

    /** Has the payment-confirmation migration been applied? Cached per request. */
    public static function hasConfirmationColumns(): bool {
        static $has = null;
        if ($has !== null) return $has;
        if (!Database::available()) return $has = false;
        try {
            return $has = (bool) Database::all("SHOW COLUMNS FROM payments LIKE 'confirmed_via'");
        } catch (Throwable $e) { return $has = false; }
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
