<?php

/**
 * StudentAuth — public learner sessions. Separate from operator Auth so a
 * learner can never inherit admin capabilities and vice versa.
 */
class StudentAuth {
    public static function user(): ?array {
        return $_SESSION['student'] ?? null;
    }
    public static function check(): bool {
        return !empty($_SESSION['student']);
    }
    public static function id(): int {
        return (int)($_SESSION['student']['id'] ?? 0);
    }

    public static function login(array $row): void {
        $_SESSION['student'] = [
            'id'         => (int)$row['id'],
            'student_id' => $row['student_id'] ?? '',
            'name'       => $row['name'],
            'email'      => $row['email'],
            'status'     => $row['status'] ?? 'active',
        ];
        session_regenerate_id(true);
    }

    public static function attempt(string $email, string $password): bool {
        $row = Student::findByEmail($email);
        if (!$row || empty($row['password_hash'])) return false;
        if (!password_verify($password, $row['password_hash'])) return false;
        self::login($row);
        return true;
    }

    public static function logout(): void {
        unset($_SESSION['student']);
        session_regenerate_id(true);
    }

    public static function require(): void {
        if (!self::check()) {
            header('Location: ' . url('/login'));
            exit;
        }
    }
}
