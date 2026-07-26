<?php

/**
 * FormEngine — the logic-based form runtime behind /summer.
 *
 * A form is a versioned JSON document: an ordered list of fields, split into
 * steps by `step` markers, where any field can carry
 *
 *   • conditional VISIBILITY   — show/hide me when <rules> match
 *   • conditional REQUIREMENT  — only demand an answer when <rules> match
 *   • per-field VALIDATION     — length, range, selection count, pattern
 *   • PRICING                  — flat or per-option add-ons that move the fee
 *
 * Rules reference other fields by key, so a form is a small dependency graph.
 * Two properties matter and are the reason this file exists rather than a
 * sprinkle of ifs in the controller:
 *
 *  1. The SAME evaluation runs on the server. The browser hides a field for
 *     comfort; the server decides whether that field was ever askable. A
 *     hidden field is never required and its posted value is discarded, so a
 *     crafted POST can't smuggle answers past the logic (or the price).
 *
 *  2. Visibility is resolved in PASSES, not one shot. B can depend on A while
 *     C depends on B; a single pass would evaluate C against a value that is
 *     about to disappear. We iterate to a fixed point (and cap the loop, so a
 *     hand-edited cyclic definition degrades to "hidden" instead of hanging).
 *
 * Everything here is pure: no DB, no session, no globals. FormDef owns
 * persistence; this owns meaning. That's what makes it testable — see
 * scripts/test-form-engine.php.
 */
class FormEngine {

    /** Presentational blocks — they collect nothing and are never validated. */
    public const LAYOUT_TYPES = ['step', 'section', 'info', 'divider'];

    /** Every input type the builder can emit. */
    public const INPUT_TYPES = [
        'text', 'longtext', 'email', 'phone', 'url', 'number',
        'date', 'time', 'choice', 'dropdown', 'multi', 'yesno',
        'consent', 'scale', 'country', 'file', 'hidden',
    ];

    /** Types whose answer is a list rather than a scalar. */
    public const MULTI_VALUE_TYPES = ['multi'];

    /** Types that carry an option list. */
    public const OPTION_TYPES = ['choice', 'dropdown', 'multi'];

    /** Human labels for the builder's type picker. */
    public const TYPE_LABELS = [
        'step'     => 'Page break',
        'section'  => 'Section heading',
        'info'     => 'Info text',
        'divider'  => 'Divider',
        'text'     => 'Short text',
        'longtext' => 'Paragraph',
        'email'    => 'Email',
        'phone'    => 'Phone',
        'url'      => 'Website / link',
        'number'   => 'Number',
        'date'     => 'Date',
        'time'     => 'Time',
        'choice'   => 'Choice (radio)',
        'dropdown' => 'Choice (dropdown)',
        'multi'    => 'Multi-select',
        'yesno'    => 'Yes / No',
        'consent'  => 'Consent checkbox',
        'scale'    => 'Scale 1–10',
        'country'  => 'Country',
        'file'     => 'File upload',
        'hidden'   => 'Hidden / captured',
    ];

    /**
     * Comparison operators available to logic rules. `needsValue` drives the
     * builder UI (an "is empty" rule has nothing to type into).
     */
    public const OPERATORS = [
        'equals'       => ['label' => 'is',                  'needsValue' => true],
        'not_equals'   => ['label' => 'is not',              'needsValue' => true],
        'contains'     => ['label' => 'contains',            'needsValue' => true],
        'not_contains' => ['label' => "doesn't contain",     'needsValue' => true],
        'starts_with'  => ['label' => 'starts with',         'needsValue' => true],
        'ends_with'    => ['label' => 'ends with',           'needsValue' => true],
        'gt'           => ['label' => 'is greater than',     'needsValue' => true],
        'gte'          => ['label' => 'is at least',         'needsValue' => true],
        'lt'           => ['label' => 'is less than',        'needsValue' => true],
        'lte'          => ['label' => 'is at most',          'needsValue' => true],
        'between'      => ['label' => 'is between (a,b)',    'needsValue' => true],
        'in'           => ['label' => 'is one of (a,b,c)',   'needsValue' => true],
        'not_in'       => ['label' => 'is none of (a,b,c)',  'needsValue' => true],
        'includes'     => ['label' => 'includes the option', 'needsValue' => true],
        'empty'        => ['label' => 'is blank',            'needsValue' => false],
        'not_empty'    => ['label' => 'has any answer',      'needsValue' => false],
        'checked'      => ['label' => 'is ticked / yes',     'needsValue' => false],
        'unchecked'    => ['label' => 'is unticked / no',    'needsValue' => false],
    ];

