<?php
namespace Admin;

class CoursesController extends \Controller {
    public function index(): void {
        \Rbac::require('courses.view');
        $this->view('admin/courses/index', [
            'title'   => 'Courses · ' . AFT_NAME . ' Ops',
            'courses' => \Course::all(),
        ], 'admin');
    }

    public function edit(string $id = ''): void {
        \Rbac::require('courses.manage');
        $course = $id ? \Course::find((int)$id) : null;
        $this->view('admin/courses/edit', [
            'title'  => ($course ? 'Edit course' : 'New course') . ' · ' . AFT_NAME . ' Ops',
            'course' => $course,
            'tracks' => \Track::options(),
        ], 'admin');
    }

    public function save(): void {
        \Csrf::require();
        \Rbac::require('courses.manage');
        $d = [
            'id'          => (int)$this->input('id', 0),
            'title'       => (string)$this->input('title', ''),
            'slug'        => (string)$this->input('slug', ''),
            'track_slug'  => (string)$this->input('track_slug', ''),
            'level'       => (string)$this->input('level', 'Beginner'),
            'age_range'   => (string)$this->input('age_range', '7+'),
            'weeks'       => (int)$this->input('weeks', 4),
            'instructor'  => (string)$this->input('instructor', ''),
            'price_naira' => (int)$this->input('price_naira', 40000),
            'summary'     => (string)$this->input('summary', ''),
            'body'        => (string)$this->input('body', ''),
            'syllabus_json' => (string)$this->input('syllabus_json', ''),
            'status'      => $this->input('status', 'draft') === 'published' ? 'published' : 'draft',
        ];
        if (trim($d['title']) === '') { $this->redirect('/admin/courses/new'); return; }
        \Course::save($d);
        $this->redirect('/admin/courses');
    }

    public function delete(string $id): void {
        \Csrf::require();
        \Rbac::require('courses.manage');
        \Course::delete((int)$id);
        $this->redirect('/admin/courses');
    }
}
