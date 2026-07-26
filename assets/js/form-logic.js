/* ================================================================
   Afrotech Academy — form logic runtime.

   Drives any form rendered by FormRenderer: conditional visibility,
   conditional requirement, per-step navigation, inline validation and
   the live fee total.

   This is a MIRROR of src/core/FormEngine.php, deliberately. The server
   is the authority — it re-resolves visibility, re-validates every
   answer and recomputes the price on submit. This file exists so the
   form feels immediate, and so a parent on a phone is never asked a
   question that doesn't apply to them. With JS off the form still
   works: every field is in the document and posts normally, and the
   server enforces the same rules.
================================================================== */
(function () {
  'use strict';

  var forms = document.querySelectorAll('form[data-form-logic]');
  if (!forms.length) return;

  Array.prototype.forEach.call(forms, function (form) { init(form); });

  function init(form) {
    var specEl = form.querySelector('[data-form-spec]');
    if (!specEl) return;
    var spec;
    try { spec = JSON.parse(specEl.textContent); } catch (e) { return; }

    var fields = spec.fields || [];
    var byKey = {};
    fields.forEach(function (f) { byKey[f.key] = f; });

    var steps = spec.steps || 1;
    var stepEls = form.querySelectorAll('[data-step]');
    var progressEls = form.querySelectorAll('[data-progress-step]');
    var backBtn = form.querySelector('[data-form-back]');
    var nextBtn = form.querySelector('[data-form-next]');
    var submitBtn = form.querySelector('[data-form-submit]');
    var totalBox = form.querySelector('[data-form-total]');
    var totalRows = form.querySelector('[data-total-rows]');
    var totalSum = form.querySelector('[data-total-sum]');
    var current = spec.open || 0;
    var visible = {};

    /* ---- reading answers -------------------------------------- */

    function valueOf(key) {
      var f = byKey[key];
      if (f && f.type === 'multi') {
        var picked = [];
        form.querySelectorAll('[name="' + cssEscape(key) + '[]"]:checked').forEach(function (i) { picked.push(i.value); });
        return picked;
      }
      var nodes = form.querySelectorAll('[name="' + cssEscape(key) + '"]');
      if (!nodes.length) return '';
      var first = nodes[0];
      if (first.type === 'radio') {
        for (var i = 0; i < nodes.length; i++) if (nodes[i].checked) return nodes[i].value;
        return '';
      }
      if (first.type === 'checkbox') return first.checked ? (first.value || 'yes') : '';
      return (first.value || '').trim();
    }

    function data() {
      var out = {};
      fields.forEach(function (f) { out[f.key] = valueOf(f.key); });
      return out;
    }

    /* ---- the operator set (mirrors FormEngine::compare) -------- */

    function compare(actual, op, expected) {
      var list = Array.isArray(actual) ? actual.map(String) : null;
      var a = list === null ? String(actual == null ? '' : actual).trim() : list.join(', ');
      var e = String(expected == null ? '' : expected).trim();
      var lcA = a.toLowerCase(), lcE = e.toLowerCase();
      var isEmpty = list === null ? a === '' : list.length === 0;
      var lcList = list === null ? [] : list.map(function (x) { return x.toLowerCase(); });

      switch (op) {
        case 'equals':       return list !== null ? lcList.indexOf(lcE) > -1 : lcA === lcE;
        case 'not_equals':   return !compare(actual, 'equals', expected);
        case 'contains':     return lcE !== '' && lcA.indexOf(lcE) > -1;
        case 'not_contains': return !(lcE !== '' && lcA.indexOf(lcE) > -1);
        case 'starts_with':  return lcE !== '' && lcA.indexOf(lcE) === 0;
        case 'ends_with':    return lcE !== '' && lcA.length >= lcE.length && lcA.slice(-lcE.length) === lcE;
        case 'gt':           return isNum(a) && isNum(e) && parseFloat(a) > parseFloat(e);
        case 'gte':          return isNum(a) && isNum(e) && parseFloat(a) >= parseFloat(e);
        case 'lt':           return isNum(a) && isNum(e) && parseFloat(a) < parseFloat(e);
        case 'lte':          return isNum(a) && isNum(e) && parseFloat(a) <= parseFloat(e);
        case 'between':
          var b = e.split(',').map(function (x) { return x.trim(); });
          if (b.length < 2 || !isNum(a) || !isNum(b[0]) || !isNum(b[1])) return false;
          var lo = Math.min(+b[0], +b[1]), hi = Math.max(+b[0], +b[1]);
          return parseFloat(a) >= lo && parseFloat(a) <= hi;
        case 'in':
        case 'not_in':
          var set = e.split(',').map(function (x) { return x.trim().toLowerCase(); }).filter(Boolean);
          var hit = list !== null
            ? lcList.some(function (x) { return set.indexOf(x) > -1; })
            : set.indexOf(lcA) > -1;
          return op === 'in' ? hit : !hit;
        case 'includes':     return list !== null ? lcList.indexOf(lcE) > -1 : lcA === lcE;
        case 'empty':        return isEmpty;
        case 'not_empty':    return !isEmpty;
        case 'checked':      return ['1', 'yes', 'true', 'on'].indexOf(lcA) > -1 || (list !== null && !isEmpty);
        case 'unchecked':    return !compare(actual, 'checked', '');
      }
      return false;
    }

    function matches(logic, d, vis) {
      var rules = logic.rules || [];
      if (!rules.length) return true;
      var all = (logic.match || 'all') === 'all';
      for (var i = 0; i < rules.length; i++) {
        var r = rules[i];
        // A rule about a hidden field can never pass — same as the server.
        var ok = (vis && vis[r.field] === false) ? false : compare(d[r.field], r.op, r.value);
        if (all && !ok) return false;
        if (!all && ok) return true;
      }
      return all;
    }

    /* ---- visibility to a fixed point -------------------------- */

    function resolveVisibility(d) {
      var vis = {};
      fields.forEach(function (f) { vis[f.key] = true; });
      for (var pass = 0; pass < 12; pass++) {
        var changed = false;
        fields.forEach(function (f) {
          if (!f.logic) return;
          var m = matches(f.logic, d, vis);
          var show = f.logic.action === 'show' ? m : !m;
          if (vis[f.key] !== show) { vis[f.key] = show; changed = true; }
        });
        if (!changed) break;
      }
      return vis;
    }

    function isRequired(f, d, vis) {
      if (f.requiredIf) {
        var m = matches(f.requiredIf, d, vis);
        return f.requiredIf.action === 'require' ? m : !m;
      }
      return !!f.required;
    }

    /* ---- painting --------------------------------------------- */

    function apply() {
      var d = data();
      visible = resolveVisibility(d);

      fields.forEach(function (f) {
        var wrap = form.querySelector('[data-field="' + cssEscape(f.key) + '"]');
        if (!wrap) return;
        var show = visible[f.key] !== false;
        if (wrap.hasAttribute('hidden') === show) {
          if (show) wrap.removeAttribute('hidden'); else wrap.setAttribute('hidden', '');
        }
        // A hidden field must not block submission, and must not carry a
        // stale error from a branch the parent has since left.
        if (!show) clearError(wrap);
        var req = show && isRequired(f, d, visible);
        var star = wrap.querySelector('.fb-when');
        if (star) star.style.display = req ? '' : 'none';
        // Disabling a hidden field keeps it out of the POST body entirely —
        // belt to the server's braces, which discards it anyway.
        inputs(f.key).forEach(function (el) {
          el.disabled = !show;
          el.required = false;   // requirement is ours to decide, not the browser's
        });
      });

      // Option cards reflect their own checked state.
      form.querySelectorAll('.fb-option').forEach(function (label) {
        var input = label.querySelector('input');
        if (input) label.classList.toggle('is-on', input.checked);
      });

      paintTotal(d);
      paintSteps();
    }

    function paintTotal(d) {
      if (!totalBox || !totalSum) return;
      var base = Number(spec.fee || 0);
      var rows = [];
      var addons = 0;

      fields.forEach(function (f) {
        if (visible[f.key] === false) return;
        var v = d[f.key];
        var empty = Array.isArray(v) ? !v.length : (v === '' || v === 'no');
        if (empty) return;
        if (f.price) { addons += Number(f.price); rows.push([f.label || f.key, Number(f.price)]); }
        if (f.options && f.options.length) {
          var picked = Array.isArray(v) ? v.map(String) : [String(v)];
          f.options.forEach(function (o) {
            if (!o.price) return;
            if (picked.indexOf(String(o.value)) > -1) {
              addons += Number(o.price);
              rows.push([labelForOption(f.key, o.value), Number(o.price)]);
            }
          });
        }
      });

      var sym = spec.symbol || '₦';
      if (totalRows) {
        totalRows.innerHTML = ['<div class="fb-total__row"><span>Programme fee</span><span>' + money(sym, base) + '</span></div>']
          .concat(rows.map(function (r) {
            return '<div class="fb-total__row"><span>' + escapeHtml(r[0]) + '</span><span>+' + money(sym, r[1]) + '</span></div>';
          })).join('');
      }
      totalSum.textContent = money(sym, base + addons);
      if (base + addons > 0) totalBox.removeAttribute('hidden');
    }

    function labelForOption(key, value) {
      var input = form.querySelector('[name="' + cssEscape(key) + '"][value="' + cssEscape(String(value)) + '"], [name="' + cssEscape(key) + '[]"][value="' + cssEscape(String(value)) + '"]');
      if (input) {
        var lbl = input.closest('.fb-option');
        var txt = lbl && lbl.querySelector('.fb-option__label');
        if (txt) return txt.textContent.trim();
        if (input.tagName === 'OPTION') return input.textContent.trim();
      }
      var opt = form.querySelector('[name="' + cssEscape(key) + '"] option[value="' + cssEscape(String(value)) + '"]');
      return opt ? opt.textContent.replace(/\s*\(\+.*\)$/, '').trim() : String(value);
    }

    /* ---- steps ------------------------------------------------ */

    function paintSteps() {
      if (steps <= 1) return;
      Array.prototype.forEach.call(stepEls, function (el) {
        var s = Number(el.getAttribute('data-step'));
        if (s === current) el.removeAttribute('hidden'); else el.setAttribute('hidden', '');
      });
      Array.prototype.forEach.call(progressEls, function (el) {
        var s = Number(el.getAttribute('data-progress-step'));
        el.classList.toggle('is-current', s === current);
        el.classList.toggle('is-done', s < current);
      });
      if (backBtn) { if (current > 0) backBtn.removeAttribute('hidden'); else backBtn.setAttribute('hidden', ''); }
      var last = current >= steps - 1;
      if (nextBtn) { if (last) nextBtn.setAttribute('hidden', ''); else nextBtn.removeAttribute('hidden'); }
      if (submitBtn) { if (last) submitBtn.removeAttribute('hidden'); else submitBtn.setAttribute('hidden', ''); }
    }

    function go(to) {
      current = Math.max(0, Math.min(steps - 1, to));
      paintSteps();
      var top = form.getBoundingClientRect().top + window.pageYOffset - 90;
      window.scrollTo({ top: top, behavior: 'smooth' });
    }

    if (nextBtn) nextBtn.addEventListener('click', function () {
      if (!validateStep(current)) return;
      go(current + 1);
    });
    if (backBtn) backBtn.addEventListener('click', function () { go(current - 1); });

    /* ---- validation ------------------------------------------- */

    function validateStep(step) {
      var d = data();
      var firstBad = null;
      fields.forEach(function (f) {
        if (f.step !== step) return;
        if (visible[f.key] === false) return;
        var wrap = form.querySelector('[data-field="' + cssEscape(f.key) + '"]');
        if (!wrap) return;
        var msg = checkField(f, d[f.key], d);
        if (msg) { showError(wrap, msg); if (!firstBad) firstBad = wrap; }
        else clearError(wrap);
      });
      if (firstBad) {
        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var input = firstBad.querySelector('input, select, textarea');
        if (input) input.focus({ preventScroll: true });
        return false;
      }
      return true;
    }

    function checkField(f, v, d) {
      var empty = Array.isArray(v) ? !v.length : String(v || '').trim() === '';
      if (empty) {
        return isRequired(f, d, visible)
          ? (f.requiredMessage || ((f.label || 'This') + ' is required.'))
          : null;
      }
      var s = Array.isArray(v) ? '' : String(v);
      switch (f.type) {
        case 'email':
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(s)) return "That email doesn't look right.";
          break;
        case 'phone':
          if (!/^[\d\s+\-()]{6,20}$/.test(s)) return "That phone number doesn't look right.";
          break;
        case 'url':
          if (!/^https?:\/\/[^\s.]+\.[^\s]+$/.test(s)) return 'Enter a full link, starting with https://';
          break;
        case 'number':
        case 'scale':
          if (!isNum(s)) return (f.label || 'This') + ' must be a number.';
          if (f.min != null && parseFloat(s) < f.min) return (f.label || 'This') + ' must be at least ' + f.min + '.';
          if (f.max != null && parseFloat(s) > f.max) return (f.label || 'This') + ' must be ' + f.max + ' or below.';
          break;
        case 'multi':
          if (f.minSelect != null && v.length < f.minSelect) return 'Pick at least ' + f.minSelect + '.';
          if (f.maxSelect != null && v.length > f.maxSelect) return 'Pick no more than ' + f.maxSelect + '.';
          break;
        case 'consent':
          if (s !== 'yes') return f.requiredMessage || 'Please tick this to continue.';
          break;
      }
      if (f.minLen != null && s.length < f.minLen) return (f.label || 'This') + ' should be at least ' + f.minLen + ' characters.';
      if (f.maxLen != null && s.length > f.maxLen) return (f.label || 'This') + ' is too long (max ' + f.maxLen + ').';
      if (f.pattern) {
        try { if (!new RegExp(f.pattern, 'u').test(s)) return f.patternMessage || ((f.label || 'This') + " isn't in the expected format."); }
        catch (e) { /* an expression the browser can't compile is left to the server */ }
      }
      return null;
    }

    function showError(wrap, msg) {
      wrap.classList.add('has-error');
      var slot = wrap.querySelector('.err');
      if (slot) slot.textContent = msg;
    }
    function clearError(wrap) {
      wrap.classList.remove('has-error');
      var slot = wrap.querySelector('.err');
      if (slot) slot.textContent = '';
    }

    /* ---- wiring ----------------------------------------------- */

    function inputs(key) {
      return Array.prototype.slice.call(
        form.querySelectorAll('[name="' + cssEscape(key) + '"], [name="' + cssEscape(key) + '[]"]')
      );
    }

    form.addEventListener('input', onChange);
    form.addEventListener('change', onChange);

    function onChange(ev) {
      apply();
      // Re-check a field the moment it stops being wrong, never before —
      // typing the first letter of an email shouldn't paint it red.
      var t = ev.target;
      if (!t || !t.name) return;
      var wrap = t.closest('[data-field]');
      if (wrap && wrap.classList.contains('has-error')) {
        var key = wrap.getAttribute('data-field');
        var f = byKey[key];
        if (f && !checkField(f, valueOf(key), data())) clearError(wrap);
      }
    }

    // Final gate: every visible step must pass before the POST goes out.
    // app.js owns the actual submit (it handles the JSON response), so we
    // only cancel here — capture phase, so we run first.
    form.addEventListener('submit', function (ev) {
      var bad = -1;
      for (var s = 0; s < steps; s++) {
        if (!validateStep(s)) { bad = s; break; }
      }
      if (bad > -1) {
        ev.preventDefault();
        ev.stopImmediatePropagation();
        if (bad !== current) go(bad);
        var box = form.querySelector('[data-form-alert]');
        if (box) {
          box.className = '';
          box.innerHTML = '<div class="alert alert--err">Please check the highlighted fields.</div>';
        }
      }
    }, true);

    /* ---- helpers ---------------------------------------------- */

    function isNum(v) { return v !== '' && v !== null && isFinite(Number(v)); }
    function money(sym, n) { return sym + Number(n).toLocaleString('en-NG'); }
    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    // Field keys are [A-Za-z0-9_] server-side, but a definition edited by
    // hand shouldn't be able to break a selector.
    function cssEscape(s) {
      return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/["\\\]]/g, '\\$&');
    }

    apply();
    paintSteps();
  }
})();