    /** Option lists the builder can bind to live app data instead of literals. */
    public const OPTION_SOURCES = [
        'tracks'   => 'Program tracks (from Tracks)',
        'campuses' => 'Campuses (from Settings)',
    ];

    /** Registration columns a field may write into. Everything else is an answer. */
    public const MAPPABLE = [
        'student_name'  => "Student's full name",
        'student_age'   => "Student's age",
        'guardian_name' => 'Parent / guardian name',
        'email'         => 'Email',
        'phone'         => 'Phone',
        'track_slug'    => 'Track',
        'location_pref' => 'Preferred campus',
        'experience'    => 'Experience level',
        'notes'         => 'Notes',
    ];

    /** Fields the summer pipeline cannot run without — locked in the builder. */
    public const REQUIRED_MAPS = ['student_name', 'student_age', 'guardian_name', 'email', 'phone', 'track_slug'];

    public const MAX_FIELDS  = 120;
    public const MAX_OPTIONS = 60;
    public const MAX_RULES   = 12;
    /** Visibility fixed-point cap. Deep chains resolve well inside this. */
    public const MAX_PASSES  = 12;

    /* ─────────────────────────────────────────────────────────────────
       Definition normalisation
       ───────────────────────────────────────────────────────────────── */

    /**
     * Clean and validate a builder-submitted field list.
     *
     * Returns ['ok' => true, 'fields' => [...]] or
     *         ['ok' => false, 'error' => <code>, 'detail' => <string>].
     *
     * Anything unrecognised is dropped rather than trusted: this is the only
     * door between operator input and the live public form.
     */
    public static function normalizeFields(array $raw): array {
        $out = [];
        $keys = [];

        foreach ($raw as $i => $f) {
            if (!is_array($f)) return self::defErr('bad_field', 'Field #' . ($i + 1) . ' is malformed.');
            $type = (string)($f['type'] ?? 'text');
            if (!in_array($type, array_merge(self::LAYOUT_TYPES, self::INPUT_TYPES), true)) {
                return self::defErr('bad_field_type', 'Unknown field type "' . $type . '".');
            }

            $isLayout = in_array($type, self::LAYOUT_TYPES, true);
            $key = self::cleanKey((string)($f['key'] ?? ''));
            if ($key === '') $key = self::keyFromLabel((string)($f['label'] ?? ''), $type, $i);
            if (!$isLayout) {
                if (strlen($key) > 40) return self::defErr('bad_field_key', 'Key "' . $key . '" is too long (max 40).');
                if (isset($keys[$key])) return self::defErr('duplicate_field_key', 'Two fields share the key "' . $key . '".');
                $keys[$key] = true;
            }

            $clean = [
                'key'   => $key,
                'type'  => $type,
                'label' => mb_substr(trim((string)($f['label'] ?? '')), 0, 160),
            ];
            if ($clean['label'] === '') $clean['label'] = $isLayout ? '' : ucfirst(str_replace('_', ' ', $key));

            foreach (['help', 'placeholder', 'requiredMessage', 'patternMessage'] as $k) {
                $v = trim((string)($f[$k] ?? ''));
                if ($v !== '') $clean[$k] = mb_substr($v, 0, 240);
            }

            if (!$isLayout) {
                if (!empty($f['required']))  $clean['required']  = true;
                if (!empty($f['sensitive'])) $clean['sensitive'] = true;
                if (!empty($f['locked']))    $clean['locked']    = true;
                if (($f['width'] ?? '') === 'half') $clean['width'] = 'half';

                $map = (string)($f['map'] ?? '');
                if ($map !== '' && isset(self::MAPPABLE[$map])) $clean['map'] = $map;

                $default = $f['default'] ?? '';
                if (is_string($default) && trim($default) !== '') $clean['default'] = mb_substr(trim($default), 0, 200);

                $prefill = self::cleanKey((string)($f['prefill'] ?? ''));
                if ($prefill !== '') $clean['prefill'] = substr($prefill, 0, 40);
            }

            // ---- options ------------------------------------------------
            if (in_array($type, self::OPTION_TYPES, true)) {
                $src = (string)($f['optionsFrom'] ?? '');
                if ($src !== '' && isset(self::OPTION_SOURCES[$src])) {
                    $clean['optionsFrom'] = $src;
                } else {
                    $opts = self::normalizeOptions($f['options'] ?? []);
                    if (!$opts) return self::defErr('choice_needs_options', '"' . $clean['label'] . '" needs at least one option.');
                    $clean['options'] = $opts;
                }
            }

            // ---- per-type validation knobs ------------------------------
            if ($type === 'number' || $type === 'scale') {
                if (self::isNum($f['min'] ?? null)) $clean['min'] = (int)$f['min'];
                if (self::isNum($f['max'] ?? null)) $clean['max'] = (int)$f['max'];
                if (isset($clean['min'], $clean['max']) && $clean['min'] > $clean['max']) {
                    return self::defErr('bad_range', '"' . $clean['label'] . '" has a minimum above its maximum.');
                }
            }
            if (in_array($type, ['text', 'longtext', 'url'], true)) {
                if (self::isNum($f['minLen'] ?? null)) $clean['minLen'] = max(0, (int)$f['minLen']);
                if (self::isNum($f['maxLen'] ?? null)) $clean['maxLen'] = max(1, (int)$f['maxLen']);
            }
            if ($type === 'multi') {
                if (self::isNum($f['minSelect'] ?? null)) $clean['minSelect'] = max(0, (int)$f['minSelect']);
                if (self::isNum($f['maxSelect'] ?? null)) $clean['maxSelect'] = max(1, (int)$f['maxSelect']);
            }
            if ($type === 'longtext' && self::isNum($f['rows'] ?? null)) {
                $clean['rows'] = max(2, min(16, (int)$f['rows']));
            }
            if ($type === 'file') {
                $accept = preg_replace('/[^a-zA-Z0-9\.\,\/\-\+]/', '', (string)($f['accept'] ?? ''));
                if ($accept !== '') $clean['accept'] = substr($accept, 0, 120);
            }
            if (in_array($type, ['text', 'phone', 'url'], true)) {
                $pattern = trim((string)($f['pattern'] ?? ''));
                if ($pattern !== '') {
                    if (!self::patternUsable($pattern)) {
                        return self::defErr('bad_pattern', 'The pattern on "' . $clean['label'] . '" is not a valid expression.');
                    }
                    $clean['pattern'] = mb_substr($pattern, 0, 200);
                }
            }

            // ---- pricing ------------------------------------------------
            if (!$isLayout && self::isNum($f['price'] ?? null) && (int)$f['price'] !== 0) {
                $clean['price'] = (int)$f['price'];
                // A question makes a poor line item — "Do you need campus shuttle
                // pick-up? …₦5,000" on a receipt reads as a typo. Operators can
                // name the thing being sold separately from the way they ask about it.
                $pl = trim((string)($f['priceLabel'] ?? ''));
                if ($pl !== '') $clean['priceLabel'] = mb_substr($pl, 0, 120);
            }

            // ---- logic --------------------------------------------------
            $logic = self::normalizeLogic($f['logic'] ?? null, ['show', 'hide']);
            if ($logic !== null) $clean['logic'] = $logic;
            $reqIf = self::normalizeLogic($f['requiredIf'] ?? null, ['require', 'optional']);
            if ($reqIf !== null) $clean['requiredIf'] = $reqIf;

            $out[] = $clean;
        }

        if (count($out) > self::MAX_FIELDS) {
            return self::defErr('too_many_fields', 'A form is capped at ' . self::MAX_FIELDS . ' blocks.');
        }

        // A rule may only reference a field that appears EARLIER in the form.
        // Forward references can't be answered yet, so they'd evaluate against
        // a permanent blank and silently hide half the form.
        $seen = [];
        foreach ($out as $f) {
            foreach (['logic', 'requiredIf'] as $slot) {
                foreach ($f[$slot]['rules'] ?? [] as $r) {
                    if (!isset($seen[$r['field']])) {
                        return self::defErr('rule_forward_reference',
                            '"' . $f['label'] . '" has a rule about "' . $r['field'] . '", which comes later in the form (or does not exist).');
                    }
                }
            }
            if ($f['key'] !== '' && !in_array($f['type'], self::LAYOUT_TYPES, true)) $seen[$f['key']] = true;
        }

        // The intake pipeline needs these columns filled by SOME field.
        $mapped = [];
        foreach ($out as $f) if (!empty($f['map'])) $mapped[$f['map']] = true;
        foreach (self::REQUIRED_MAPS as $need) {
            if (!isset($mapped[$need])) {
                return self::defErr('missing_core_field',
                    'A field mapped to "' . (self::MAPPABLE[$need] ?? $need) . '" is required — the registration record needs it.');
            }
        }
        // Two fields writing the same column is ambiguous; last-write-wins is
        // not a behaviour worth shipping.
        $dupes = [];
        foreach ($out as $f) {
            if (empty($f['map'])) continue;
            if (isset($dupes[$f['map']])) {
                return self::defErr('duplicate_map', 'Two fields both save to "' . self::MAPPABLE[$f['map']] . '".');
            }
            $dupes[$f['map']] = true;
        }

        return ['ok' => true, 'fields' => $out];
    }

