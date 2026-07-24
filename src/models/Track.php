<?php

/**
 * Track — the six flier programs. Falls back to a hard-coded catalog when
 * the DB is unreachable so the landing + summer pages always render.
 */
class Track {
    /** Canonical fallback list — mirrors database/seed.sql. */
    public static function fallback(): array {
        return [
            ['slug'=>'cybersecurity','name'=>'Cybersecurity','icon'=>'shield','sort'=>1,
             'summary'=>'Stay safe online and learn how the people who protect networks actually think.',
             'outcomes'=>['Spot phishing, scams, and unsafe links','Strong passwords & 2FA','Digital footprint & privacy basics','Intro to ethical hacking mindset']],
            ['slug'=>'google-workspace','name'=>'Google Workspace','icon'=>'grid','sort'=>2,
             'summary'=>'Get fluent in the tools schools and workplaces run on every day.',
             'outcomes'=>['Docs, Sheets & Slides','Gmail & Calendar like a pro','Drive organisation & sharing','Collaboration & version history']],
            ['slug'=>'ai-automations','name'=>'AI & Automations','icon'=>'spark','sort'=>3,
             'summary'=>'Use AI responsibly and automate the boring stuff.',
             'outcomes'=>['Prompting that works','AI image & text tools','No-code automations','Using AI honestly & safely']],
            ['slug'=>'coding','name'=>'Coding','icon'=>'code','sort'=>4,
             'summary'=>'Go from first line of code to a project you can show off.',
             'outcomes'=>['Web basics: HTML, CSS, JS','Logic & problem solving','Build a mini web project','Version control fundamentals']],
            ['slug'=>'graphics-design','name'=>'Graphics Design','icon'=>'pen','sort'=>5,
             'summary'=>'Design posters, logos, and social graphics that pop.',
             'outcomes'=>['Design principles & colour','Canva & vector basics','Logo & poster projects','Building a mini portfolio']],
            ['slug'=>'digital-marketing','name'=>'Digital Marketing','icon'=>'megaphone','sort'=>6,
             'summary'=>'Understand how brands grow online — and run a real mini campaign.',
             'outcomes'=>['Social media strategy','Content that converts','Basic analytics','Plan & run a mini campaign']],
        ];
    }

    public static function all(): array {
        if (!Database::available()) return self::fallback();
        $rows = Database::all("SELECT * FROM tracks WHERE status='active' ORDER BY sort, name");
        if (!$rows) return self::fallback();
        foreach ($rows as &$r) {
            $r['outcomes'] = self::decodeOutcomes($r['outcomes'] ?? null);
        }
        return $rows;
    }

    public static function find(string $slug): ?array {
        foreach (self::all() as $t) if ($t['slug'] === $slug) return $t;
        return null;
    }

    /** slug => name map, handy for <select> menus and validation. */
    public static function options(): array {
        $out = [];
        foreach (self::all() as $t) $out[$t['slug']] = $t['name'];
        return $out;
    }

    private static function decodeOutcomes($raw): array {
        if (is_array($raw)) return $raw;
        $d = json_decode((string)$raw, true);
        return is_array($d) ? $d : [];
    }
}
