<?php

/**
 * Rbac — role-based access control for the operator (admin) surface.
 *
 * A small, auditable permission model: every capability in the admin is a
 * dotted permission string (e.g. "registrations.manage"). Each role maps to
 * a set of permissions. The wildcard "*" grants everything (super_admin).
 *
 * Controllers guard themselves with Rbac::require('some.permission'); views
 * hide affordances with Rbac::can('some.permission'). The source of truth is
 * the role stored on the signed-in admin's session row.
 */
class Rbac {
    /** Ordered by seniority — used for the "cannot manage a peer/senior" rule. */
    public const ROLES = ['super_admin', 'admin', 'registrar', 'instructor', 'viewer'];

    public const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin'       => 'Administrator',
        'registrar'   => 'Registrar',
        'instructor'  => 'Instructor',
        'viewer'      => 'Viewer',
    ];

    public const ROLE_BLURB = [
        'super_admin' => 'Full control, including managing other operators and site settings.',
        'admin'       => 'Manage registrations, students, courses, and content. Cannot manage operators.',
        'registrar'   => 'Own the summer intake — review, confirm, and export registrations.',
        'instructor'  => 'Manage courses and view enrolled students.',
        'viewer'      => 'Read-only access across the admin.',
    ];

    /** All permissions the app knows about (used to render the matrix). */
    public const PERMISSIONS = [
        'dashboard.view'      => 'View the admin dashboard',
        'registrations.view'  => 'View summer registrations',
        'registrations.manage'=> 'Confirm / cancel / export registrations',
        'students.view'       => 'View student accounts',
        'students.manage'     => 'Edit / suspend student accounts',
        'courses.view'        => 'View courses',
        'courses.manage'      => 'Create / edit / publish courses',
        'content.manage'      => 'Edit landing-page content blocks',
        'forms.view'          => 'View the registration form builder',
        'forms.manage'        => 'Edit and publish the public registration form',
        'payments.view'       => 'View the payments ledger',
        'payments.confirm'    => 'Confirm a bank transfer by hand (sends the receipt)',
        'promotions.manage'   => 'Manage promotions & the announcement ribbon',
        'discounts.manage'    => 'Manage discount codes',
        'users.view'          => 'View operator accounts',
        'users.manage'        => 'Create / edit / remove operators',
        'settings.manage'     => 'Change program settings (fee, age, deadline…)',
    ];

    /** Role → permission grants. "*" means every permission. */
    private const GRANTS = [
        'super_admin' => ['*'],
        'admin' => [
            'dashboard.view',
            'registrations.view', 'registrations.manage',
            'students.view', 'students.manage',
            'courses.view', 'courses.manage',
            'content.manage',
            // Editing the live public form is an admin-and-above capability;
            // a registrar can read it to understand what a submission means.
            'forms.view', 'forms.manage',
            'payments.view', 'payments.confirm',
            'promotions.manage', 'discounts.manage',
            'settings.manage',
            'users.view',
        ],
        'registrar' => [
            'dashboard.view',
            'registrations.view', 'registrations.manage',
            'students.view',
            'courses.view',
            'forms.view',
            'payments.view', 'payments.confirm',
            'discounts.manage',
        ],
        'instructor' => [
            'dashboard.view',
            'courses.view', 'courses.manage',
            'students.view',
            'registrations.view',
        ],
        'viewer' => [
            'dashboard.view',
            'registrations.view', 'students.view', 'courses.view', 'users.view',
            'payments.view',
        ],
    ];

    public static function normalizeRole(?string $role): string {
        $role = (string)$role;
        return in_array($role, self::ROLES, true) ? $role : 'viewer';
    }

    /** Every permission a role holds (expands the "*" wildcard). */
    public static function permissionsFor(string $role): array {
        $role   = self::normalizeRole($role);
        $grants = self::GRANTS[$role] ?? [];
        if (in_array('*', $grants, true)) return array_keys(self::PERMISSIONS);
        return $grants;
    }

    /** Does the currently signed-in admin hold this permission? */
    public static function can(string $permission): bool {
        $user = Auth::user();
        if (!$user) return false;
        $grants = self::GRANTS[self::normalizeRole($user['role'] ?? '')] ?? [];
        return in_array('*', $grants, true) || in_array($permission, $grants, true);
    }

    /** Hard guard for controllers: 403 if the permission is missing. */
    public static function require(string $permission): void {
        Auth::require();
        if (self::can($permission)) return;
        http_response_code(403);
        (new Controller())->view('admin/forbidden', [
            'title'      => 'Access denied · ' . AFT_NAME,
            'permission' => $permission,
        ], 'admin');
        exit;
    }

    /** Seniority index — lower is more senior. Unknown roles sort last. */
    public static function rank(string $role): int {
        $i = array_search(self::normalizeRole($role), self::ROLES, true);
        return $i === false ? count(self::ROLES) : (int)$i;
    }

    /**
     * Can $actor manage (edit/delete) an account with $targetRole? An operator
     * may only manage roles strictly junior to their own — nobody edits a peer
     * or a senior, and only a super_admin can touch another super_admin.
     */
    public static function canManageRole(string $actorRole, string $targetRole): bool {
        $actorRole  = self::normalizeRole($actorRole);
        $targetRole = self::normalizeRole($targetRole);
        if ($actorRole === 'super_admin') return true;
        return self::rank($actorRole) < self::rank($targetRole);
    }

    /** Roles an operator is allowed to assign (never above their own rank). */
    public static function assignableRoles(string $actorRole): array {
        $actorRole = self::normalizeRole($actorRole);
        if ($actorRole === 'super_admin') return self::ROLES;
        return array_values(array_filter(self::ROLES, fn($r) =>
            self::rank($r) > self::rank($actorRole)));
    }

    public static function label(string $role): string {
        return self::ROLE_LABELS[self::normalizeRole($role)] ?? ucfirst($role);
    }
}