    /** Normalise the form-level settings blob. */
    public static function normalizeSettings($raw): array {
        $s = is_array($raw) ? $raw : [];
        $out = [
            'submitLabel'    => mb_substr(trim((string)($s['submitLabel'] ?? '')) ?: 'Continue to payment', 0, 80),
            'successTitle'   => mb_substr(trim((string)($s['successTitle'] ?? '')) ?: "You're registered! 🎉", 0, 120),
            'successMessage' => mb_substr(trim((string)($s['successMessage'] ?? '')), 0, 400),
            'closedMessage'  => mb_substr(trim((string)($s['closedMessage'] ?? '')), 0, 400),
            'introTitle'     => mb_substr(trim((string)($s['introTitle'] ?? '')) ?: 'Tell us about your child', 0, 120),
            'introBody'      => mb_substr(trim((string)($s['introBody'] ?? '')), 0, 400),
            'showProgress'   => !isset($s['showProgress']) || !empty($s['showProgress']),
            'showPrice'      => !isset($s['showPrice']) || !empty($s['showPrice']),
            'reviewStep'     => !empty($s['reviewStep']),
        ];
        return $out;
    }

    /* ─────────────────────────────────────────────────────────────────
       Runtime shaping
       ───────────────────────────────────────────────────────────────── */

