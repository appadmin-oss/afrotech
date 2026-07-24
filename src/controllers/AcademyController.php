<?php

class AcademyController extends Controller {
    public function index(): void {
        $this->view('pages/academy/index', [
            'title'   => 'Courses · ' . AFT_NAME,
            'courses' => Course::published(),
            'tracks'  => Track::all(),
        ], 'main');
    }

    public function course(string $slug): void {
        $course = Course::findBySlug($slug);
        if (!$course || ($course['status'] ?? 'published') !== 'published') { $this->notFound(); return; }
        $enrolled = false;
        if (StudentAuth::check() && !empty($course['id'])) {
            $enrolled = Enrollment::isEnrolled(StudentAuth::id(), (int)$course['id']);
        }
        $this->view('pages/academy/course', [
            'title'    => $course['title'] . ' · ' . AFT_NAME,
            'course'   => $course,
            'syllabus' => Course::syllabus($course),
            'track'    => $course['track_slug'] ? Track::find($course['track_slug']) : null,
            'enrolled' => $enrolled,
        ], 'main');
    }

    /** POST /api/enroll — enrol the signed-in learner in a course. */
    public function enroll(): void {
        Csrf::require();
        if (!StudentAuth::check()) {
            if ($this->wantsJson()) $this->json(['ok' => false, 'redirect' => url('/login')], 401);
            $this->redirect('/login');
            return;
        }
        $courseId = (int)$this->input('course_id', 0);
        $course = Course::find($courseId);
        if (!$course) {
            if ($this->wantsJson()) $this->json(['ok' => false, 'message' => 'Course not found.'], 404);
            $this->notFound();
            return;
        }
        Enrollment::enroll(StudentAuth::id(), $courseId);
        if ($this->wantsJson()) $this->json(['ok' => true, 'redirect' => url('/dashboard')]);
        $this->redirect('/dashboard');
    }
}
