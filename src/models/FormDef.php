<?php

/**
 * FormDef — persistence for builder-authored forms.
 *
 * A form is identified by `form_key` and stored as a row per VERSION. Exactly
 * one row per key is `live`; there may be one `draft` (the working copy the
 * builder autosaves into) and any number of `archived` past versions. Editing
 * never touches what the public sees — publishing does, atomically, by
 * archiving the current live row and inserting a new one.
 *
 * Every submission records the version it was captured against, so an answer
 * set can always be replayed against the questions that were actually asked.
 *
 * When the DB is unreachable the seeded default is returned so /summer still
 * renders (the same soft-fallback posture as Track and Setting).
 */
class FormDef {

    public const SUMMER = 'summer-reg';

    /** Registry of every builder-editable form. */
    public static function registry(): array {
        return [
            self::SUMMER => [
                'name' => 'Summer school registration',
                'note' => 'The public /summer form. Drives the intake record, the confirmation email and the checkout amount.',
                'defaults' => 'summerDefaults',
            ],
        ];
    }

    public static function known(string $key): bool {
        return isset(self::registry()[$key]);
    }

    /* ── Reads ─────────────────────────────────────────────────────── */

    /**
     * The definition the public form renders from. Seeds the default version
     * on first call so a fresh install has a working form immediately.
     */
    public static function live(string $key = self::SUMMER): array {
        $row = self::row($key, 'live');
        if ($row) return $row;
        if (Database::available() && self::known($key)) {
            self::seed($key);
            $row = self::row($key, 'live');
            if ($row) return $row;
        }
        return self::fallback($key);
    }

    /** The working copy the builder edits — the live version until one exists. */
    public static function draft(string $key = self::SUMMER): array {
        $row = self::row($key, 'draft');
        if ($row) return $row;
        $live = self::live($key);
        $live['status'] = 'draft';
        return $live;
    }

    /** Version history, newest first. */
    public static function versions(string $key = self::SUMMER, int $limit = 30): array {
        if (!Database::available()) return [];
        try {
            return Database::all(
                "SELECT id, version, name, status, published_by, created_at
                   FROM form_defs WHERE form_key = ? AND status <> 'draft'
                  ORDER BY version DESC LIMIT " . max(1, min(100, $limit)),
                [$key]
            );
        } catch (Throwable $e) { return []; }
    }

    public static function findVersion(string $key, int $version): ?array {
        if (!Database::available()) return null;
        try {
            $row = Database::one("SELECT * FROM form_defs WHERE form_key = ? AND version = ? LIMIT 1", [$key, $version]);
        } catch (Throwable $e) { return null; }
        return $row ? self::hydrate($row) : null;
    }

    /* ── Writes ────────────────────────────────────────────────────── */