    /**
     * Expand dynamic option sources and stamp a step index on every field.
     * Call once per request; both the renderer and the validator work on the
     * resolved list so the two can never disagree about what was asked.
     */
    public static function resolve(array $fields, ?array $sources = null): array {
        $sources = $sources ?? self::sources();
        $step = 0;
        $out  = [];
        foreach ($fields as $f) {
            if ($f['type'] === 'step') { $step++; continue; }   // markers become boundaries
            if (!empty($f['optionsFrom'])) {
                $f['options'] = $sources[$f['optionsFrom']] ?? [];
                if (!$f['options']) $f['options'] = [['value' => '', 'label' => '— none configured —']];
            }
            $f['step'] = $step;
            $out[] = $f;
        }
        return $out;
    }

    /** How many steps a resolved field list spans (always ≥ 1). */
    public static function stepCount(array $resolved): int {
        $max = 0;
        foreach ($resolved as $f) $max = max($max, (int)($f['step'] ?? 0));
        return $max + 1;
    }

    /** Live option lists for the dynamic sources. */
    public static function sources(): array {
        $tracks = [];
        foreach (Track::all() as $t) {
            $tracks[] = ['value' => (string)$t['slug'], 'label' => (string)$t['name']];
        }
        $campuses = [];
        foreach (preg_split('/\r?\n/', (string)Setting::get('campuses', '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // "Egbeda | Egbeda — 2 Oremeji Street" → value | label
            $parts = array_map('trim', explode('|', $line, 2));
            $campuses[] = ['value' => $parts[0], 'label' => $parts[1] ?? $parts[0]];
        }
        return ['tracks' => $tracks, 'campuses' => $campuses];
    }

    /* ─────────────────────────────────────────────────────────────────
       Logic evaluation
       ───────────────────────────────────────────────────────────────── */

    /**
     * Resolve which fields are visible for a given answer set.
     *
     * Iterates to a fixed point: a rule that points at a field which has
     * itself become hidden evaluates against a blank, which can hide further
     * fields, and so on. Returns key => bool.
     */
    public static function visibility(array $resolved, array $data): array {
        $vis = [];
        foreach ($resolved as $f) $vis[$f['key']] = true;

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $changed = false;
            foreach ($resolved as $f) {
                if (empty($f['logic'])) continue;
                $matched = self::matches($f['logic'], $data, $vis);
                $show = ($f['logic']['action'] === 'show') ? $matched : !$matched;
                if ($vis[$f['key']] !== $show) { $vis[$f['key']] = $show; $changed = true; }
            }
            if (!$changed) return $vis;
        }
        return $vis;   // cap hit (cyclic definition) — last state stands
    }

    /** Is this field required, given the answers so far? */
    public static function isRequired(array $field, array $data, array $vis): bool {
        if (!empty($field['requiredIf'])) {
            $matched = self::matches($field['requiredIf'], $data, $vis);
            return $field['requiredIf']['action'] === 'require' ? $matched : !$matched;
        }
        return !empty($field['required']);
    }

    /** Evaluate a rule group (all/any) against the answers. */
    public static function matches(array $logic, array $data, array $vis = []): bool {
        $rules = $logic['rules'] ?? [];
        if (!$rules) return true;
        $all = ($logic['match'] ?? 'all') === 'all';
        foreach ($rules as $r) {
            // A rule about an invisible field can never be satisfied — that
            // field was never asked, so treating its blank as a real answer
            // would leak hidden branches back into view.
            $ok = (isset($vis[$r['field']]) && $vis[$r['field']] === false)
                ? false
                : self::compare($data[$r['field']] ?? null, $r['op'], $r['value'] ?? '');
            if ($all && !$ok) return false;
            if (!$all && $ok) return true;
        }
        return $all;
    }

    /** One operator, one comparison. Loose by design: form values are strings. */
    public static function compare($actual, string $op, $expected): bool {
        $list = is_array($actual) ? array_map('strval', $actual) : null;
        $a    = $list === null ? trim((string)($actual ?? '')) : implode(', ', $list);
        $e    = trim((string)$expected);
        $lcA  = mb_strtolower($a);
        $lcE  = mb_strtolower($e);
        $isEmpty = $list === null ? ($a === '') : (count($list) === 0);

        switch ($op) {
            case 'equals':       return $list !== null ? in_array($lcE, array_map('mb_strtolower', $list), true) : $lcA === $lcE;
            case 'not_equals':   return !self::compare($actual, 'equals', $expected);
            case 'contains':     return $lcE !== '' && str_contains($lcA, $lcE);
            case 'not_contains': return !($lcE !== '' && str_contains($lcA, $lcE));
            case 'starts_with':  return $lcE !== '' && str_starts_with($lcA, $lcE);
            case 'ends_with':    return $lcE !== '' && str_ends_with($lcA, $lcE);
            case 'gt':           return self::isNum($a) && self::isNum($e) && (float)$a >  (float)$e;
            case 'gte':          return self::isNum($a) && self::isNum($e) && (float)$a >= (float)$e;
            case 'lt':           return self::isNum($a) && self::isNum($e) && (float)$a <  (float)$e;
            case 'lte':          return self::isNum($a) && self::isNum($e) && (float)$a <= (float)$e;
            case 'between':
                $b = array_map('trim', explode(',', $e));
                if (count($b) < 2 || !self::isNum($a) || !self::isNum($b[0]) || !self::isNum($b[1])) return false;
                $lo = min((float)$b[0], (float)$b[1]); $hi = max((float)$b[0], (float)$b[1]);
                return (float)$a >= $lo && (float)$a <= $hi;
            case 'in':
            case 'not_in':
                $set = array_filter(array_map(fn($x) => mb_strtolower(trim($x)), explode(',', $e)), fn($x) => $x !== '');
                $hit = $list !== null
                    ? (bool)array_intersect(array_map('mb_strtolower', $list), $set)
                    : in_array($lcA, $set, true);
                return $op === 'in' ? $hit : !$hit;
            case 'includes':     return $list !== null ? in_array($lcE, array_map('mb_strtolower', $list), true) : $lcA === $lcE;
            case 'empty':        return $isEmpty;
            case 'not_empty':    return !$isEmpty;
            case 'checked':      return in_array($lcA, ['1', 'yes', 'true', 'on'], true) || (!$isEmpty && $list !== null);
            case 'unchecked':    return !self::compare($actual, 'checked', '');
        }
        return false;
    }

    /* ─────────────────────────────────────────────────────────────────
       Submission validation
       ───────────────────────────────────────────────────────────────── */

    /**
     * Validate a submission against a resolved field list.
     *
     * Returns:
     *   ok        bool
     *   errors    key => message   (only when !ok)
     *   answers   key => value     for every visible, answered field
     *   mapped    column => value  for fields carrying a `map`
     *   addons    int              priced add-ons the answers selected
     *   hidden    key[]            fields the logic ruled out
     */
    public static function validateSubmission(array $resolved, array $data): array {
        $vis     = self::visibility($resolved, $data);
        $errors  = [];
        $answers = [];
        $mapped  = [];
        $hidden  = [];

        foreach ($resolved as $f) {
            $k = $f['key'];
            if (in_array($f['type'], self::LAYOUT_TYPES, true)) continue;
            if (empty($vis[$k])) { $hidden[] = $k; continue; }

            $v = self::coerce($f, $data[$k] ?? null);
            $isEmpty = is_array($v) ? !count($v) : ($v === '');

            if ($isEmpty) {
                if (self::isRequired($f, $data, $vis)) {
                    $errors[$k] = $f['requiredMessage'] ?? (self::labelOf($f) . ' is required.');
                }
                continue;
            }

            $err = self::validateValue($f, $v);
            if ($err !== null) { $errors[$k] = $err; continue; }

            $answers[$k] = $v;
            if (!empty($f['map'])) $mapped[$f['map']] = is_array($v) ? implode(', ', $v) : $v;
        }

        if ($errors) return ['ok' => false, 'errors' => $errors, 'hidden' => $hidden];

        return [
            'ok'      => true,
            'errors'  => [],
            'answers' => $answers,
            'mapped'  => $mapped,
            'addons'  => self::addons($resolved, $answers, $vis),
            'hidden'  => $hidden,
        ];
    }

    /** Cast a raw posted value into the field's shape, with hard length caps. */
    public static function coerce(array $f, $raw) {
        if (in_array($f['type'], self::MULTI_VALUE_TYPES, true)) {
            $list = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw]);
            $vals = array_values(array_unique(array_filter(array_map(
                fn($x) => mb_substr(trim((string)$x), 0, 200), $list
            ), fn($x) => $x !== '')));
            // Only options that actually exist survive.
            $allowed = self::optionValues($f);
            return $allowed ? array_values(array_intersect($vals, $allowed)) : $vals;
        }
        if (is_array($raw)) $raw = reset($raw) ?: '';
        $v = trim((string)($raw ?? ''));
        $cap = $f['type'] === 'longtext' ? 4000 : 300;
        $v = mb_substr($v, 0, $cap);
        if (in_array($f['type'], ['consent', 'yesno'], true)) {
            if ($v === '') return '';
            return in_array(mb_strtolower($v), ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
        }
        if ($f['type'] === 'email') $v = mb_strtolower($v);
        return $v;
    }

