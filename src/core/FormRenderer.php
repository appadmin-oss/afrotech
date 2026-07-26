<?php

/**
 * FormRenderer — turns a resolved form definition into HTML.
 *
 * Shared by the public /summer page and the builder's preview pane, so what an
 * operator previews is literally what a parent gets. It renders the *whole*
 * form including conditionally-hidden fields and marks them `hidden`; the
 * browser reveals them as answers arrive (assets/js/form-logic.js), and the
 * server re-decides visibility on submit. That way the form works with
 * JavaScript off — every question is present and posts normally — while
 * still enforcing the logic where it counts.
 */
class FormRenderer {

    /** Countries offered by the `country` field type. */
    private const COUNTRIES = 'Nigeria,Ghana,Kenya,South Africa,Egypt,Ethiopia,Morocco,Tanzania,Uganda,Rwanda,Senegal,Ivory Coast,Cameroon,Zambia,Zimbabwe,Botswana,Namibia,Benin,Togo,Sierra Leone,Liberia,Gambia,Niger,Mali,Burkina Faso,Algeria,Tunisia,Libya,Sudan,Somalia,Mozambique,Angola,Malawi,Congo,DR Congo,Gabon,United Kingdom,United States,Canada,Ireland,France,Germany,Netherlands,Belgium,Spain,Portugal,Italy,Sweden,Norway,Denmark,Switzerland,Austria,Poland,United Arab Emirates,Saudi Arabia,Qatar,Turkey,India,China,Japan,Malaysia,Singapore,Australia,New Zealand,Brazil,Mexico,Jamaica,Trinidad and Tobago,Other';

    /**
     * Render a complete form.
     *
     * $opts:
     *   action     string  POST target (omitted in preview)
     *   errors     array   key => message, from a failed server validation
     *   old        array   key => previously submitted value
     *   fee        int     base programme fee, for the running total
     *   symbol     string  currency symbol
     *   preview    bool    inert markup for the builder (no CSRF, no submit)
     *   prefill    array   query parameters available to `prefill` fields
     */
    public static function form(array $def, array $opts = []): string {
        $fields   = FormEngine::resolve($def['fields'] ?? []);
        $settings = $def['settings'] ?? FormEngine::normalizeSettings([]);
        $errors   = $opts['errors']  ?? [];
        $old      = $opts['old']     ?? [];
        $preview  = !empty($opts['preview']);
        $fee      = (int)($opts['fee'] ?? 0);
        $symbol   = (string)($opts['symbol'] ?? '₦');
        $prefill  = $opts['prefill'] ?? [];
        $steps    = FormEngine::stepCount($fields);

        // Which step should open? On a validation bounce, the first one that
        // actually has an error — nobody should hunt for it.
        $openStep = 0;
        if ($errors) {
            foreach ($fields as $f) {
                if (isset($errors[$f['key']])) { $openStep = (int)($f['step'] ?? 0); break; }
            }
        }

        $spec = FormEngine::clientSpec($fields, $settings);
        $spec['fee']    = $fee;
        $spec['symbol'] = $symbol;
        $spec['open']   = $openStep;

        $h = [];
        $h[] = '<form class="fb-form form' . ($preview ? ' fb-form--preview' : '') . '"'
             . ' data-form-logic'
             . ' data-steps="' . $steps . '"'
             . ($preview ? '' : ' method="post" action="' . e((string)($opts['action'] ?? '')) . '" data-async')
             . ' novalidate>';

        // HEX_TAG/HEX_AMP keep a stray "</script>" inside an operator-authored
        // label from ending the block early.
        $h[] = '<script type="application/json" data-form-spec>'
             . (string)json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
             . '</script>';

        if (!$preview) {
            $h[] = Csrf::field();
            $h[] = '<input type="hidden" name="form_version" value="' . (int)($def['version'] ?? 1) . '">';
            // Bots fill everything; humans never see this.
            $h[] = '<div class="fb-trap" aria-hidden="true"><label>Leave this blank'
                 . '<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>';
            $h[] = '<input type="hidden" name="started_at" value="' . time() . '">';
        }

        $h[] = '<div data-form-alert>' . ($errors
            ? '<div class="alert alert--err">Please check the highlighted fields.</div>' : '') . '</div>';

        if ($steps > 1 && !empty($settings['showProgress'])) {
            $h[] = self::progress($fields, $steps);
        }

        for ($s = 0; $s < $steps; $s++) {
            $h[] = '<div class="fb-step" data-step="' . $s . '"' . ($s === $openStep ? '' : ' hidden') . '>';
            foreach ($fields as $f) {
                if ((int)($f['step'] ?? 0) !== $s) continue;
                $h[] = self::block($f, $old, $errors, $prefill, $symbol);
            }
            $h[] = '</div>';
        }

        if (!empty($settings['showPrice'])) {
            $h[] = '<div class="fb-total" data-form-total hidden>'
                 . '<div class="fb-total__rows" data-total-rows></div>'
                 . '<div class="fb-total__sum"><span>Total today</span>'
                 . '<b data-total-sum>' . e($symbol . number_format($fee)) . '</b></div>'
                 . '</div>';
        }

        $h[] = '<div class="fb-nav">';
        $h[] = '<button type="button" class="btn btn--ghost fb-nav__back" data-form-back hidden>← Back</button>';
        if ($steps > 1) {
            $h[] = '<button type="button" class="btn btn--ink fb-nav__next" data-form-next>Next step →</button>';
        }
        $h[] = '<button type="submit" class="btn btn--primary btn--lg fb-nav__submit" data-form-submit'
             . ($steps > 1 ? ' hidden' : '') . ($preview ? ' disabled' : '') . '>'
             . e((string)($settings['submitLabel'] ?? 'Submit')) . '</button>';
        $h[] = '</div>';

        $h[] = '</form>';
        return implode("\n", $h);
    }

