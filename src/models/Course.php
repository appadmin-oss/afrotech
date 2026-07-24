<?php

class Course {
    /** Fallback catalog so /academy renders without a DB. */
    public static function fallback(): array {
        return [
            ['id'=>1,'title'=>'Cyber Safety Foundations','slug'=>'cyber-safety-foundations','track_slug'=>'cybersecurity','level'=>'Beginner','age_range'=>'7+','weeks'=>4,'instructor'=>'Afrotech Faculty','price_naira'=>40000,'status'=>'published','cover'=>null,
             'summary'=>'A hands-on introduction to staying safe, private, and smart online.',
             'body'=>'Young learners explore how the internet works, how attackers try to trick people, and the simple habits that keep them safe.',
             'syllabus_json'=>'[{"title":"Week 1 — How the internet works","desc":"Devices, networks, and what really happens when you click."},{"title":"Week 2 — Spotting scams","desc":"Phishing, fake links, and social-engineering red flags."},{"title":"Week 3 — Locking things down","desc":"Passwords, passphrases, and two-factor authentication."},{"title":"Week 4 — Your digital footprint","desc":"Privacy, oversharing, and a good online reputation."}]'],
            ['id'=>2,'title'=>'Coding Kickstart: Build Your First Website','slug'=>'coding-kickstart','track_slug'=>'coding','level'=>'Beginner','age_range'=>'9+','weeks'=>4,'instructor'=>'Afrotech Faculty','price_naira'=>40000,'status'=>'published','cover'=>null,
             'summary'=>'From zero to a real, live web page you built yourself.',
             'body'=>'Learners write their first HTML, style it with CSS, and add a spark of JavaScript.',
             'syllabus_json'=>'[{"title":"Week 1 — Structure with HTML","desc":"Headings, text, images, and links."},{"title":"Week 2 — Style with CSS","desc":"Colour, layout, and making it yours."},{"title":"Week 3 — A little JavaScript","desc":"Buttons that do things."},{"title":"Week 4 — Ship it","desc":"Polish and publish your first project."}]'],
            ['id'=>3,'title'=>'AI Explorers: Create with AI (Safely)','slug'=>'ai-explorers','track_slug'=>'ai-automations','level'=>'Beginner','age_range'=>'8+','weeks'=>4,'instructor'=>'Afrotech Faculty','price_naira'=>40000,'status'=>'published','cover'=>null,
             'summary'=>'Use AI tools to create, learn, and automate — the honest way.',
             'body'=>'A guided tour of everyday AI tools, how to prompt them well, and where the responsible limits are.',
             'syllabus_json'=>'[{"title":"Week 1 — Meet AI","desc":"What it can and cannot do."},{"title":"Week 2 — Prompting","desc":"Asking good questions."},{"title":"Week 3 — Create","desc":"Text, images, and a mini project."},{"title":"Week 4 — Use it right","desc":"Honesty, credit, and safety."}]'],
            ['id'=>4,'title'=>'Design Studio: Posters & Logos','slug'=>'design-studio','track_slug'=>'graphics-design','level'=>'Beginner','age_range'=>'8+','weeks'=>4,'instructor'=>'Afrotech Faculty','price_naira'=>40000,'status'=>'published','cover'=>null,
             'summary'=>'Design eye-catching graphics and start a mini portfolio.',
             'body'=>'Learners pick up core design principles and use friendly tools to produce posters, a logo, and social graphics.',
             'syllabus_json'=>'[{"title":"Week 1 — Design eyes","desc":"Colour, contrast, and balance."},{"title":"Week 2 — Tools","desc":"Canva and vector basics."},{"title":"Week 3 — Make a logo","desc":"From idea to finished mark."},{"title":"Week 4 — Portfolio","desc":"Pull your best work together."}]'],
        ];
    }

    public static function published(): array {
        if (!Database::available()) return self::fallback();
        $rows = Database::all("SELECT * FROM courses WHERE status='published' ORDER BY created_at DESC");
        return $rows ?: self::fallback();
    }

    public static function all(): array {
        if (!Database::available()) return self::fallback();
        return Database::all("SELECT * FROM courses ORDER BY created_at DESC");
    }

    public static function find(int $id): ?array {
        if (!Database::available()) {
            foreach (self::fallback() as $c) if ((int)$c['id'] === $id) return $c;
            return null;
        }
        return Database::one("SELECT * FROM courses WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array {
        if (!Database::available()) {
            foreach (self::fallback() as $c) if ($c['slug'] === $slug) return $c;
            return null;
        }
        return Database::one("SELECT * FROM courses WHERE slug = ?", [$slug]);
    }

    public static function syllabus(array $course): array {
        $d = json_decode((string)($course['syllabus_json'] ?? ''), true);
        return is_array($d) ? $d : [];
    }

    public static function save(array $d): int {
        $slug = $d['slug'] ?: slugify($d['title'] ?? 'course');
        if (!empty($d['id'])) {
            Database::exec(
                "UPDATE courses SET title=?, slug=?, track_slug=?, level=?, age_range=?, weeks=?, instructor=?, price_naira=?, summary=?, body=?, syllabus_json=?, status=? WHERE id=?",
                [$d['title'], $slug, $d['track_slug'] ?: null, $d['level'], $d['age_range'], (int)$d['weeks'],
                 $d['instructor'] ?: null, (int)$d['price_naira'], $d['summary'] ?: null, $d['body'] ?: null,
                 $d['syllabus_json'] ?: null, $d['status'], (int)$d['id']]
            );
            return (int)$d['id'];
        }
        return (int) Database::insert(
            "INSERT INTO courses (title, slug, track_slug, level, age_range, weeks, instructor, price_naira, summary, body, syllabus_json, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$d['title'], $slug, $d['track_slug'] ?: null, $d['level'], $d['age_range'], (int)$d['weeks'],
             $d['instructor'] ?: null, (int)$d['price_naira'], $d['summary'] ?: null, $d['body'] ?: null,
             $d['syllabus_json'] ?: null, $d['status']]
        );
    }

    public static function delete(int $id): void {
        if (Database::available()) Database::exec("DELETE FROM courses WHERE id = ?", [$id]);
    }
}
