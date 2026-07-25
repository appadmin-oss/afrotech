<?php

/**
 * Ids — human-readable, collision-resistant identifier minting.
 *
 * Every learner, summer registrant, operator, and certificate gets a stable
 * public ID in a consistent house format:
 *
 *   Student ...... AFT-STU-2026-7H3QK
 *   Summer intake  AFT-SS26-4F9RD        (SS26 = Summer School 2026)
 *   Operator ..... AFT-STAFF-9K2P
 *   Certificate .. AFT-CERT-2026-J4M8-QP7X
 *
 * The random segment draws from a Crockford-style alphabet (no 0/O/1/I/L)
 * so the codes survive being read aloud, printed on an ID card, or typed
 * from a WhatsApp screenshot without ambiguity.
 */
class Ids {
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** N random characters from the unambiguous alphabet. */
    public static function token(int $len = 5): string {
        $a = self::ALPHABET;
        $max = strlen($a) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $a[random_int(0, $max)];
        }
        return $out;
    }

    public static function student(): string {
        return sprintf('AFT-STU-%s-%s', date('Y'), self::token(5));
    }

    /** Summer-school intake code, tagged with the two-digit year. */
    public static function summer(): string {
        return sprintf('AFT-SS%s-%s', date('y'), self::token(5));
    }

    public static function staff(): string {
        return sprintf('AFT-STAFF-%s', self::token(4));
    }

    public static function certificate(): string {
        return sprintf('AFT-CERT-%s-%s-%s', date('Y'), self::token(4), self::token(4));
    }

    /** Payment reference — passed to the gateway; must be URL/DB safe. */
    public static function payment(): string {
        return sprintf('AFT-PAY-%s-%s', date('ymd'), self::token(6));
    }

    /**
     * Mint an id and guarantee it's unique against an existing column,
     * retrying on the (astronomically unlikely) collision. Falls back to the
     * raw generator when the DB is unreachable.
     *
     *   Ids::unique('summer_registrations', 'reg_code', fn() => Ids::summer())
     */
    public static function unique(string $table, string $column, callable $gen, int $tries = 6): string {
        if (!Database::available()) return $gen();
        for ($i = 0; $i < $tries; $i++) {
            $candidate = $gen();
            $hit = Database::scalar(
                "SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1",
                [$candidate]
            );
            if (!$hit) return $candidate;
        }
        // Final attempt: widen the entropy so we don't loop forever.
        return $gen() . '-' . self::token(3);
    }
}
