<?php

/**
 * SummerController — the public /summer intake.
 *
 * The form is not written here: it's whatever the live FormDef says it is.
 * This controller resolves that definition, renders it, and on POST replays
 * the same logic server-side before anything is stored. Nothing about the
 * submission is trusted — not which fields were required, not which were
 * asked, and not what the add-ons cost.
 */
class SummerController extends Controller {

    public function index(): void {
        $def = FormDef::live();
        $this->view('pages/summer', [
            'title'       => 'Summer School Registration · ' . AFT_NAME,
            'description' => 'Register your child for the Afrotech Academy Summer School. ' . Setting::money(Setting::fee()) . ', ' . Setting::get('age_label') . '. Cybersecurity, AI, coding, design & more.',
            'tracks'      => Track::all(),
            'content'     => ContentBlock::all(),
            'def'         => $def,
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

        if (Setting::deadlinePassed()) {
            $msg = 'Registration for this cohort has closed.';
            if ($this->wantsJson()) $this->json(['ok' => false, 'message' => $msg, 'errors' => []], 422);
            flash_set('summer_err', $msg);
            $this->redirect('/summer');
            return;
        }

        // Cheap bot filters before we touch the DB: a filled honeypot, or a
        // form "completed" faster than a human can read the first question.
        if (trim((string)$this->input('website_url', '')) !== '') {
            if ($this->wantsJson()) $this->json(['ok' => true, 'reg_code' => '', 'message' => 'Thanks — we have your details.']);
            $this->redirect('/summer');
            return;
        }
        $started = (int)$this->input('started_at', 0);
        if ($started > 0 && (time() - $started) < 3) {
            $msg = 'That was quick — please take a moment and submit again.';
            if ($this->wantsJson()) $this->json(['ok' => false, 'message' => $msg, 'errors' => []], 422);
            flash_set('summer_err', $msg);
            $this->redirect('/summer');
            return;
        }

        $def      = FormDef::live();
        $resolved = FormEngine::resolve($def['fields']);
        $posted   = $this->collect($resolved);

        $result = FormEngine::validateSubmission($resolved, $posted);
        if (empty($result['ok'])) {
            if ($this->wantsJson()) {
                $this->json([
                    'ok'      => false,
                    'errors'  => $result['errors'],
                    'message' => 'Please check the highlighted fields.',
                ], 422);
            }
            $this->view('pages/summer', [
                'title'   => 'Summer School Registration · ' . AFT_NAME,
                'tracks'  => Track::all(),
                'content' => ContentBlock::all(),
                'def'     => $def,
                'errors'  => $result['errors'],
                'old'     => $posted,
            ], 'main');
            return;
        }

        $mapped  = $result['mapped'];
        $answers = $result['answers'];
        $addons  = (int)$result['addons'];
        $fee     = Setting::fee();

        // Track name is denormalised onto the record so a renamed or retired
        // track doesn't rewrite history on old registrations.
        $trackOptions = Track::options();
        $trackSlug    = (string)($mapped['track_slug'] ?? '');

        $data = [
            'student_name'  => (string)($mapped['student_name'] ?? ''),
            'student_age'   => (int)($mapped['student_age'] ?? 0),
            'guardian_name' => (string)($mapped['guardian_name'] ?? ''),
            'email'         => (string)($mapped['email'] ?? ''),
            'phone'         => (string)($mapped['phone'] ?? ''),
            'track_slug'    => $trackSlug,
            'track_name'    => $trackOptions[$trackSlug] ?? null,
            'location_pref' => (string)($mapped['location_pref'] ?? ''),
            'experience'    => (string)($mapped['experience'] ?? 'none'),
            'notes'         => (string)($mapped['notes'] ?? ''),
            'answers'       => $answers,
            'form_version'  => (int)($def['version'] ?? 1),
            'fee_naira'     => $fee + $addons,
            'addons_naira'  => $addons,
        ];

        $created = SummerRegistration::create($data);
        $code    = $created['reg_code'];

        // Only non-sensitive answers travel in email — a field an operator
        // flagged sensitive stays inside the console.
        // `answer_rows` is the presented [label, value] list; the raw answer map
        // is dropped here so a template can't accidentally render field keys.
        $mailVars = array_merge($data, [
            'reg_code'    => $code,
            'answers'     => null,
            'answer_rows' => FormEngine::presentAnswers($resolved, $answers, false),
            'total'       => $fee + $addons,
            'addons'      => $addons,
        ]);

        Mailer::sendTo($data['email'],
            'Your Afrotech Academy Summer School registration (' . $code . ')',
            render_email('summer-confirmation', $mailVars),
            AFT_EMAIL);
        Mailer::send('New summer registration · ' . $code,
            render_email('summer-admin', $mailVars),
            $data['email']);

        $settings = $def['settings'];
        $msg = $settings['successMessage'] !== ''
            ? $settings['successMessage']
            : "We've received " . $data['student_name'] . "'s registration for the "
              . ($data['track_name'] ?? 'Summer School') . '. Continue to secure the place.';

        if ($this->wantsJson()) {
            $this->json([
                'ok'       => true,
                'reg_code' => $code,
                'redirect' => url('/summer/pay/' . $code),
                'title'    => $settings['successTitle'],
                'message'  => $msg,
            ]);
        }
        $this->redirect('/summer/pay/' . $code);
    }

    /**
     * Pull one raw value per field from the request. We read by field key
     * rather than iterating $_POST so an extra parameter nobody asked for
     * can never become an answer.
     */
    private function collect(array $resolved): array {
        $out = [];
        foreach ($resolved as $f) {
            if (in_array($f['type'], FormEngine::LAYOUT_TYPES, true)) continue;
            $out[$f['key']] = $this->input($f['key'], in_array($f['type'], FormEngine::MULTI_VALUE_TYPES, true) ? [] : '');
        }
        return $out;
    }
}
