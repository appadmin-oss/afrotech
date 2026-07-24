<?php

class SummerController extends Controller {
    public function index(): void {
        $this->view('pages/summer', [
            'title'       => 'Summer School Registration · ' . AFT_NAME,
            'description' => 'Register your child for the Afrotech Academy Summer School. ' . AFT_SUMMER_FEE . ', ' . AFT_SUMMER_AGE . '. Cybersecurity, AI, coding, design & more.',
            'tracks'      => Track::all(),
            'content'     => ContentBlock::all(),
            'bodyClass'   => 'page-summer',
        ], 'main');
    }

    public function track(string $slug): void {
        $track = Track::find($slug);
        if (!$track) { $this->notFound(); return; }
        $this->view('pages/summer-track', [
            'title'  => $track['name'] . ' · Summer School · ' . AFT_NAME,
            'track'  => $track,
            'tracks' => Track::all(),
        ], 'main');
    }

    /** POST /api/summer/register — the intake endpoint. */
    public function register(): void {
        Csrf::require();

        $data = [
            'student_name'  => $this->input('student_name', ''),
            'student_age'   => $this->input('student_age', ''),
            'guardian_name' => $this->input('guardian_name', ''),
            'email'         => $this->input('email', ''),
            'phone'         => $this->input('phone', ''),
            'track_slug'    => $this->input('track_slug', ''),
            'location_pref' => $this->input('location_pref', ''),
            'experience'    => $this->input('experience', 'none'),
            'notes'         => $this->input('notes', ''),
        ];

        $trackOptions = Track::options();
        $v = new Validator($data);
        $v->check('student_name',  ['required', 'max:160'], "Student's name")
          ->check('student_age',   ['required', 'int', 'min_num:5', 'max_num:19'], "Student's age")
          ->check('guardian_name', ['required', 'max:160'], "Parent/guardian name")
          ->check('email',         ['required', 'email'])
          ->check('phone',         ['required', 'phone'])
          ->check('track_slug',    ['required', 'in:' . implode(',', array_keys($trackOptions))], 'Track');

        if ($v->fails()) {
            if ($this->wantsJson()) $this->json(['ok' => false, 'errors' => $v->errors(), 'message' => 'Please check the highlighted fields.'], 422);
            $this->view('pages/summer', [
                'title' => 'Summer School Registration · ' . AFT_NAME,
                'tracks' => Track::all(), 'content' => ContentBlock::all(),
                'errors' => $v->errors(), 'old' => $data,
            ], 'main');
            return;
        }

        $data['track_name'] = $trackOptions[$data['track_slug']] ?? null;
        $result = SummerRegistration::create($data);
        $code   = $result['reg_code'];

        // Confirmation to the family + notification to the academy inbox.
        Mailer::sendTo($data['email'],
            'Your Afrotech Academy Summer School registration (' . $code . ')',
            render_email('summer-confirmation', $data + ['reg_code' => $code]),
            AFT_EMAIL);
        Mailer::send('New summer registration · ' . $code,
            render_email('summer-admin', $data + ['reg_code' => $code]),
            $data['email']);

        $msg = "We've received " . $data['student_name'] . "'s registration for the "
             . ($data['track_name'] ?? 'Summer School') . ". Save your reference code — "
             . "we'll email you next steps and payment details.";

        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'reg_code' => $code, 'title' => "You're registered! 🎉", 'message' => $msg]);
        }
        flash_set('summer_code', $code);
        flash_set('summer_msg', $msg);
        $this->view('pages/summer-done', [
            'title'   => 'Registration received · ' . AFT_NAME,
            'code'    => $code,
            'message' => $msg,
            'data'    => $data,
        ], 'main');
    }
}