    /**
     * Save the builder's working copy. Draft rows are upserted in place — one
     * draft per form, so autosave never grows the table.
     */
    public static function saveDraft(string $key, array $fields, string $name, array $settings, int $adminId = 0): bool {
        if (!Database::available()) return false;
        $payload = [
            json_encode($fields,   JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            mb_substr($name, 0, 160),
            $adminId ?: null,
        ];
        $existing = self::row($key, 'draft');
        if ($existing) {
            Database::exec(
                "UPDATE form_defs SET fields_json = ?, settings_json = ?, name = ?, published_by = ?
                  WHERE form_key = ? AND status = 'draft'",
                array_merge($payload, [$key])
            );
            return true;
        }
        Database::insert(
            "INSERT INTO form_defs (form_key, version, name, fields_json, settings_json, status, published_by)
             VALUES (?, ?, ?, ?, ?, 'draft', ?)",
            [$key, self::nextVersion($key), $payload[2], $payload[0], $payload[1], $payload[3]]
        );
        return true;
    }

    /**
     * Promote a field list to live as a new version. The previous live row is
     * archived in the same transaction, so there is never a window with two
     * live definitions (or none).
     */
    public static function publish(string $key, array $fields, string $name, array $settings, int $adminId = 0): array {
        if (!Database::available()) return ['ok' => false, 'error' => 'db_unavailable'];
        $version = self::nextVersion($key);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::exec("UPDATE form_defs SET status = 'archived' WHERE form_key = ? AND status = 'live'", [$key]);
            Database::exec("DELETE FROM form_defs WHERE form_key = ? AND status = 'draft'", [$key]);
            Database::insert(
                "INSERT INTO form_defs (form_key, version, name, fields_json, settings_json, status, published_by)
                 VALUES (?, ?, ?, ?, ?, 'live', ?)",
                [
                    $key, $version, mb_substr($name, 0, 160),
                    json_encode($fields,   JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $adminId ?: null,
                ]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[aft/forms] publish failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'publish_failed'];
        }
        return ['ok' => true, 'version' => $version];
    }

    /** Copy an archived version forward as the new live one. */
    public static function rollback(string $key, int $version, int $adminId = 0): array {
        $old = self::findVersion($key, $version);
        if (!$old) return ['ok' => false, 'error' => 'unknown_version'];
        return self::publish($key, $old['fields'], (string)$old['name'], $old['settings'], $adminId);
    }

    public static function discardDraft(string $key): void {
        if (!Database::available()) return;
        Database::exec("DELETE FROM form_defs WHERE form_key = ? AND status = 'draft'", [$key]);
    }

    /* ── Defaults ──────────────────────────────────────────────────── */

    /**
     * The seeded summer form. It reproduces the fields the hard-coded page
     * always collected, and demonstrates each capability the builder exposes:
     * a page break, dynamic track options, conditional visibility, conditional
     * requirement, per-option pricing and a consent gate.
     */
    public static function summerDefaults(): array {
        return [
            ['type' => 'section', 'key' => 'sec_student', 'label' => 'The student',
             'help' => 'Who is coming to summer school?'],

            ['key' => 'student_name', 'type' => 'text', 'label' => "Student's full name",
             'required' => true, 'locked' => true, 'map' => 'student_name', 'width' => 'half',
             'placeholder' => 'First and last name', 'maxLen' => 160],

            ['key' => 'student_age', 'type' => 'number', 'label' => "Student's age",
             'required' => true, 'locked' => true, 'map' => 'student_age', 'width' => 'half',
             'min' => 7, 'max' => 19, 'help' => 'Age at the start of the cohort.'],

            ['key' => 'track_slug', 'type' => 'choice', 'label' => 'Choose a track',
             'required' => true, 'locked' => true, 'map' => 'track_slug', 'optionsFrom' => 'tracks',
             'help' => 'Four weeks, one track. Options come from Tracks in the console.'],

            ['key' => 'experience', 'type' => 'choice', 'label' => 'Experience with this track',
             'required' => true, 'map' => 'experience',
             'options' => [
                 ['value' => 'none',      'label' => 'Brand new — no experience'],
                 ['value' => 'some',      'label' => 'A little experience'],
                 ['value' => 'confident', 'label' => 'Confident — done some before'],
             ]],

            // Conditional visibility: only ask about prior work when there IS any.
            ['key' => 'experience_detail', 'type' => 'longtext', 'label' => 'What have they already built or learned?',
             'rows' => 3, 'placeholder' => 'A class, a project, a tutorial series…',
             'logic' => ['action' => 'show', 'match' => 'any', 'rules' => [
                 ['field' => 'experience', 'op' => 'in', 'value' => 'some, confident'],
             ]],
             // …and once shown, an answer is expected.
             'requiredIf' => ['action' => 'require', 'match' => 'any', 'rules' => [
                 ['field' => 'experience', 'op' => 'equals', 'value' => 'confident'],
             ]],
             'help' => 'Helps us place confident learners in the right group.'],

            ['type' => 'step', 'key' => 'step_2', 'label' => 'Guardian & logistics'],

            ['type' => 'section', 'key' => 'sec_guardian', 'label' => 'Parent / guardian'],

            ['key' => 'guardian_name', 'type' => 'text', 'label' => 'Parent / guardian name',
             'required' => true, 'locked' => true, 'map' => 'guardian_name', 'width' => 'half', 'maxLen' => 160],

            ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone / WhatsApp',
             'required' => true, 'locked' => true, 'map' => 'phone', 'width' => 'half',
             'placeholder' => '+234…'],

            ['key' => 'email', 'type' => 'email', 'label' => 'Email',
             'required' => true, 'locked' => true, 'map' => 'email',
             'help' => 'The confirmation and reference code go here.'],

            ['key' => 'location_pref', 'type' => 'choice', 'label' => 'Preferred campus',
             'required' => true, 'map' => 'location_pref',
             'options' => [
                 ['value' => 'Egbeda',        'label' => 'Egbeda — 2 Oremeji Street'],
                 ['value' => 'Ishefun',       'label' => 'Ishefun — 18 Camp Davis Road'],
                 ['value' => 'Online',        'label' => 'Online (live classes)'],
                 ['value' => 'No preference', 'label' => 'No preference'],
             ]],

            // Priced add-on, shown only to on-campus learners. Both rules are
            // needed: a negative test alone ("not Online") is also true of a
            // blank, which would offer the shuttle before a campus is picked.
            ['key' => 'transport', 'type' => 'yesno', 'label' => 'Do you need campus shuttle pick-up?',
             'price' => 5000, 'priceLabel' => 'Campus shuttle pick-up',
             'help' => 'Adds ₦5,000 to the fee — return trip, weekdays.',
             'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [
                 ['field' => 'location_pref', 'op' => 'not_empty'],
                 ['field' => 'location_pref', 'op' => 'not_in', 'value' => 'Online, No preference'],
             ]]],

