<?php

/** Learner (student) authentication. */
class AuthController extends Controller {
    public function showLogin(): void {
        if (StudentAuth::check()) { $this->redirect('/dashboard'); return; }
        $this->view('pages/auth/login', [
            'title' => 'Student sign-in · ' . AFT_NAME,
            'error' => flash_pop('login_err'),
        ], 'main');
    }

    public function login(): void {
        Csrf::require();
        $email = (string)$this->input('email', '');
        $pass  = (string)$this->input('password', '');
        if (StudentAuth::attempt($email, $pass)) {
            $this->redirect('/dashboard');
            return;
        }
        flash_set('login_err', 'Those details did not match an active student account.');
        $this->redirect('/login');
    }

    public function logout(): void {
        Csrf::require();
        StudentAuth::logout();
        $this->redirect('/');
    }
}
