<?php
/**
 * scripts/create-admin.php — create/update an operator with a role.
 *
 * Usage:
 *   php scripts/create-admin.php <username> <email> <password> [role]
 *
 * role ∈ super_admin | admin | registrar | instructor | viewer  (default: super_admin)
 * Re-running with an existing username updates the password + role.
 */
if (PHP_SAPI !== 'cli') { exit("CLI only.\n"); }
if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php <username> <email> <password> [role]\n");
    exit(1);
}
[$_, $username, $email, $password] = $argv;
$role = $argv[4] ?? 'super_admin';

if (strlen($password) < 8) { fwrite(STDERR, "Password must be at least 8 characters.\n"); exit(1); }

define('AFT_ROOT', dirname(__DIR__));
require AFT_ROOT . '/config/app.php';
require AFT_ROOT . '/src/core/Helpers.php';
require AFT_ROOT . '/src/core/Database.php';
require AFT_ROOT . '/src/core/Rbac.php';
require AFT_ROOT . '/src/core/Ids.php';

$role = Rbac::normalizeRole($role);

try {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $existing = Database::one('SELECT id FROM admin_users WHERE username = ? OR email = ?', [$username, $email]);
    if ($existing) {
        Database::exec('UPDATE admin_users SET password_hash = ?, role = ?, status = "active" WHERE id = ?',
            [$hash, $role, $existing['id']]);
        echo "Operator '{$username}' updated (role: {$role}).\n";
    } else {
        $staff = Ids::unique('admin_users', 'staff_id', fn() => Ids::staff());
        Database::insert(
            'INSERT INTO admin_users (staff_id, name, username, email, password_hash, role, status)
             VALUES (?,?,?,?,?,?, "active")',
            [$staff, $username, $username, $email, $hash, $role]
        );
        echo "Operator '{$username}' created — {$staff} (role: {$role}).\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(2);
}