    /** Type + rule validation for a non-empty value. Returns null when fine. */
    public static function validateValue(array $f, $v): ?string {
        $label = self::labelOf($f);
        $scalar = is_array($v) ? '' : (string)$v;

        switch ($f['type']) {
            case 'email':
                if (!filter_var($scalar, FILTER_VALIDATE_EMAIL)) return "That email doesn't look right.";
                break;
            case 'phone':
                if (!preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $scalar)) return "That phone number doesn't look right.";
                break;
            case 'url':
                if (!filter_var($scalar, FILTER_VALIDATE_URL)) return 'Enter a full link, starting with https://';
                break;
            case 'number':
            case 'scale':
                if (!self::isNum($scalar)) return $label . ' must be a number.';
                $n = (float)$scalar;
                if (isset($f['min']) && $n < $f['min']) return $label . ' must be at least ' . $f['min'] . '.';
                if (isset($f['max']) && $n > $f['max']) return $label . ' must be ' . $f['max'] . ' or below.';
                break;
            case 'date':
                if (!self::isDate($scalar)) return 'Use the date picker (YYYY-MM-DD).';
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $scalar)) return 'Use a 24-hour time, like 14:30.';
                break;
            case 'choice':
            case 'dropdown':
                $allowed = self::optionValues($f);
                if ($allowed && !in_array($scalar, $allowed, true)) return 'Pick one of the listed options.';
                break;
            case 'multi':
                $n = is_array($v) ? count($v) : 0;
                if (isset($f['minSelect']) && $n < $f['minSelect']) return 'Pick at least ' . $f['minSelect'] . '.';
                if (isset($f['maxSelect']) && $n > $f['maxSelect']) return 'Pick no more than ' . $f['maxSelect'] . '.';
                break;
            case 'consent':
                if ($scalar !== 'yes') return $f['requiredMessage'] ?? ($label . ' must be agreed to continue.');
                break;
            case 'yesno':
                if (!in_array($scalar, ['yes', 'no'], true)) return 'Choose yes or no.';
                break;
        }

        if (isset($f['minLen']) && mb_strlen($scalar) < $f['minLen']) return $label . ' should be at least ' . $f['minLen'] . ' characters.';
        if (isset($f['maxLen']) && mb_strlen($scalar) > $f['maxLen']) return $label . ' is too long (max ' . $f['maxLen'] . ').';
        if (!empty($f['pattern']) && $scalar !== '') {
            $ok = @preg_match('/' . str_replace('/', '\/', $f['pattern']) . '/u', $scalar);
            // A pattern that fails to compile must not block a real family.
            if ($ok === 0) return $f['patternMessage'] ?? ($label . " isn't in the expected format.");
        }
        return null;
    }

    /** Sum the priced add-ons the answers selected (naira). */
    public static function addons(array $resolved, array $answers, ?array $vis = null): int {
        $total = 0;
        foreach ($resolved as $f) {
            $k = $f['key'];
            if ($vis !== null && isset($vis[$k]) && !$vis[$k]) continue;
            if (!array_key_exists($k, $answers)) continue;
            $v = $answers[$k];
            $isEmpty = is_array($v) ? !count($v) : ($v === '' || $v === 'no');
            if ($isEmpty) continue;

            if (!empty($f['price'])) $total += (int)$f['price'];

            if (in_array($f['type'], self::OPTION_TYPES, true)) {
                $picked = is_array($v) ? $v : [$v];
                foreach ($f['options'] ?? [] as $o) {
                    if (empty($o['price'])) continue;
                    if (in_array((string)$o['value'], array_map('strval', $picked), true)) $total += (int)$o['price'];
                }
            }
        }
        return max(0, $total);
    }

    /* ─────────────────────────────────────────────────────────────────
       Presentation helpers
       ───────────────────────────────────────────────────────────────── */

    /** Option `value` list for a resolved field. */
    public static function optionValues(array $f): array {
        $out = [];
        foreach ($f['options'] ?? [] as $o) {
            $v = (string)($o['value'] ?? '');
            if ($v !== '') $out[] = $v;
        }
        return $out;
    }

    /** value => label map, for rendering a stored answer back to a human. */
    public static function optionLabels(array $f): array {
        $out = [];
        foreach ($f['options'] ?? [] as $o) $out[(string)($o['value'] ?? '')] = (string)($o['label'] ?? $o['value'] ?? '');
        return $out;
    }

    /**
     * Turn stored answers into ordered [label, value] rows for the ops console
     * and confirmation emails. `$includeSensitive` gates the fields an operator
     * flagged as staff-only.
     */
    public static function presentAnswers(array $resolved, array $answers, bool $includeSensitive = true): array {
        $rows = [];
        foreach ($resolved as $f) {
            $k = $f['key'];
            if (in_array($f['type'], self::LAYOUT_TYPES, true)) continue;
            if (!array_key_exists($k, $answers)) continue;
            if (!empty($f['sensitive']) && !$includeSensitive) continue;
            $v = $answers[$k];
            $labels = self::optionLabels($f);
            if (is_array($v)) {
                $v = implode(', ', array_map(fn($x) => $labels[(string)$x] ?? (string)$x, $v));
            } else {
                $v = $labels[(string)$v] ?? (string)$v;
            }
            $rows[] = [
                'key'       => $k,
                'label'     => self::labelOf($f),
                'value'     => $v,
                'sensitive' => !empty($f['sensitive']),
            ];
        }
        return $rows;
    }

    /** The JSON handed to the browser so the client mirrors server logic. */
    public static function clientSpec(array $resolved, array $settings): array {
        $fields = [];
        foreach ($resolved as $f) {
            $spec = [
                'key'  => $f['key'],
                'type' => $f['type'],
                'step' => (int)($f['step'] ?? 0),
            ];
            foreach (['required', 'logic', 'requiredIf', 'min', 'max', 'minLen', 'maxLen',
                      'minSelect', 'maxSelect', 'pattern', 'patternMessage', 'requiredMessage', 'price'] as $k) {
                if (isset($f[$k])) $spec[$k] = $f[$k];
            }
            $spec['label'] = self::labelOf($f);
            if (in_array($f['type'], self::OPTION_TYPES, true)) {
                $spec['options'] = array_map(fn($o) => [
                    'value' => (string)($o['value'] ?? ''),
                    'price' => (int)($o['price'] ?? 0),
                ], $f['options'] ?? []);
            }
            $fields[] = $spec;
        }
        return [
            'fields'   => $fields,
            'steps'    => self::stepCount($resolved),
            'settings' => $settings,
        ];
    }

    /* ─────────────────────────────────────────────────────────────────
       Internals
       ───────────────────────────────────────────────────────────────── */

    private static function normalizeOptions($raw): array {
        $out = [];
        foreach ((array)$raw as $o) {
            if (is_string($o)) $o = ['value' => $o, 'label' => $o];
            if (!is_array($o)) continue;
            $label = mb_substr(trim((string)($o['label'] ?? $o['value'] ?? '')), 0, 120);
            $value = mb_substr(trim((string)($o['value'] ?? $label)), 0, 120);
            if ($label === '' && $value === '') continue;
            if ($value === '') $value = $label;
            if ($label === '') $label = $value;
            $row = ['value' => $value, 'label' => $label];
            if (self::isNum($o['price'] ?? null) && (int)$o['price'] !== 0) $row['price'] = (int)$o['price'];
            $help = trim((string)($o['help'] ?? ''));
            if ($help !== '') $row['help'] = mb_substr($help, 0, 160);
            $out[] = $row;
        }
        // Duplicate values would make an answer ambiguous.
        $seen = [];
        $dedup = [];
        foreach ($out as $row) {
            if (isset($seen[$row['value']])) continue;
            $seen[$row['value']] = true;
            $dedup[] = $row;
        }
        return array_slice($dedup, 0, self::MAX_OPTIONS);
    }

    /** Clean a rule group, or null when there's nothing usable. */
    private static function normalizeLogic($raw, array $actions): ?array {
        if (!is_array($raw)) return null;
        $rules = [];
        foreach ((array)($raw['rules'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $field = self::cleanKey((string)($r['field'] ?? ''));
            $op    = (string)($r['op'] ?? 'equals');
            if ($field === '' || !isset(self::OPERATORS[$op])) continue;
            $rule = ['field' => $field, 'op' => $op];
            if (self::OPERATORS[$op]['needsValue']) {
                $rule['value'] = mb_substr(trim((string)($r['value'] ?? '')), 0, 200);
            }
            $rules[] = $rule;
            if (count($rules) >= self::MAX_RULES) break;
        }
        if (!$rules) return null;
        $action = (string)($raw['action'] ?? $actions[0]);
        return [
            'action' => in_array($action, $actions, true) ? $action : $actions[0],
            'match'  => (($raw['match'] ?? 'all') === 'any') ? 'any' : 'all',
            'rules'  => $rules,
        ];
    }

    private static function defErr(string $code, string $detail): array {
        return ['ok' => false, 'error' => $code, 'detail' => $detail];
    }

    public static function cleanKey(string $raw): string {
        return (string)preg_replace('/[^a-zA-Z0-9_]/', '', $raw);
    }

    public static function keyFromLabel(string $label, string $type = 'text', int $i = 0): string {
        $k = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? ''), '_');
        $k = preg_replace('/_+/', '_', $k) ?: '';
        if ($k === '' || preg_match('/^\d/', $k)) $k = $type . '_' . ($i + 1);
        return substr($k, 0, 40);
    }

    private static function labelOf(array $f): string {
        return $f['label'] !== '' ? $f['label'] : ucfirst(str_replace('_', ' ', $f['key']));
    }

    private static function isNum($v): bool {
        return $v !== null && $v !== '' && is_numeric($v);
    }

    private static function isDate(string $v): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return false;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** Is this admin-authored pattern something preg can actually run? */
    private static function patternUsable(string $pattern): bool {
        return @preg_match('/' . str_replace('/', '\/', $pattern) . '/u', '') !== false;
    }
}
