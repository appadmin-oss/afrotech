<?php

/**
 * Auth — operator (admin) authentication. Distinct from StudentAuth, which
 * powers the public learner surface. The signed-in operator's role rides on
 * the session and drives Rbac checks.
 */
class Auth {
    public static function user(): ?array {
        return $_SESSION['admin'] ?? null;
    }

    public static function check(): bool {
        return !empty($_SESSION['admin']);
    }

    public static function id(): int {
        return (int)($_SESSION['admin']['id'] ?? 0);
    }

    public static function role(): string {
        return Rbac::normalizeRole($_SESSION['admin']['role'] ?? 'viewer');
    }

    public static function attempt(string $identifier, string $password): bool {
        if (!Database::available()) return false;
        // Accept username OR email as the login identifier.
        $row = Database::one(
            'SELECT * FROM admin_users WHERE (username = ? OR email = ?) LIMIT 1',
            [$identifier, $identifier]
        );
        if (!$row) return false;
        if (($row['status'] ?? 'active') !== 'active') return false;
        if (!password_verify($password, $row['password_hash'])) return false;

        $_SESSION['admin'] = [
            'id'       => (int)$row['id'],
            'staff_id' => $row['staff_id'] ?? '',
            'name'     => $row['name'] ?? $row['username'],
            'username' => $row['username'],
            'email'    => $row['email'] ?? '',
            'role'     => Rbac::normalizeRole($row['role'] ?? 'viewer'),
        ];
        session_regenerate_id(true);
        Database::exec('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [(int)$row['id']]);
        return true;
    }

    public static function logout(): void {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }

    public static function require(): void {
        if (!self::check()) {
            flash_set('admin_next', current_path());
            header('Location: ' . url('/admin/login'));
            exit;
        }
    }
}
