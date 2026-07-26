<?php
/**
 * FormEngine test harness — no database, no framework boot.
 *
 *   php scripts/test-form-engine.php
 *
 * The engine is pure by design, so it can be exercised directly. These are
 * the behaviours the public form depends on: definitions are refused when
 * they'd break, logic resolves in chains, hidden fields are neither required
 * nor readable, and the price is computed from what was actually asked.
 */

declare(strict_types=1);

define('AFT_ROOT', dirname(__DIR__));
require AFT_ROOT . '/src/core/FormEngine.php';

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  \033[32m✓\033[0m {$what}\n"; return; }
    $fail++;
    echo "  \033[31m✗ {$what}\033[0m" . ($detail !== '' ? "  — {$detail}" : '') . "\n";
}
function group(string $name): void { echo "\n\033[1m{$name}\033[0m\n"; }

/** A minimal definition that satisfies the core-field requirement. */
function coreFields(array $extra = []): array {
    return array_merge([
        ['key' => 'student_name',  'type' => 'text',   'label' => 'Student',  'map' => 'student_name',  'required' => true],
        ['key' => 'student_age',   'type' => 'number', 'label' => 'Age',      'map' => 'student_age',   'required' => true, 'min' => 7, 'max' => 19],
        ['key' => 'guardian_name', 'type' => 'text',   'label' => 'Guardian', 'map' => 'guardian_name', 'required' => true],
        ['key' => 'email',         'type' => 'email',  'label' => 'Email',    'map' => 'email',         'required' => true],
        ['key' => 'phone',         'type' => 'phone',  'label' => 'Phone',    'map' => 'phone',         'required' => true],
        ['key' => 'track_slug',    'type' => 'choice', 'label' => 'Track',    'map' => 'track_slug',    'required' => true,
         'options' => [['value' => 'coding', 'label' => 'Coding'], ['value' => 'design', 'label' => 'Design']]],
    ], $extra);
}

function goodAnswers(array $extra = []): array {
    return array_merge([
        'student_name'  => 'Ada Obi',
        'student_age'   => '12',
        'guardian_name' => 'Ngozi Obi',
        'email'         => 'ngozi@example.com',
        'phone'         => '+234 810 019 1456',
        'track_slug'    => 'coding',
    ], $extra);
}

/* ══ definition validation ═══════════════════════════════════════ */

group('Definition validation');

$v = FormEngine::normalizeFields(coreFields());
ok('a complete core definition is accepted', !empty($v['ok']), $v['detail'] ?? '');

$v = FormEngine::normalizeFields([
    ['key' => 'student_name', 'type' => 'text', 'label' => 'Student', 'map' => 'student_name'],
]);
ok('a definition missing core fields is refused', empty($v['ok']) && $v['error'] === 'missing_core_field');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'email', 'type' => 'text', 'label' => 'Duplicate key'],
]));
ok('duplicate field keys are refused', empty($v['ok']) && $v['error'] === 'duplicate_field_key');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'second_email', 'type' => 'email', 'label' => 'Other email', 'map' => 'email'],
]));
ok('two fields writing the same column are refused', empty($v['ok']) && $v['error'] === 'duplicate_map');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'shoes', 'type' => 'choice', 'label' => 'Shoe size', 'options' => []],
]));
ok('a choice field with no options is refused', empty($v['ok']) && $v['error'] === 'choice_needs_options');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'early', 'type' => 'text', 'label' => 'Depends on later',
     'logic' => ['action' => 'show', 'rules' => [['field' => 'later_field', 'op' => 'equals', 'value' => 'x']]]],
    ['key' => 'later_field', 'type' => 'text', 'label' => 'Later'],
]));
ok('a rule referencing a later field is refused', empty($v['ok']) && $v['error'] === 'rule_forward_reference');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'code', 'type' => 'text', 'label' => 'Code', 'pattern' => '([unclosed'],
]));
ok('an uncompilable regex pattern is refused', empty($v['ok']) && $v['error'] === 'bad_pattern');

$v = FormEngine::normalizeFields(coreFields([
    ['key' => 'weird', 'type' => 'text', 'label' => 'Weird', 'nonsense' => 'dropped', 'price' => '2500'],
]));
$weird = null;
foreach ($v['fields'] ?? [] as $f) if ($f['key'] === 'weird') $weird = $f;
ok('unknown properties are dropped, known ones cast', $weird !== null && !isset($weird['nonsense']) && $weird['price'] === 2500);

$v = FormEngine::normalizeFields(coreFields([
    ['type' => 'text', 'label' => 'What school do they attend?'],
]));
$keys = array_column($v['fields'] ?? [], 'key');
ok('a blank key is derived from the label', in_array('what_school_do_they_attend', $keys, true), implode(',', $keys));

/* ══ logic: chains and fixed point ═══════════════════════════════ */

