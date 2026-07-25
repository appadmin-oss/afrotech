<?php

/**
 * Setting — single source of truth for everything the flier used to hard-code
 * (fee, age, deadline, cohort, campuses, payment toggle). Editable in admin,
 * cached per-request, with sensible built-in defaults so pages render before
 * anything is seeded and when the DB is offline.
 */
class Setting {
    private static ?array $cache = null;

    public static function defaults(): array {
        return [
            'program_name'          => 'Afrotech Academy Summer School',
            'currency_symbol'       => '₦',
            'currency_code'         => 'NGN',
            'summer_fee'            => '40000',
            'min_age'               => '7',
            'age_label'             => 'Age 7+',
            'cohort_label'          => 'Summer 2026',
            'cohort_start'          => '2026-08-04',
            'registration_deadline' => '2026-07-31 23:59:00',
            'seats_total'           => '120',
            'payment_enabled'       => '1',
            'payment_provider'      => 'paystack',
            'bank_transfer_details' => 'Afrostrength Limited · GTBank · 0123456789 (quote your reference code)',
            'whatsapp_phone'        => '+234 810 019 1456',
            'contact_email'         => 'reachus@afrostrength.com',
        ];
    }

    /** Editable keys grouped for the admin settings screen. */
    public static function groups(): array {
        return [
            'Program' => ['program_name','age_label','min_age','cohort_label','cohort_start','seats_total'],
            'Pricing & payment' => ['summer_fee','currency_symbol','currency_code','payment_enabled','payment_provider','bank_transfer_details'],
            'Deadline' => ['registration_deadline'],
            'Contact' => ['whatsapp_phone','contact_email'],
        ];
    }

    private static function load(): void {
        self::$cache = [];
        if (!Database::available()) return;
        try {
            foreach (Database::all("SELECT key_name, value_text FROM settings") as $r) {
                self::$cache[$r['key_name']] = (string)$r['value_text'];
            }
        } catch (Throwable $e) { /* table not migrated yet */ }
    }

    public static function get(string $key, ?string $default = null): string {
        if (self::$cache === null) self::load();
        if (isset(self::$cache[$key]) && self::$cache[$key] !== '') return self::$cache[$key];
        return $default ?? (self::defaults()[$key] ?? '');
    }

    public static function int(string $key, int $default = 0): int {
        $v = self::get($key, (string)$default);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function bool(string $key, bool $default = false): bool {
        $v = strtolower(self::get($key, $default ? '1' : '0'));
        return in_array($v, ['1','true','yes','on'], true);
    }

    /** Formatted money using the configured currency symbol. */
    public static function money(int $amount): string {
        return self::get('currency_symbol', '₦') . number_format($amount);
    }

    public static function fee(): int { return self::int('summer_fee', 40000); }

    public static function deadline(): ?int {
        $t = strtotime(self::get('registration_deadline', ''));
        return $t ?: null;
    }

    public static function deadlinePassed(): bool {
        $d = self::deadline();
        return $d !== null && $d < time();
    }

    public static function all(): array {
        if (self::$cache === null) self::load();
        return array_merge(self::defaults(), self::$cache);
    }

    public static function set(string $key, string $value): void {
        if (!Database::available()) return;
        Database::exec(
            "INSERT INTO settings (key_name, value_text) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)",
            [$key, $value]
        );
        self::$cache = null;
    }
}
