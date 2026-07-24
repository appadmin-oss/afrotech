<?php

/**
 * AdminUser — operator accounts for the RBAC console. Mints a staff id on
 * creation and enforces unique username/email.
 */
class AdminUser {
    public static function all(): array {
        if (!Database::available()) return [];
        return Database::all("SELECT * FROM admin_users ORDER BY FIELD(role,'super_admin','admin','registrar','instructor','viewer'), name");
    }

    public static function find(int $id): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM admin_users WHERE id = ?", [$id]);
    }

    public static function findByLogin(string $login): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1", [$login, $login]);
    }

    /** @return array{ok:bool,id?:int,staff_id?:string,error?:string} */
    public static function create(array $d, int $creatorId = 0): array {
        if (!Database::available()) return ['ok' => false, 'error' => 'Database unavailable.'];
        $username = strtolower(trim($d['username'] ?? ''));
        $email    = strtolower(trim($d['email'] ?? ''));
        if (self::findByLogin($username) || self::findByLogin($email)) {
            return ['ok' => false, 'error' => 'That username or email is already taken.'];
        }
        $staff = Ids::unique('admin_users', 'staff_id', fn() => Ids::staff());
        $id = (int) Database::insert(
            "INSERT INTO admin_users (staff_id, name, username, email, password_hash, role, status, created_by)
             VALUES (?,?,?,?,?,?, 'active', ?)",
            [$staff, $d['name'] ?? $username, $username, $email,
             password_hash($d['password'] ?? '', PASSWORD_BCRYPT),
             Rbac::normalizeRole($d['role'] ?? 'viewer'), $creatorId ?: null]
        );
        return ['ok' => true, 'id' => $id, 'staff_id' => $staff];
    }

    public static function updateRole(int $id, string $role): void {
        if (Database::available())
            Database::exec("UPDATE admin_users SET role = ? WHERE id = ?", [Rbac::normalizeRole($role), $id]);
    }

    public static function setStatus(int $id, string $status): void {
        if (!Database::available()) return;
        if (!in_array($status, ['active','suspended'], true)) return;
        Database::exec("UPDATE admin_users SET status = ? WHERE id = ?", [$status, $id]);
    }

    public static function setPassword(int $id, string $password): void {
        if (Database::available())
            Database::exec("UPDATE admin_users SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_BCRYPT), $id]);
    }

    public static function delete(int $id): void {
        if (Database::available()) Database::exec("DELETE FROM admin_users WHERE id = ?", [$id]);
    }

    public static function count(): int {
        if (!Database::available()) return 0;
        return (int) Database::scalar("SELECT COUNT(*) FROM admin_users");
    }
}
