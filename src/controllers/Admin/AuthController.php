<?php
namespace Admin;

class AuthController extends \Controller {
    public function showLogin(): void {
        if (\Auth::check()) { $this->redirect('/admin'); return; }
        $this->view('admin/login', [
            'title' => 'Operator sign-in · ' . AFT_NAME,
            'error' => flash_pop('admin_err'),
        ], 'auth');
    }

    public function login(): void {
        \Csrf::require();
        $id   = (string)$this->input('identifier', '');
        $pass = (string)$this->input('password', '');
        if (\Auth::attempt($id, $pass)) {
            $next = flash_pop('admin_next');
            $this->redirect($next && str_starts_with($next, '/admin') ? $next : '/admin');
            return;
        }
        flash_set('admin_err', 'Invalid credentials, or the account is suspended.');
        $this->redirect('/admin/login');
    }

    public function logout(): void {
        \Csrf::require();
        \Auth::logout();
        $this->redirect('/admin/login');
    }
}
