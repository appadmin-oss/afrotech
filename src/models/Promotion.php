<?php

/**
 * Promotion — the site-wide announcement ribbon (optionally with a live
 * countdown). Only active, in-window promotions surface publicly.
 */
class Promotion {
    /** Seeded fallback so the ribbon still renders before the DB is set up. */
    public static function fallback(): array {
        return [
            'id' => 0,
            'title' => 'Summer School registration is open',
            'body'  => 'Save your child’s place before the deadline — limited seats per campus.',
            'badge' => 'NEW', 'tone' => 'red',
            'cta_label' => 'Register now', 'cta_href' => '/summer',
            'show_countdown' => Setting::deadline() ? 1 : 0,
            'starts_at' => null,
            'ends_at' => Setting::deadline() ? date('Y-m-d H:i:s', Setting::deadline()) : null,
        ];
    }

    /** The single promotion to show in the ribbon right now (or null). */
    public static function active(): ?array {
        if (!Database::available()) return Setting::deadlinePassed() ? null : self::fallback();
        try {
            return Database::one(
                "SELECT * FROM promotions
                  WHERE status='active'
                    AND (starts_at IS NULL OR starts_at <= NOW())
                    AND (ends_at   IS NULL OR ends_at   >= NOW())
                  ORDER BY sort ASC, id DESC LIMIT 1"
            );
        } catch (Throwable $e) { return null; }
    }

    public static function all(): array {
        if (!Database::available()) return [];
        try { return Database::all("SELECT * FROM promotions ORDER BY sort ASC, id DESC"); }
        catch (Throwable $e) { return []; }
    }

    public static function find(int $id): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM promotions WHERE id = ?", [$id]);
    }

    public static function save(array $d): int {
        $args = [
            $d['title'], $d['body'] ?: null, $d['badge'] ?: null,
            $d['cta_label'] ?: null, $d['cta_href'] ?: null,
            in_array($d['tone'] ?? 'red', ['red','ink','gold'], true) ? $d['tone'] : 'red',
            !empty($d['show_countdown']) ? 1 : 0,
            $d['starts_at'] ?: null, $d['ends_at'] ?: null, (int)($d['sort'] ?? 0),
            in_array($d['status'] ?? 'active', ['active','paused'], true) ? $d['status'] : 'active',
        ];
        if (!empty($d['id'])) {
            Database::exec(
                "UPDATE promotions SET title=?, body=?, badge=?, cta_label=?, cta_href=?, tone=?, show_countdown=?, starts_at=?, ends_at=?, sort=?, status=? WHERE id=?",
                array_merge($args, [(int)$d['id']])
            );
            return (int)$d['id'];
        }
        return (int) Database::insert(
            "INSERT INTO promotions (title, body, badge, cta_label, cta_href, tone, show_countdown, starts_at, ends_at, sort, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            $args
        );
    }

    public static function delete(int $id): void {
        if (Database::available()) Database::exec("DELETE FROM promotions WHERE id = ?", [$id]);
    }
}
