<?php

class StudentController extends Controller {
    public function index(): void {
        StudentAuth::require();
        $me = StudentAuth::user();
        $enrollments = Enrollment::forStudent((int)$me['id']);
        $this->view('pages/student/dashboard', [
            'title'       => 'My Learning · ' . AFT_NAME,
            'me'          => $me,
            'enrollments' => $enrollments,
            'suggested'   => array_slice(Course::published(), 0, 3),
        ], 'main');
    }
}
