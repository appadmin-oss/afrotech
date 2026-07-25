<?php

/**
 * Discount — promo codes applied at checkout. Validation is centralised so
 * the same rules run on the public estimate and the server-side charge.
 */
class Discount {
    public static function all(): array {
        if (!Database::available()) return [];
        try { return Database::all("SELECT * FROM discount_codes ORDER BY created_at DESC"); }
        catch (Throwable $e) { return []; }
    }

    public static function find(int $id): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM discount_codes WHERE id = ?", [$id]);
    }

    public static function findByCode(string $code): ?array {
        if (!Database::available()) return null;
        $code = strtoupper(trim($code));
        if ($code === '') return null;
        try { return Database::one("SELECT * FROM discount_codes WHERE code = ?", [$code]); }
        catch (Throwable $e) { return null; }
    }

    /**
     * Validate a code against an order amount (naira).
     * @return array{ok:bool, error?:string, code?:array, discount?:int, total?:int}
     */
    public static function evaluate(?string $code, int $amount): array {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return ['ok' => false, 'error' => 'No code entered.'];
        $row = self::findByCode($code);
        if (!$row)                               return ['ok' => false, 'error' => 'That code isn’t recognised.'];
        if (($row['status'] ?? '') !== 'active') return ['ok' => false, 'error' => 'That code is no longer active.'];
        $now = time();
        if (!empty($row['starts_at']) && strtotime($row['starts_at']) > $now) return ['ok' => false, 'error' => 'That code isn’t active yet.'];
        if (!empty($row['ends_at'])   && strtotime($row['ends_at'])   < $now) return ['ok' => false, 'error' => 'That code has expired.'];
        if ($row['max_uses'] !== null && (int)$row['used_count'] >= (int)$row['max_uses']) return ['ok' => false, 'error' => 'That code has reached its limit.'];
        if ((int)$row['min_amount'] > 0 && $amount < (int)$row['min_amount']) return ['ok' => false, 'error' => 'Order is below the minimum for this code.'];

        $discount = $row['type'] === 'percent'
            ? (int) floor($amount * min(100, max(0, (int)$row['value'])) / 100)
            : min($amount, (int)$row['value']);
        $discount = max(0, min($amount, $discount));

        return ['ok' => true, 'code' => $row, 'discount' => $discount, 'total' => $amount - $discount];
    }

    public static function markUsed(string $code): void {
        if (!Database::available()) return;
        Database::exec("UPDATE discount_codes SET used_count = used_count + 1 WHERE code = ?", [strtoupper(trim($code))]);
    }

    public static function save(array $d): int {
        $code = strtoupper(trim($d['code'] ?? ''));
        $args = [
            $code, $d['description'] ?: null,
            in_array($d['type'] ?? 'percent', ['percent','fixed'], true) ? $d['type'] : 'percent',
            (int)($d['value'] ?? 0), (int)($d['min_amount'] ?? 0),
            ($d['max_uses'] === '' || $d['max_uses'] === null) ? null : (int)$d['max_uses'],
            $d['starts_at'] ?: null, $d['ends_at'] ?: null,
            in_array($d['status'] ?? 'active', ['active','paused'], true) ? $d['status'] : 'active',
        ];
        if (!empty($d['id'])) {
            Database::exec(
                "UPDATE discount_codes SET code=?, description=?, type=?, value=?, min_amount=?, max_uses=?, starts_at=?, ends_at=?, status=? WHERE id=?",
                array_merge($args, [(int)$d['id']])
            );
            return (int)$d['id'];
        }
        return (int) Database::insert(
            "INSERT INTO discount_codes (code, description, type, value, min_amount, max_uses, starts_at, ends_at, status) VALUES (?,?,?,?,?,?,?,?,?)",
            $args
        );
    }

    public static function delete(int $id): void {
        if (Database::available()) Database::exec("DELETE FROM discount_codes WHERE id = ?", [$id]);
    }
}
