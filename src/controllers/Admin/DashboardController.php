<?php
namespace Admin;

class DashboardController extends \Controller {
    public function index(): void {
        \Rbac::require('dashboard.view');
        $this->view('admin/dashboard', [
            'title'   => 'Dashboard · ' . AFT_NAME . ' Ops',
            'stats'   => \SummerRegistration::stats(),
            'recent'  => \SummerRegistration::recent(6),
            'counts'  => [
                'students' => \Student::count(),
                'courses'  => count(\Course::all()),
                'operators'=> \AdminUser::count(),
            ],
        ], 'admin');
    }
}