group('Conditional logic');

$chain = FormEngine::normalizeFields(coreFields([
    ['key' => 'has_laptop', 'type' => 'yesno', 'label' => 'Has a laptop?'],
    ['key' => 'laptop_os', 'type' => 'choice', 'label' => 'Which OS?', 'required' => true,
     'options' => [['value' => 'win', 'label' => 'Windows'], ['value' => 'mac', 'label' => 'macOS']],
     'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [['field' => 'has_laptop', 'op' => 'checked']]]],
    // Depends on a field that is itself conditional — the chain case.
    ['key' => 'mac_version', 'type' => 'text', 'label' => 'macOS version', 'required' => true,
     'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [['field' => 'laptop_os', 'op' => 'equals', 'value' => 'mac']]]],
]));
ok('a chained definition is accepted', !empty($chain['ok']), $chain['detail'] ?? '');
$resolved = FormEngine::resolve($chain['fields'], ['tracks' => [], 'campuses' => []]);

$vis = FormEngine::visibility($resolved, goodAnswers(['has_laptop' => 'no']));
ok('a dependent field hides when its trigger is off', $vis['laptop_os'] === false);
ok('a chained field hides when its parent is hidden', $vis['mac_version'] === false);

$vis = FormEngine::visibility($resolved, goodAnswers(['has_laptop' => 'yes', 'laptop_os' => 'mac']));
ok('the chain opens fully when both triggers pass', $vis['laptop_os'] === true && $vis['mac_version'] === true);

// The interesting one: a stale answer on a field whose parent has since been
// switched off must not resurrect the grandchild.
$vis = FormEngine::visibility($resolved, goodAnswers(['has_laptop' => 'no', 'laptop_os' => 'mac']));
ok('a stale answer on a hidden field cannot reveal its child', $vis['mac_version'] === false);

$res = FormEngine::validateSubmission($resolved, goodAnswers(['has_laptop' => 'no', 'laptop_os' => 'mac']));
ok('a hidden required field does not block submission', !empty($res['ok']), json_encode($res['errors'] ?? []));
ok('a hidden field\'s posted value is discarded', !array_key_exists('laptop_os', $res['answers'] ?? []));

$res = FormEngine::validateSubmission($resolved, goodAnswers(['has_laptop' => 'yes']));
ok('a visible required field does block submission', empty($res['ok']) && isset($res['errors']['laptop_os']));

/* ══ conditional requirement ════════════════════════════════════ */

group('Conditional requirement');

$reqIf = FormEngine::normalizeFields(coreFields([
    ['key' => 'experience', 'type' => 'choice', 'label' => 'Experience',
     'options' => [['value' => 'none', 'label' => 'None'], ['value' => 'confident', 'label' => 'Confident']]],
    ['key' => 'detail', 'type' => 'longtext', 'label' => 'Tell us more',
     'requiredIf' => ['action' => 'require', 'match' => 'all',
                      'rules' => [['field' => 'experience', 'op' => 'equals', 'value' => 'confident']]]],
]));
$r2 = FormEngine::resolve($reqIf['fields'], ['tracks' => [], 'campuses' => []]);

$res = FormEngine::validateSubmission($r2, goodAnswers(['experience' => 'none']));
ok('the field is optional while the rule is unmet', !empty($res['ok']));

$res = FormEngine::validateSubmission($r2, goodAnswers(['experience' => 'confident']));
ok('the field becomes required once the rule is met', empty($res['ok']) && isset($res['errors']['detail']));

$res = FormEngine::validateSubmission($r2, goodAnswers(['experience' => 'confident', 'detail' => 'Built a game.']));
ok('an answer satisfies the conditional requirement', !empty($res['ok']));

/* ══ operators ══════════════════════════════════════════════════ */

group('Operators');

$cases = [
    ['12', 'gt', '10', true],   ['12', 'gt', '20', false],
    ['12', 'between', '10,15', true], ['22', 'between', '10,15', false],
    ['mac', 'in', 'win, mac', true], ['linux', 'in', 'win, mac', false],
    ['mac', 'not_in', 'win, mac', false],
    ['hello world', 'contains', 'WORLD', true],
    ['hello', 'starts_with', 'he', true], ['hello', 'ends_with', 'lo', true],
    ['', 'empty', '', true], ['x', 'not_empty', '', true],
    ['yes', 'checked', '', true], ['no', 'checked', '', false],
    ['no', 'unchecked', '', true],
    ['abc', 'gt', '5', false],   // non-numeric comparison must not throw or pass
];
$allOps = true;
foreach ($cases as [$a, $op, $e, $want]) {
    $got = FormEngine::compare($a, $op, $e);
    if ($got !== $want) { $allOps = false; echo "      op {$op}('{$a}','{$e}') = " . var_export($got, true) . ", want " . var_export($want, true) . "\n"; }
}
ok('scalar operators behave', $allOps);

ok('includes matches inside a multi-select', FormEngine::compare(['lunch', 'kit'], 'includes', 'kit'));
ok('a multi-select with no picks reads as empty', FormEngine::compare([], 'empty', ''));
ok('an operator that does not exist is false, not fatal', FormEngine::compare('x', 'no_such_op', 'x') === false);

/* ══ validation rules ═══════════════════════════════════════════ */

group('Field validation');

$strict = FormEngine::normalizeFields(coreFields([
    ['key' => 'extras', 'type' => 'multi', 'label' => 'Extras', 'maxSelect' => 2,
     'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']]],
    ['key' => 'ref', 'type' => 'text', 'label' => 'Reference', 'pattern' => '^[A-Z]{3}[0-9]{3}$',
     'patternMessage' => 'Three letters then three digits.'],
    ['key' => 'bio', 'type' => 'longtext', 'label' => 'Bio', 'minLen' => 10],
    ['key' => 'consent', 'type' => 'consent', 'label' => 'I agree', 'required' => true],
]));
$r3 = FormEngine::resolve($strict['fields'], ['tracks' => [], 'campuses' => []]);
$base = goodAnswers(['consent' => 'yes']);