            ['key' => 'extras', 'type' => 'multi', 'label' => 'Optional add-ons',
             'help' => 'Tick anything you want included. Priced per item.',
             'maxSelect' => 3,
             'options' => [
                 ['value' => 'laptop',    'label' => 'Laptop rental for the four weeks', 'price' => 15000],
                 ['value' => 'lunch',     'label' => 'Daily lunch plan',                 'price' => 12000],
                 ['value' => 'kit',       'label' => 'Branded starter kit',              'price' => 3500],
             ]],

            ['type' => 'step', 'key' => 'step_3', 'label' => 'Anything else'],

            ['key' => 'sibling', 'type' => 'yesno', 'label' => 'Is a sibling also registering?',
             'help' => "Tick yes and we'll apply the family rate at checkout."],

            ['key' => 'sibling_name', 'type' => 'text', 'label' => "Sibling's full name",
             'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [
                 ['field' => 'sibling', 'op' => 'checked'],
             ]],
             'requiredIf' => ['action' => 'require', 'match' => 'all', 'rules' => [
                 ['field' => 'sibling', 'op' => 'checked'],
             ]]],

            ['key' => 'notes', 'type' => 'longtext', 'label' => 'Anything we should know?',
             'map' => 'notes', 'rows' => 3,
             'placeholder' => 'Allergies, accessibility needs, questions…'],

            ['key' => 'medical', 'type' => 'longtext', 'label' => 'Medical or dietary notes',
             'sensitive' => true, 'rows' => 2,
             'help' => 'Seen by program staff only — never included in exports or emails.'],

            ['key' => 'referral', 'type' => 'dropdown', 'label' => 'How did you hear about us?',
             'options' => [
                 ['value' => 'friend',    'label' => 'From a friend or family member'],
                 ['value' => 'school',    'label' => 'Through a school'],
                 ['value' => 'instagram', 'label' => 'Instagram'],
                 ['value' => 'whatsapp',  'label' => 'WhatsApp'],
                 ['value' => 'search',    'label' => 'Google search'],
                 ['value' => 'other',     'label' => 'Somewhere else'],
             ]],

            ['key' => 'referral_detail', 'type' => 'text', 'label' => 'Tell us where',
             'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [
                 ['field' => 'referral', 'op' => 'equals', 'value' => 'other'],
             ]]],

            ['key' => 'utm_source', 'type' => 'hidden', 'label' => 'Campaign source',
             'prefill' => 'utm_source'],

            ['key' => 'consent', 'type' => 'consent', 'required' => true,
             'label' => "I confirm I'm the parent/guardian and consent to Afrotech Academy contacting me about this registration.",
             'requiredMessage' => 'We need your consent as the parent or guardian to continue.'],
        ];
    }

    public static function defaultSettings(): array {
        return FormEngine::normalizeSettings([
            'submitLabel'    => 'Continue to payment',
            'introTitle'     => 'Tell us about your child',
            'introBody'      => "Takes two minutes. You'll go straight to secure payment — apply any discount code there.",
            'successTitle'   => "You're registered! 🎉",
            'successMessage' => '',
            'showProgress'   => true,
            'showPrice'      => true,
        ]);
    }

    /* ── Internals ─────────────────────────────────────────────────── */

    private static function row(string $key, string $status): ?array {
        if (!Database::available()) return null;
        try {
            $row = Database::one(
                "SELECT * FROM form_defs WHERE form_key = ? AND status = ? ORDER BY version DESC LIMIT 1",
                [$key, $status]
            );
        } catch (Throwable $e) {
            // Table not migrated yet — the caller falls back to the seed.
            return null;
        }
        return $row ? self::hydrate($row) : null;
    }

    private static function hydrate(array $row): array {
        $fields   = json_decode((string)$row['fields_json'], true);
        $settings = json_decode((string)($row['settings_json'] ?? ''), true);
        $row['fields']   = is_array($fields) ? $fields : [];
        $row['settings'] = FormEngine::normalizeSettings(is_array($settings) ? $settings : []);
        $row['version']  = (int)$row['version'];
        return $row;
    }

    private static function nextVersion(string $key): int {
        try {
            $max = (int) Database::scalar("SELECT COALESCE(MAX(version), 0) FROM form_defs WHERE form_key = ?", [$key]);
        } catch (Throwable $e) { $max = 0; }
        return $max + 1;
    }

    private static function seed(string $key): void {
        $reg = self::registry()[$key] ?? null;
        if (!$reg) return;
        $fn = $reg['defaults'];
        $fields = self::$fn();
        $v = FormEngine::normalizeFields($fields);
        if (empty($v['ok'])) {   // a broken seed is a bug, not a runtime state
            error_log('[aft/forms] seed rejected: ' . ($v['detail'] ?? $v['error']));
            return;
        }
        try {
            Database::insert(
                "INSERT INTO form_defs (form_key, version, name, fields_json, settings_json, status, published_by)
                 VALUES (?, 1, ?, ?, ?, 'live', NULL)",
                [
                    $key, $reg['name'],
                    json_encode($v['fields'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    json_encode(self::defaultSettings(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (Throwable $e) {
            error_log('[aft/forms] seed insert failed: ' . $e->getMessage());
        }
    }

    /** In-memory definition used when the DB is down or unmigrated. */
    private static function fallback(string $key): array {
        $reg = self::registry()[$key] ?? ['name' => 'Form', 'defaults' => 'summerDefaults'];
        $fn = $reg['defaults'];
        $v = FormEngine::normalizeFields(self::$fn());
        return [
            'id' => 0, 'form_key' => $key, 'version' => 1,
            'name' => $reg['name'],
            'fields' => $v['fields'] ?? [],
            'settings' => self::defaultSettings(),
            'status' => 'live', 'published_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
