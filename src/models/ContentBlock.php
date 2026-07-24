<?php

/**
 * ContentBlock — editable landing-page copy. Reads fall back to hard-coded
 * defaults so pages render before anything is seeded.
 */
class ContentBlock {
    private static ?array $cache = null;

    private static function defaults(): array {
        return [
            'hero_eyebrow'  => 'Afrostrength Summer School · ' . AFT_SUMMER_AGE,
            'hero_title'    => 'Afrotech Academy',
            'hero_subtitle' => 'This summer school is your opportunity to build the skills that matter most for the future. Explore leadership, digital and tech skills, content creation, business, and financial literacy through engaging, practical learning designed to inspire confidence, creativity, and success.',
            'summer_intro'  => 'Six future-ready tracks. One transformative summer. Register your child today for the Afrotech Academy Summer School.',
        ];
    }

    public static function get(string $key, string $default = ''): string {
        if (self::$cache === null) self::load();
        return self::$cache[$key] ?? (self::defaults()[$key] ?? $default);
    }

    public static function all(): array {
        if (self::$cache === null) self::load();
        return array_merge(self::defaults(), self::$cache);
    }

    private static function load(): void {
        self::$cache = [];
        if (!Database::available()) return;
        foreach (Database::all("SELECT key_name, value_text FROM content_blocks") as $r) {
            self::$cache[$r['key_name']] = (string)$r['value_text'];
        }
    }

    public static function set(string $key, string $value): void {
        if (!Database::available()) return;
        Database::exec(
            "INSERT INTO content_blocks (key_name, value_text) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)",
            [$key, $value]
        );
        self::$cache = null;
    }
}
