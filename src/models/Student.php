<?php

class Student {
    public static function findByEmail(string $email): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM students WHERE email = ?", [strtolower(trim($email))]);
    }

    public static function find(int $id): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM students WHERE id = ?", [$id]);
    }

    public static function all(): array {
        if (!Database::available()) return [];
        return Database::all("SELECT * FROM students ORDER BY created_at DESC");
    }

    /**
     * Create (or fetch existing) learner account. Mints a house student id.
     * Returns [id, student_id]. When the DB is down the row is logged so the
     * inbox still sees it and a phantom id is returned.
     */
    public static function create(array $d, string $passwordHash = ''): array {
        $email = strtolower(trim($d['email'] ?? ''));
        if (!Database::available()) {
            $dir = AFT_ROOT . '/storage';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents($dir . '/students.log', json_encode($d) . "\n", FILE_APPEND | LOCK_EX);
            return ['id' => 0, 'student_id' => Ids::student()];
        }
        $existing = self::findByEmail($email);
        if ($existing) return ['id' => (int)$existing['id'], 'student_id' => $existing['student_id']];

        $sid = Ids::unique('students', 'student_id', fn() => Ids::student());
        $id = (int) Database::insert(
            "INSERT INTO students (student_id, name, email, password_hash, phone, guardian_name, age, track_slug, status)
             VALUES (?,?,?,?,?,?,?,?, 'active')",
            [$sid, $d['name'] ?? '', $email, $passwordHash, $d['phone'] ?? null,
             $d['guardian_name'] ?? null, !empty($d['age']) ? (int)$d['age'] : null, $d['track_slug'] ?? null]
        );
        return ['id' => $id, 'student_id' => $sid];
    }

    public static function setStatus(int $id, string $status): void {
        if (Database::available())
            Database::exec("UPDATE students SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function count(): int {
        if (!Database::available()) return 0;
        return (int) Database::scalar("SELECT COUNT(*) FROM students");
    }
}