$res = FormEngine::validateSubmission($r3, array_merge($base, ['student_age' => '4']));
ok('a number below the minimum is rejected', isset($res['errors']['student_age']));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['email' => 'not-an-email']));
ok('a malformed email is rejected', isset($res['errors']['email']));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['track_slug' => 'not-a-track']));
ok('an option outside the list is rejected', isset($res['errors']['track_slug']));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['extras' => ['a', 'b', 'c']]));
ok('too many multi-select picks are rejected', isset($res['errors']['extras']));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['extras' => ['a', 'ghost']]));
ok('an option that was never offered is silently dropped',
    !empty($res['ok']) && $res['answers']['extras'] === ['a'], json_encode($res['answers']['extras'] ?? null));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['ref' => 'ab1']));
ok('a value failing the pattern is rejected with its message',
    ($res['errors']['ref'] ?? '') === 'Three letters then three digits.');

$res = FormEngine::validateSubmission($r3, array_merge($base, ['ref' => 'ABC123']));
ok('a value matching the pattern passes', !empty($res['ok']), json_encode($res['errors'] ?? []));

$res = FormEngine::validateSubmission($r3, array_merge($base, ['bio' => 'short']));
ok('a value under minLen is rejected', isset($res['errors']['bio']));

$res = FormEngine::validateSubmission($r3, goodAnswers());   // consent absent
ok('an unticked required consent blocks submission', isset($res['errors']['consent']));

$res = FormEngine::validateSubmission($r3, $base);
ok('mapped values reach the record columns',
    ($res['mapped']['email'] ?? '') === 'ngozi@example.com' && ($res['mapped']['track_slug'] ?? '') === 'coding');

/* ══ pricing ════════════════════════════════════════════════════ */

group('Pricing');

$priced = FormEngine::normalizeFields(coreFields([
    ['key' => 'campus', 'type' => 'choice', 'label' => 'Campus',
     'options' => [['value' => 'Egbeda', 'label' => 'Egbeda'], ['value' => 'Online', 'label' => 'Online']]],
    ['key' => 'shuttle', 'type' => 'yesno', 'label' => 'Shuttle', 'price' => 5000,
     'logic' => ['action' => 'show', 'match' => 'all', 'rules' => [['field' => 'campus', 'op' => 'not_equals', 'value' => 'Online']]]],
    ['key' => 'extras', 'type' => 'multi', 'label' => 'Add-ons',
     'options' => [['value' => 'laptop', 'label' => 'Laptop', 'price' => 15000],
                   ['value' => 'lunch',  'label' => 'Lunch',  'price' => 12000],
                   ['value' => 'free',   'label' => 'Free thing']]],
]));
$r4 = FormEngine::resolve($priced['fields'], ['tracks' => [], 'campuses' => []]);

$res = FormEngine::validateSubmission($r4, goodAnswers(['campus' => 'Egbeda', 'shuttle' => 'yes', 'extras' => ['laptop', 'lunch']]));
ok('flat and per-option prices sum', ($res['addons'] ?? 0) === 32000, (string)($res['addons'] ?? -1));

$res = FormEngine::validateSubmission($r4, goodAnswers(['campus' => 'Egbeda', 'shuttle' => 'no', 'extras' => ['free']]));
ok('a "no" answer and unpriced options add nothing', ($res['addons'] ?? -1) === 0, (string)($res['addons'] ?? -1));

