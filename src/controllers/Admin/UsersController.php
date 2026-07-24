<?php
namespace Admin;

class UsersController extends \Controller {
    public function index(): void {
        \Rbac::require('users.view');
        $this->view('admin/users/index', [
            'title'      => 'Operators · ' . AFT_NAME . ' Ops',
            'users'      => \AdminUser::all(),
            'canManage'  => \Rbac::can('users.manage'),
            'me'         => \Auth::user(),
            'saved'      => flash_pop('users_saved'),
            'error'      => flash_pop('users_err'),
        ], 'admin');
    }

    public function create(): void {
        \Rbac::require('users.manage');
        $this->view('admin/users/new', [
            'title'      => 'New operator · ' . AFT_NAME . ' Ops',
            'assignable' => \Rbac::assignableRoles(\Auth::role()),
            'error'      => flash_pop('users_err'),
            'old'        => $_SESSION['_old_user'] ?? [],
        ], 'admin');
        unset($_SESSION['_old_user']);
    }

    public function store(): void {
        \Csrf::require();
        \Rbac::require('users.manage');

        $d = [
            'name'     => (string)$this->input('name', ''),
            'username' => (string)$this->input('username', ''),
            'email'    => (string)$this->input('email', ''),
            'password' => (string)$this->input('password', ''),
            'role'     => (string)$this->input('role', 'viewer'),
        ];

        // Never let an operator mint an account more senior than themselves.
        if (!in_array(\Rbac::normalizeRole($d['role']), \Rbac::assignableRoles(\Auth::role()), true)) {
            flash_set('users_err', 'You cannot assign a role at or above your own.');
            $_SESSION['_old_user'] = $d;
            $this->redirect('/admin/users/new');
            return;
        }

        $v = new \Validator($d);
        $v->check('name', ['required', 'max:120'])
          ->check('username', ['required', 'min:3', 'max:80'])
          ->check('email', ['required', 'email'])
          ->check('password', ['required', 'min:8'], 'Password');
        if ($v->fails()) {
            flash_set('users_err', $v->first());
            $_SESSION['_old_user'] = $d;
            $this->redirect('/admin/users/new');
            return;
        }

        $res = \AdminUser::create($d, \Auth::id());
        if (!$res['ok']) {
            flash_set('users_err', $res['error'] ?? 'Could not create operator.');
            $_SESSION['_old_user'] = $d;
            $this->redirect('/admin/users/new');
            return;
        }
        \Mailer::sendTo($d['email'],
            'Your Afrotech Academy operator account',
            render_email('operator-welcome', $d + ['staff_id' => $res['staff_id']]));
        flash_set('users_saved', 'Operator ' . $d['name'] . ' created (' . $res['staff_id'] . ').');
        $this->redirect('/admin/users');
    }

    public function updateRole(string $id): void {
        \Csrf::require();
        \Rbac::require('users.manage');
        $target = \AdminUser::find((int)$id);
        if (!$target) { $this->redirect('/admin/users'); return; }
        $newRole = \Rbac::normalizeRole((string)$this->input('role', 'viewer'));

        if (!$this->guard($target, $newRole)) { $this->redirect('/admin/users'); return; }
        \AdminUser::updateRole((int)$id, $newRole);
        flash_set('users_saved', 'Role updated for ' . $target['name'] . '.');
        $this->redirect('/admin/users');
    }

    public function updateStatus(string $id): void {
        \Csrf::require();
        \Rbac::require('users.manage');
        $target = \AdminUser::find((int)$id);
        if (!$target) { $this->redirect('/admin/users'); return; }
        if ((int)$id === \Auth::id()) { flash_set('users_err', 'You cannot suspend your own account.'); $this->redirect('/admin/users'); return; }
        if (!$this->guard($target, $target['role'])) { $this->redirect('/admin/users'); return; }
        \AdminUser::setStatus((int)$id, (string)$this->input('status', 'active'));
        flash_set('users_saved', 'Status updated for ' . $target['name'] . '.');
        $this->redirect('/admin/users');
    }

    public function delete(string $id): void {
        \Csrf::require();
        \Rbac::require('users.manage');
        $target = \AdminUser::find((int)$id);
        if (!$target) { $this->redirect('/admin/users'); return; }
        if ((int)$id === \Auth::id()) { flash_set('users_err', 'You cannot delete your own account.'); $this->redirect('/admin/users'); return; }
        if (!$this->guard($target, $target['role'])) { $this->redirect('/admin/users'); return; }
        if ($this->isLastSuperAdmin($target)) { flash_set('users_err', 'You cannot remove the last super admin.'); $this->redirect('/admin/users'); return; }
        \AdminUser::delete((int)$id);
        flash_set('users_saved', 'Operator ' . $target['name'] . ' removed.');
        $this->redirect('/admin/users');
    }

    /** Shared seniority + last-super-admin guard. Sets a flash on refusal. */
    private function guard(array $target, string $intendedRole): bool {
        $actorRole = \Auth::role();
        if (!\Rbac::canManageRole($actorRole, $target['role'])) {
            flash_set('users_err', 'You cannot manage an operator at or above your own rank.');
            return false;
        }
        if (!in_array(\Rbac::normalizeRole($intendedRole), array_merge([$target['role']], \Rbac::assignableRoles($actorRole)), true)) {
            flash_set('users_err', 'You cannot assign a role at or above your own.');
            return false;
        }
        // Demoting the final super admin would lock everyone out of user mgmt.
        if ($target['role'] === 'super_admin' && $intendedRole !== 'super_admin' && $this->isLastSuperAdmin($target)) {
            flash_set('users_err', 'You cannot demote the last super admin.');
            return false;
        }
        return true;
    }

    private function isLastSuperAdmin(array $target): bool {
        if ($target['role'] !== 'super_admin') return false;
        $supers = array_filter(\AdminUser::all(), fn($u) => $u['role'] === 'super_admin' && $u['status'] === 'active');
        return count($supers) <= 1;
    }
}
