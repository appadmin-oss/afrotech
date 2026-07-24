<?php
namespace Admin;

class StudentsController extends \Controller {
    public function index(): void {
        \Rbac::require('students.view');
        $this->view('admin/students/index', [
            'title'    => 'Students · ' . AFT_NAME . ' Ops',
            'students' => \Student::all(),
            'canManage'=> \Rbac::can('students.manage'),
        ], 'admin');
    }

    public function status(string $id): void {
        \Csrf::require();
        \Rbac::require('students.manage');
        \Student::setStatus((int)$id, (string)$this->input('status', 'active'));
        $this->redirect('/admin/students');
    }
}