    /** Step rail — one pip per page, labelled from the page-break markers. */
    private static function progress(array $fields, int $steps): string {
        $labels = [];
        foreach ($fields as $f) {
            $s = (int)($f['step'] ?? 0);
            if (!isset($labels[$s]) && in_array($f['type'], ['section'], true) && $f['label'] !== '') {
                $labels[$s] = $f['label'];
            }
        }
        $out = ['<ol class="fb-progress" data-form-progress aria-label="Form progress">'];
        for ($s = 0; $s < $steps; $s++) {
            $out[] = '<li class="fb-progress__step' . ($s === 0 ? ' is-current' : '') . '" data-progress-step="' . $s . '">'
                   . '<span class="fb-progress__pip">' . ($s + 1) . '</span>'
                   . '<span class="fb-progress__label">' . e($labels[$s] ?? ('Step ' . ($s + 1))) . '</span>'
                   . '</li>';
        }
        $out[] = '</ol>';
        return implode('', $out);
    }

    /** One block — layout furniture or an input field. */
    public static function block(array $f, array $old, array $errors, array $prefill, string $symbol): string {
        $key   = $f['key'];
        $type  = $f['type'];
        $label = $f['label'] ?? '';

        // Conditional fields ship hidden and are revealed client-side.
        $attrs = ' data-field="' . e($key) . '"';
        if (!empty($f['logic'])) {
            $showByDefault = $f['logic']['action'] === 'hide';
            $attrs .= ' data-conditional="1"' . ($showByDefault ? '' : ' hidden');
        }

        if ($type === 'section') {
            return '<div class="fb-section"' . $attrs . '>'
                 . '<h3 class="fb-section__title">' . e($label) . '</h3>'
                 . (!empty($f['help']) ? '<p class="fb-section__help">' . e($f['help']) . '</p>' : '')
                 . '</div>';
        }
        if ($type === 'info') {
            return '<div class="fb-info"' . $attrs . '>' . nl2br(e($label)) . '</div>';
        }
        if ($type === 'divider') {
            return '<hr class="fb-divider"' . $attrs . '>';
        }

        $value = $old[$key] ?? null;
        if ($value === null || $value === '') {
            if (!empty($f['prefill']) && isset($prefill[$f['prefill']])) {
                $value = is_string($prefill[$f['prefill']]) ? trim($prefill[$f['prefill']]) : '';
            } elseif (isset($f['default'])) {
                $value = $f['default'];
            }
        }
        $err = $errors[$key] ?? '';

        if ($type === 'hidden') {
            return '<input type="hidden" name="' . e($key) . '" value="' . e(is_array($value) ? '' : (string)$value) . '"'
                 . ' data-field="' . e($key) . '">';
        }

        $classes = 'field fb-field';
        if (!empty($f['width']) && $f['width'] === 'half') $classes .= ' fb-field--half';
        if ($err !== '') $classes .= ' has-error';

        $h = ['<div class="' . $classes . '"' . $attrs . '>'];

        // The consent checkbox carries its own label inline.
        if ($type !== 'consent') {
            $h[] = '<label id="fb-' . e($key) . '-l" for="fb-' . e($key) . '">' . e($label)
                 . (!empty($f['required']) ? ' <span class="req">*</span>' : '')
                 . (!empty($f['requiredIf']) ? ' <span class="fb-when" title="Required depending on your earlier answers">*</span>' : '')
                 . (!empty($f['price']) ? ' <span class="fb-price">+' . e($symbol . number_format((int)$f['price'])) . '</span>' : '')
                 . '</label>';
        }

        $h[] = self::input($f, $value, $symbol);

        if (!empty($f['help'])) $h[] = '<div class="hint">' . e($f['help']) . '</div>';
        $h[] = '<div class="err">' . e($err) . '</div>';
        $h[] = '</div>';
        return implode("\n", $h);
    }