// The security-relevant case: pricing must follow the logic, not the POST.
$res = FormEngine::validateSubmission($r4, goodAnswers(['campus' => 'Online', 'shuttle' => 'yes']));
ok('a priced field hidden by logic cannot be bought', ($res['addons'] ?? -1) === 0, (string)($res['addons'] ?? -1));

/* ══ steps ══════════════════════════════════════════════════════ */

group('Steps');

$paged = FormEngine::normalizeFields(coreFields([
    ['type' => 'step', 'label' => 'Page two'],
    ['key' => 'notes', 'type' => 'longtext', 'label' => 'Notes', 'map' => 'notes'],
    ['type' => 'step', 'label' => 'Page three'],
    ['key' => 'consent', 'type' => 'consent', 'label' => 'I agree', 'required' => true],
]));
$r5 = FormEngine::resolve($paged['fields'], ['tracks' => [], 'campuses' => []]);
ok('page breaks produce step boundaries', FormEngine::stepCount($r5) === 3, (string)FormEngine::stepCount($r5));
ok('step markers are dropped from the field list',
    count(array_filter($r5, fn($f) => $f['type'] === 'step')) === 0);
$last = end($r5);
ok('a field after two breaks lands on step 2', $last['step'] === 2, (string)$last['step']);

/* ══ client spec ════════════════════════════════════════════════ */

group('Client spec');

$spec = FormEngine::clientSpec($r4, FormEngine::normalizeSettings([]));
ok('the client spec carries the logic', !empty($spec['fields']));
$shuttle = null;
foreach ($spec['fields'] as $f) if ($f['key'] === 'shuttle') $shuttle = $f;
ok('a conditional field ships its rules to the browser', isset($shuttle['logic']['rules'][0]['field']));
ok('option prices ship to the browser', ($spec['fields'][7]['options'][0]['price'] ?? null) !== null
    || (function () use ($spec) {
        foreach ($spec['fields'] as $f) {
            foreach ($f['options'] ?? [] as $o) if (($o['price'] ?? 0) === 15000) return true;
        }
        return false;
    })());
ok('the spec never leaks a mapped column name', !str_contains(json_encode($spec), '"map"'));

/* ══ the shipped default form ════════════════════════════════════ */

group('Seeded summer form');

// FormDef only touches the database when it persists; the default definition
// is a pure function, so the seed a fresh install receives can be verified.
require AFT_ROOT . '/src/models/FormDef.php';

$seed = FormEngine::normalizeFields(FormDef::summerDefaults());
ok('the seeded summer definition is valid', !empty($seed['ok']), $seed['detail'] ?? '');

if (!empty($seed['ok'])) {
    // Track options come from the database at render time; stub them here.
    $seedResolved = FormEngine::resolve($seed['fields'], [
        'tracks'   => [['value' => 'coding', 'label' => 'Coding']],
        'campuses' => [['value' => 'Egbeda', 'label' => 'Egbeda']],
    ]);
    ok('it spans three steps', FormEngine::stepCount($seedResolved) === 3, (string)FormEngine::stepCount($seedResolved));

    $seedAnswers = [
        'student_name' => 'Ada Obi', 'student_age' => '12', 'track_slug' => 'coding',
        'experience' => 'none', 'guardian_name' => 'Ngozi Obi', 'phone' => '08100191456',
        'email' => 'ngozi@example.com', 'location_pref' => 'Online', 'consent' => 'yes',
    ];
    $res = FormEngine::validateSubmission($seedResolved, $seedAnswers);
    ok('a minimal honest submission passes it', !empty($res['ok']), json_encode($res['errors'] ?? []));
    ok('every core column is filled', count(array_diff(FormEngine::REQUIRED_MAPS, array_keys($res['mapped'] ?? []))) === 0);

    // Online learners are never shown the shuttle, so they can't be charged for it.
    ok('the shuttle add-on is unreachable for online learners', ($res['addons'] ?? -1) === 0, (string)($res['addons'] ?? -1));

    $res = FormEngine::validateSubmission($seedResolved, array_merge($seedAnswers, [
        'location_pref' => 'Egbeda', 'transport' => 'yes', 'extras' => ['laptop'],
    ]));
    ok('on-campus add-ons price correctly', ($res['addons'] ?? 0) === 20000, (string)($res['addons'] ?? -1));

    // "Confident" makes the follow-up mandatory in the shipped definition.
    $res = FormEngine::validateSubmission($seedResolved, array_merge($seedAnswers, ['experience' => 'confident']));
    ok('confident learners must describe prior work', empty($res['ok']) && isset($res['errors']['experience_detail']));
}

/* ══ summary ════════════════════════════════════════════════════ */

echo "\n" . str_repeat('─', 52) . "\n";
echo $fail === 0
    ? "\033[32mAll {$pass} assertions passed.\033[0m\n"
    : "\033[31m{$fail} failed\033[0m, {$pass} passed.\n";
exit($fail === 0 ? 0 : 1);