    /** The control itself. */
    private static function input(array $f, $value, string $symbol): string {
        $key  = e($f['key']);
        $id   = 'fb-' . $key;
        $req  = !empty($f['required']) ? ' data-required="1"' : '';
        $ph   = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
        $val  = is_array($value) ? '' : e((string)($value ?? ''));
        $list = is_array($value) ? array_map('strval', $value) : array_filter([(string)($value ?? '')], fn($x) => $x !== '');

        switch ($f['type']) {
            case 'longtext':
                return '<textarea id="' . $id . '" name="' . $key . '" rows="' . (int)($f['rows'] ?? 4) . '"' . $ph . $req . '>' . $val . '</textarea>';

            case 'number':
                return '<input type="number" id="' . $id . '" name="' . $key . '" value="' . $val . '"'
                     . (isset($f['min']) ? ' min="' . (int)$f['min'] . '"' : '')
                     . (isset($f['max']) ? ' max="' . (int)$f['max'] . '"' : '')
                     . ' inputmode="numeric"' . $ph . $req . '>';

            case 'scale':
                $min = (int)($f['min'] ?? 1); $max = (int)($f['max'] ?? 10);
                $out = ['<div class="fb-scale" role="radiogroup" aria-labelledby="' . $id . '-l">'];
                for ($i = $min; $i <= $max; $i++) {
                    $out[] = '<label class="fb-scale__dot"><input type="radio" name="' . $key . '" value="' . $i . '"'
                           . ((string)$i === (string)$val ? ' checked' : '') . $req . '><span>' . $i . '</span></label>';
                }
                $out[] = '</div>';
                return implode('', $out);

            case 'date':
                return '<input type="date" id="' . $id . '" name="' . $key . '" value="' . $val . '"' . $req . '>';

            case 'time':
                return '<input type="time" id="' . $id . '" name="' . $key . '" value="' . $val . '"' . $req . '>';

            case 'email':
                return '<input type="email" id="' . $id . '" name="' . $key . '" value="' . $val . '" autocomplete="email"' . $ph . $req . '>';

            case 'phone':
                return '<input type="tel" id="' . $id . '" name="' . $key . '" value="' . $val . '" autocomplete="tel"' . $ph . $req . '>';

            case 'url':
                return '<input type="url" id="' . $id . '" name="' . $key . '" value="' . $val . '"'
                     . ($ph !== '' ? $ph : ' placeholder="https://"') . $req . '>';

            case 'file':
                return '<input type="file" id="' . $id . '" name="' . $key . '"'
                     . (!empty($f['accept']) ? ' accept="' . e($f['accept']) . '"' : '') . $req . '>';

            case 'country':
                $out = ['<input type="text" id="' . $id . '" name="' . $key . '" value="' . $val . '" list="' . $id . '-list"'
                      . ($ph !== '' ? $ph : ' placeholder="Start typing…"') . $req . '>'];
                $out[] = '<datalist id="' . $id . '-list">';
                foreach (explode(',', self::COUNTRIES) as $c) $out[] = '<option value="' . e($c) . '">';
                $out[] = '</datalist>';
                return implode('', $out);

            case 'dropdown':
                $out = ['<select id="' . $id . '" name="' . $key . '"' . $req . '>'];
                $out[] = '<option value="">' . e($f['placeholder'] ?? 'Select…') . '</option>';
                foreach ($f['options'] ?? [] as $o) {
                    $ov = (string)($o['value'] ?? '');
                    $out[] = '<option value="' . e($ov) . '"' . (in_array($ov, $list, true) ? ' selected' : '')
                           . (!empty($o['price']) ? ' data-price="' . (int)$o['price'] . '"' : '') . '>'
                           . e((string)($o['label'] ?? $ov))
                           . (!empty($o['price']) ? ' (+' . $symbol . number_format((int)$o['price']) . ')' : '')
                           . '</option>';
                }
                $out[] = '</select>';
                return implode('', $out);

            case 'choice':
            case 'multi':
                $multi = $f['type'] === 'multi';
                $out = ['<div class="fb-options' . ($multi ? ' fb-options--multi' : '') . '" role="' . ($multi ? 'group' : 'radiogroup') . '">'];
                foreach ($f['options'] ?? [] as $i => $o) {
                    $ov = (string)($o['value'] ?? '');
                    $on = $multi ? in_array($ov, $list, true) : (count($list) && $list[0] === $ov);
                    $out[] = '<label class="fb-option' . ($on ? ' is-on' : '') . '">'
                           . '<input type="' . ($multi ? 'checkbox' : 'radio') . '" name="' . $key . ($multi ? '[]' : '') . '"'
                           . ' value="' . e($ov) . '"' . ($on ? ' checked' : '')
                           . (!empty($o['price']) ? ' data-price="' . (int)$o['price'] . '"' : '')
                           . ($i === 0 ? $req : '') . '>'
                           . '<span class="fb-option__body">'
                           . '<span class="fb-option__label">' . e((string)($o['label'] ?? $ov)) . '</span>'
                           . (!empty($o['help']) ? '<span class="fb-option__help">' . e((string)$o['help']) . '</span>' : '')
                           . '</span>'
                           . (!empty($o['price']) ? '<span class="fb-option__price">+' . e($symbol . number_format((int)$o['price'])) . '</span>' : '')
                           . '</label>';
                }
                $out[] = '</div>';
                return implode('', $out);

            case 'yesno':
                $out = ['<div class="fb-options fb-options--inline" role="radiogroup">'];
                foreach ([['yes', 'Yes'], ['no', 'No']] as $i => [$ov, $ol]) {
                    $on = (string)$val === $ov;
                    $out[] = '<label class="fb-option fb-option--pill' . ($on ? ' is-on' : '') . '">'
                           . '<input type="radio" name="' . $key . '" value="' . $ov . '"' . ($on ? ' checked' : '')
                           . ($ov === 'yes' && !empty($f['price']) ? ' data-price="' . (int)$f['price'] . '"' : '')
                           . ($i === 0 ? $req : '') . '>'
                           . '<span class="fb-option__label">' . $ol . '</span></label>';
                }
                $out[] = '</div>';
                return implode('', $out);

            case 'consent':
                return '<label class="check fb-consent">'
                     . '<input type="checkbox" id="' . $id . '" name="' . $key . '" value="yes"'
                     . ((string)$val === 'yes' ? ' checked' : '') . $req . '>'
                     . '<span>' . e($f['label'] ?? '')
                     . (!empty($f['required']) ? ' <span class="req">*</span>' : '') . '</span></label>';

            case 'text':
            default:
                return '<input type="text" id="' . $id . '" name="' . $key . '" value="' . $val . '"'
                     . (isset($f['maxLen']) ? ' maxlength="' . (int)$f['maxLen'] . '"' : '')
                     . $ph . $req . '>';
        }
    }
}
