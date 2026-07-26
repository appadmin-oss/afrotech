/* ================================================================
   Afrotech Academy — form builder (operator console).

   A dependency-free editor for the JSON definition behind /summer.
   It owns four panes:

     Build      the block list — add, reorder, duplicate, delete, edit
     Logic map  every conditional rule in one readable list
     Settings   form-level copy and behaviour
     Preview    the REAL page in an iframe, so it can't drift from live

   Nothing here is authoritative. Save and Publish post the definition to
   Admin\FormsController, which re-runs FormEngine::normalizeFields and
   refuses anything that would break the public form. The client-side
   checks below exist to catch mistakes early and explain them in place.
================================================================== */
(function () {
  'use strict';

  var root = document.querySelector('[data-builder]');
  var boot = document.getElementById('fbx-bootstrap');
  if (!root || !boot) return;

  var B;
  try { B = JSON.parse(boot.textContent); } catch (e) { return; }

  var cat = B.catalogue;
  var LAYOUT = cat.layoutTypes;
  var OPTION_TYPES = cat.optionTypes;
  var canEdit = !!B.canManage;

  // Working state. `_id` is a client-only handle so reordering doesn't depend
  // on keys, which the operator is still editing.
  var fields = (B.fields || []).map(decorate);
  var settings = Object.assign({}, B.settings);
  var formName = B.name || 'Form';
  var dirty = false;
  var openId = null;

  var els = {
    list: root.querySelector('[data-fbx-list]'),
    add: root.querySelector('[data-fbx-add]'),
    name: root.querySelector('[data-fbx-name]'),
    count: root.querySelector('[data-fbx-count]'),
    state: root.querySelector('[data-fbx-state]'),
    msg: root.querySelector('[data-fbx-msg]'),
    logicmap: root.querySelector('[data-fbx-logicmap]'),
    settings: root.querySelector('[data-fbx-settings]'),
    frame: root.querySelector('[data-fbx-frame]'),
    frameNote: root.querySelector('[data-fbx-preview-note]'),
    save: root.querySelector('[data-fbx-save]'),
    publish: root.querySelector('[data-fbx-publish]')
  };

  /* ---- helpers ------------------------------------------------- */

  function uid() { return 'f' + Math.random().toString(36).slice(2, 9); }
  function decorate(f) { var c = Object.assign({}, f); c._id = uid(); return c; }
  function isLayout(t) { return LAYOUT.indexOf(t) > -1; }
  function hasOptions(t) { return OPTION_TYPES.indexOf(t) > -1; }
  function byId(id) { for (var i = 0; i < fields.length; i++) if (fields[i]._id === id) return fields[i]; return null; }
  function indexOf(id) { for (var i = 0; i < fields.length; i++) if (fields[i]._id === id) return i; return -1; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function keyFromLabel(label, type, i) {
    var k = String(label || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
    if (!k || /^\d/.test(k)) k = (type || 'field') + '_' + (i + 1);
    return k.slice(0, 40);
  }
  function money(n) { return B.symbol + Number(n || 0).toLocaleString('en-NG'); }

  function mark() { dirty = true; paintBar(); }

  function message(text, kind) {
    if (!els.msg) return;
    els.msg.className = 'fbx__msg fbx__msg--' + (kind || 'ok');
    els.msg.textContent = text;
    els.msg.removeAttribute('hidden');
    if (kind !== 'err') {
      clearTimeout(message._t);
      message._t = setTimeout(function () { els.msg.setAttribute('hidden', ''); }, 4000);
    }
  }

  /* ---- validation mirrors the server's refusals ---------------- */

  function problems() {
    var out = [];
    var seenKeys = {};
    var above = {};
    var mapped = {};

    fields.forEach(function (f, i) {
      var key = f.key || keyFromLabel(f.label, f.type, i);
      if (!isLayout(f.type)) {
        if (seenKeys[key]) out.push('Two fields share the key "' + key + '" — rename one.');
        seenKeys[key] = true;
      }
      if (hasOptions(f.type) && !f.optionsFrom && !(f.options || []).length) {
        out.push('"' + (f.label || key) + '" is a choice field with no options.');
      }
      ['logic', 'requiredIf'].forEach(function (slot) {
        ((f[slot] || {}).rules || []).forEach(function (r) {
          if (!r.field) out.push('"' + (f.label || key) + '" has a rule with no field chosen.');
          else if (!above[r.field]) out.push('"' + (f.label || key) + '" has a rule about "' + r.field + '", which is not above it in the form.');
        });
      });
      if (f.map) {
        if (mapped[f.map]) out.push('Two fields both save to "' + cat.mappable[f.map] + '".');
        mapped[f.map] = true;
      }
      if (!isLayout(f.type)) above[key] = true;
    });

    (cat.requiredMaps || []).forEach(function (m) {
      if (!mapped[m]) out.push('No field saves to "' + cat.mappable[m] + '" — the registration record needs it.');
    });
    return out;
  }

  /* ---- serialising for the wire -------------------------------- */

  function payload() {
    return fields.map(function (f, i) {
      var o = { type: f.type, label: f.label || '' };
      if (!isLayout(f.type)) {
        o.key = f.key || keyFromLabel(f.label, f.type, i);
        ['required', 'sensitive', 'locked'].forEach(function (k) { if (f[k]) o[k] = true; });
        ['help', 'placeholder', 'requiredMessage', 'patternMessage', 'width', 'map', 'default',
         'prefill', 'pattern', 'accept', 'priceLabel']
          .forEach(function (k) { if (f[k]) o[k] = f[k]; });
        ['min', 'max', 'minLen', 'maxLen', 'minSelect', 'maxSelect', 'rows', 'price']
          .forEach(function (k) { if (f[k] !== '' && f[k] != null) o[k] = Number(f[k]); });
        if (hasOptions(f.type)) {
          if (f.optionsFrom) o.optionsFrom = f.optionsFrom;
          else o.options = (f.options || []).map(function (opt) {
            var r = { value: opt.value || opt.label, label: opt.label || opt.value };
            if (opt.price) r.price = Number(opt.price);
            if (opt.help) r.help = opt.help;
            return r;
          });
        }
      } else if (f.help) o.help = f.help;
      ['logic', 'requiredIf'].forEach(function (slot) {
        var g = f[slot];
        if (g && (g.rules || []).length) {
          o[slot] = {
            action: g.action,
            match: g.match || 'all',
            rules: g.rules.filter(function (r) { return r.field; }).map(function (r) {
              var rr = { field: r.field, op: r.op || 'equals' };
              if ((cat.operators[r.op] || {}).needsValue) rr.value = r.value || '';
              return rr;
            })
          };
          if (!o[slot].rules.length) delete o[slot];
        }
      });
      return o;
    });
  }

  /* ---- network ------------------------------------------------- */

  function post(url, extra) {
    var body = new URLSearchParams();
    body.set('_csrf', B.csrf);
    body.set('form', B.formKey);
    body.set('name', formName);
    body.set('fields', JSON.stringify(payload()));
    body.set('settings', JSON.stringify(settings));
    Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
    return fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': B.csrf },
      body: body
    }).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, body: j }; })
        .catch(function () { return { ok: false, body: { message: 'Unexpected response from the server.' } }; });
    });
  }

  function saveDraft(quiet) {
    var errs = problems();
    if (errs.length) { message(errs[0], 'err'); return Promise.resolve(false); }
    if (els.save) { els.save.disabled = true; els.save.textContent = 'Saving…'; }
    return post(B.endpoints.draft).then(function (res) {
      if (res.ok && res.body.ok) {
        dirty = false;
        if (!quiet) message('Draft saved. It is not live until you publish.', 'ok');
        paintBar('unpublished draft');
        return true;
      }
      message(res.body.message || 'Could not save the draft.', 'err');
      return false;
    }).catch(function () {
      message('Network error — the draft was not saved.', 'err');
      return false;
    }).finally(function () {
      if (els.save) { els.save.disabled = false; els.save.textContent = 'Save draft'; }
    });
  }

  function publish() {
    var errs = problems();
    if (errs.length) { message(errs[0], 'err'); return; }
    if (!window.confirm('Publish this form? It replaces what parents see on /summer immediately.')) return;
    els.publish.disabled = true;
    els.publish.textContent = 'Publishing…';
    post(B.endpoints.publish).then(function (res) {
      if (res.ok && res.body.ok) {
        dirty = false;
        message(res.body.message || 'Published.', 'ok');
        paintBar('live · v' + res.body.version);
        // Reload so the version table and the "live vN" chip are truthful.
        setTimeout(function () { window.location.reload(); }, 900);
        return;
      }
      message(res.body.message || 'Publish failed — nothing changed.', 'err');
    }).catch(function () {
      message('Network error — nothing was published.', 'err');
    }).finally(function () {
      els.publish.disabled = false;
      els.publish.textContent = 'Publish';
    });
  }

  /* ---- rendering: the block list ------------------------------- */

  function paintBar(stateText) {
    if (els.count) els.count.textContent = String(fields.length);
    if (els.state) els.state.textContent = stateText || (dirty ? 'unsaved changes' : (B.hasDraft ? 'unpublished draft' : 'in sync with live'));
    if (els.publish) els.publish.classList.toggle('is-hot', dirty);
  }

  function fieldsAbove(id) {
    var out = [];
    for (var i = 0; i < fields.length; i++) {
      if (fields[i]._id === id) break;
      if (!isLayout(fields[i].type)) {
        out.push({ key: fields[i].key || keyFromLabel(fields[i].label, fields[i].type, i), label: fields[i].label || fields[i].key });
      }
    }
    return out;
  }

  function paintList() {
    if (!els.list) return;
    if (!fields.length) {
      els.list.innerHTML = '<div class="fbx__empty">No blocks yet. Add one below.</div>';
      return;
    }
    var step = 1;
    var html = fields.map(function (f, i) {
      var open = f._id === openId;
      var key = f.key || keyFromLabel(f.label, f.type, i);
      var chips = [];
      if (f.locked) chips.push('<span class="fbx__chip fbx__chip--lock" title="Core field — the intake record needs it">locked</span>');
      if (f.required) chips.push('<span class="fbx__chip">required</span>');
      if (f.requiredIf) chips.push('<span class="fbx__chip fbx__chip--logic">conditionally required</span>');
      if (f.logic) chips.push('<span class="fbx__chip fbx__chip--logic">' + esc(f.logic.action) + ' if ' + f.logic.rules.length + ' rule' + (f.logic.rules.length > 1 ? 's' : '') + '</span>');
      if (f.sensitive) chips.push('<span class="fbx__chip fbx__chip--warn">sensitive</span>');
      if (f.map) chips.push('<span class="fbx__chip">→ ' + esc(cat.mappable[f.map] || f.map) + '</span>');
      if (f.price) chips.push('<span class="fbx__chip fbx__chip--price">+' + esc(money(f.price)) + '</span>');
      if (f.optionsFrom) chips.push('<span class="fbx__chip">options: ' + esc(cat.sources[f.optionsFrom] || f.optionsFrom) + '</span>');

      var head = f.type === 'step'
        ? '<div class="fbx__row-title">Page break <span class="mono muted">→ step ' + (++step) + '</span></div>'
        : '<div class="fbx__row-title">' + esc(f.label || key) + '</div>';

      return '<div class="fbx__row' + (open ? ' is-open' : '') + (f.type === 'step' ? ' fbx__row--step' : '') + '" data-id="' + f._id + '">'
        + '<div class="fbx__row-rail">'
        +   '<button class="fbx__mv" data-mv="-1" title="Move up"' + (i === 0 ? ' disabled' : '') + '>↑</button>'
        +   '<button class="fbx__mv" data-mv="1" title="Move down"' + (i === fields.length - 1 ? ' disabled' : '') + '>↓</button>'
        + '</div>'
        + '<div class="fbx__row-body">'
        +   '<div class="fbx__row-head" data-toggle>'
        +     head
        +     '<div class="fbx__row-sub mono">' + esc(typeLabel(f.type)) + (isLayout(f.type) ? '' : ' · ' + esc(key)) + '</div>'
        +     (chips.length ? '<div class="fbx__chips">' + chips.join('') + '</div>' : '')
        +   '</div>'
        +   (open ? '<div class="fbx__editor">' + editor(f, i) + '</div>' : '')
        + '</div>'
        + '<div class="fbx__row-actions">'
        +   '<button class="fbx__mv" data-dup title="Duplicate">⧉</button>'
        +   (f.locked ? '' : '<button class="fbx__mv fbx__mv--del" data-del title="Remove">✕</button>')
        + '</div>'
        + '</div>';
    }).join('');
    els.list.innerHTML = html;
  }

  function typeLabel(t) {
    for (var i = 0; i < cat.types.length; i++) if (cat.types[i].value === t) return cat.types[i].label;
    return t;
  }

  /* ---- rendering: one block's editor -------------------------- */

  function editor(f, i) {
    var key = f.key || keyFromLabel(f.label, f.type, i);
    var h = [];

    h.push(grid([
      text('label', 'Label', f.label || '', 'What the parent reads'),
      select('type', 'Type', f.type, cat.types.map(function (t) { return [t.value, t.label]; }), f.locked)
    ]));

    if (!isLayout(f.type)) {
      h.push(grid([
        text('key', 'Field key', key, 'letters, numbers, underscore', f.locked),
        select('map', 'Save into', f.map || '', [['', "Keep as a form answer"]].concat(
          Object.keys(cat.mappable).map(function (m) { return [m, cat.mappable[m]]; })), f.locked)
      ]));
      h.push(grid([
        text('placeholder', 'Placeholder', f.placeholder || ''),
        select('width', 'Width', f.width || 'full', [['full', 'Full row'], ['half', 'Half row']])
      ]));
      h.push(text('help', 'Helper text', f.help || '', 'Shown under the field'));

      h.push('<div class="fbx__toggles">'
        + toggle('required', 'Always required', !!f.required)
        + toggle('sensitive', 'Sensitive — console only, never emailed or exported', !!f.sensitive)
        + '</div>');
      h.push(grid([
        text('requiredMessage', 'Custom "required" message', f.requiredMessage || ''),
        num('price', 'Flat add-on price (' + B.symbol + ')', f.price),
        // Always rendered rather than revealed once a price exists: showing it
        // conditionally would mean re-rendering the editor mid-keystroke, which
        // takes the caret out of the price box.
        text('priceLabel', 'Receipt line item', f.priceLabel || '',
             'used when priced — e.g. "Campus shuttle pick-up"')
      ]));
    } else if (f.type !== 'step') {
      h.push(text('help', 'Supporting text', f.help || ''));
    }

    // ---- per-type knobs ----
    if (f.type === 'number' || f.type === 'scale') {
      h.push(grid([num('min', 'Minimum', f.min), num('max', 'Maximum', f.max)]));
    }
    if (f.type === 'text' || f.type === 'longtext' || f.type === 'url') {
      h.push(grid([num('minLen', 'Min characters', f.minLen), num('maxLen', 'Max characters', f.maxLen)]));
    }
    if (f.type === 'longtext') h.push(grid([num('rows', 'Rows', f.rows)]));
    if (f.type === 'multi') {
      h.push(grid([num('minSelect', 'Pick at least', f.minSelect), num('maxSelect', 'Pick at most', f.maxSelect)]));
    }
    if (f.type === 'text' || f.type === 'phone' || f.type === 'url') {
      h.push(grid([
        text('pattern', 'Must match pattern (regex)', f.pattern || '', 'e.g. ^[A-Z]{3}[0-9]{4}$'),
        text('patternMessage', 'Message when it does not match', f.patternMessage || '')
      ]));
    }
    if (f.type === 'file') h.push(grid([text('accept', 'Accepted types', f.accept || '', 'e.g. .pdf,image/*')]));
    if (f.type === 'hidden') h.push(grid([text('prefill', 'Capture query parameter', f.prefill || '', 'e.g. utm_source')]));
    if (!isLayout(f.type) && f.type !== 'hidden') h.push(grid([text('default', 'Default value', f.default || '')]));

    // ---- options ----
    if (hasOptions(f.type)) {
      h.push('<div class="fbx__block">');
      h.push('<div class="fbx__block-head">Options</div>');
      h.push(grid([select('optionsFrom', 'Option source', f.optionsFrom || '',
        [['', 'Type them below']].concat(Object.keys(cat.sources).map(function (s) { return [s, cat.sources[s]]; })))]));
      if (!f.optionsFrom) {
        h.push('<div class="fbx__opts">');
        (f.options || []).forEach(function (o, oi) {
          h.push('<div class="fbx__opt" data-opt="' + oi + '">'
            + '<input class="fbx__in" data-opt-field="label" value="' + esc(o.label || '') + '" placeholder="Label">'
            + '<input class="fbx__in fbx__in--sm" data-opt-field="value" value="' + esc(o.value || '') + '" placeholder="Stored value">'
            + '<input class="fbx__in fbx__in--xs" data-opt-field="price" type="number" value="' + esc(o.price || '') + '" placeholder="+' + B.symbol + '">'
            + '<button class="fbx__mv fbx__mv--del" data-opt-del title="Remove option">✕</button>'
            + '</div>');
        });
        h.push('</div><button class="btn btn--ghost btn--sm" data-opt-add>+ Add option</button>');
      } else {
        h.push('<p class="fbx__note mono muted">Options come from ' + esc(cat.sources[f.optionsFrom]) + ' at render time — edit them there and this field follows.</p>');
      }
      h.push('</div>');
    }

    // ---- logic ----
    if (!isLayout(f.type) || f.type === 'section' || f.type === 'info') {
      h.push(logicEditor(f, 'logic', 'Show / hide this block', [['show', 'Show it only when'], ['hide', 'Hide it when']]));
      if (!isLayout(f.type)) {
        h.push(logicEditor(f, 'requiredIf', 'Require an answer conditionally', [['require', 'Require it when'], ['optional', 'Make it optional when']]));
      }
    }

    return h.join('');
  }

  function logicEditor(f, slot, title, actions) {
    var g = f[slot];
    var above = fieldsAbove(f._id);
    var h = ['<div class="fbx__block" data-logic="' + slot + '">'];
    h.push('<div class="fbx__block-head">' + title
      + (g ? '' : ' <button class="btn btn--ghost btn--sm" data-logic-add>+ Add rule</button>') + '</div>');

    if (!above.length) {
      h.push('<p class="fbx__note mono muted">Nothing above this block to test yet — rules can only look upward.</p></div>');
      return h.join('');
    }
    if (!g) { h.push('</div>'); return h.join(''); }

    h.push('<div class="fbx__logicline">');
    h.push(sel('action', g.action, actions));
    h.push(sel('match', g.match || 'all', [['all', 'ALL of these are true'], ['any', 'ANY of these is true']]));
    h.push('</div>');

    (g.rules || []).forEach(function (r, ri) {
      var needsValue = (cat.operators[r.op] || {}).needsValue;
      h.push('<div class="fbx__rule" data-rule="' + ri + '">');
      h.push(sel('field', r.field || '', [['', 'choose a field…']].concat(above.map(function (a) { return [a.key, a.label + '  (' + a.key + ')']; })), 'rule-field'));
      h.push(sel('op', r.op || 'equals', Object.keys(cat.operators).map(function (o) { return [o, cat.operators[o].label]; }), 'rule-op'));
      h.push(needsValue
        ? '<input class="fbx__in" data-rule-field="value" value="' + esc(r.value || '') + '" placeholder="value">'
        : '<span class="fbx__note mono muted">no value needed</span>');
      h.push('<button class="fbx__mv fbx__mv--del" data-rule-del title="Remove rule">✕</button>');
      h.push('</div>');
    });
    h.push('<button class="btn btn--ghost btn--sm" data-logic-add>+ Add another rule</button>');
    h.push('<button class="btn btn--ghost btn--sm" data-logic-clear>Remove all rules</button>');
    h.push('</div>');
    return h.join('');

    function sel(name, value, pairs, cls) {
      return '<select class="fbx__in ' + (cls || '') + '" data-' + (cls === 'rule-field' || cls === 'rule-op' ? 'rule-field="' + name + '"' : 'logic-field="' + name + '"') + '>'
        + pairs.map(function (p) {
            return '<option value="' + esc(p[0]) + '"' + (String(p[0]) === String(value) ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
          }).join('')
        + '</select>';
    }
  }

  /* ---- small input builders ----------------------------------- */

  function grid(parts) { return '<div class="fbx__grid">' + parts.join('') + '</div>'; }

  function text(name, label, value, hint, disabled) {
    return '<label class="fbx__lab">' + esc(label)
      + '<input class="fbx__in" data-field="' + name + '" value="' + esc(value) + '"'
      + (disabled ? ' disabled title="Core field"' : '') + '>'
      + (hint ? '<span class="fbx__hint mono">' + esc(hint) + '</span>' : '') + '</label>';
  }
  function num(name, label, value) {
    return '<label class="fbx__lab">' + esc(label)
      + '<input class="fbx__in" type="number" data-field="' + name + '" value="' + (value == null || value === '' ? '' : esc(value)) + '"></label>';
  }
  function select(name, label, value, pairs, disabled) {
    return '<label class="fbx__lab">' + esc(label)
      + '<select class="fbx__in" data-field="' + name + '"' + (disabled ? ' disabled' : '') + '>'
      + pairs.map(function (p) {
          return '<option value="' + esc(p[0]) + '"' + (String(p[0]) === String(value) ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
        }).join('')
      + '</select></label>';
  }
  function toggle(name, label, on) {
    return '<label class="fbx__toggle"><input type="checkbox" data-field="' + name + '"' + (on ? ' checked' : '') + '> ' + esc(label) + '</label>';
  }

  /* ---- add-block palette -------------------------------------- */

  function paintPalette() {
    if (!els.add) return;
    els.add.innerHTML = cat.types.map(function (t) {
      return '<button class="btn btn--ghost btn--sm" data-add="' + esc(t.value) + '">+ ' + esc(t.label) + '</button>';
    }).join('');
  }

  function addField(type) {
    var f = decorate({
      type: type,
      label: type === 'section' ? 'New section'
        : type === 'info' ? 'Something worth saying here'
        : type === 'step' ? 'Page break'
        : type === 'divider' ? ''
        : 'New question',
      key: ''
    });
    if (hasOptions(type)) f.options = [{ value: 'option_1', label: 'Option 1' }];
    if (!isLayout(type)) f.key = keyFromLabel(f.label, type, fields.length);
    fields.push(f);
    openId = f._id;
    mark();
    paintAll();
    var row = els.list.querySelector('[data-id="' + f._id + '"]');
    if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  /* ---- logic map pane ----------------------------------------- */

  function paintLogicMap() {
    if (!els.logicmap) return;
    var rows = [];
    fields.forEach(function (f, i) {
      var key = f.key || keyFromLabel(f.label, f.type, i);
      ['logic', 'requiredIf'].forEach(function (slot) {
        var g = f[slot];
        if (!g || !(g.rules || []).length) return;
        var verb = slot === 'logic'
          ? (g.action === 'show' ? 'is shown' : 'is hidden')
          : (g.action === 'require' ? 'becomes required' : 'becomes optional');
        rows.push('<div class="fbx__mapline">'
          + '<b>' + esc(f.label || key) + '</b> ' + verb
          + ' when <em>' + (g.match === 'any' ? 'any' : 'all') + '</em> of: '
          + g.rules.map(function (r) {
              // The dropdown labels carry a format hint — "is one of (a,b,c)" —
              // which is noise once the rule is written out as a sentence.
              var op = ((cat.operators[r.op] || {}).label || r.op).replace(/\s*\([^)]*\)\s*$/, '');
              return '<span class="fbx__maprule">' + esc(r.field) + ' ' + esc(op)
                + ((cat.operators[r.op] || {}).needsValue ? ' “' + esc(r.value || '') + '”' : '') + '</span>';
            }).join(' · ')
          + '</div>');
      });
    });
    els.logicmap.innerHTML = rows.length
      ? rows.join('')
      : '<div class="fbx__empty">No conditional rules yet. Open a field and add one under “Show / hide this block”.</div>';
  }

  /* ---- settings pane ------------------------------------------ */

  var SETTING_FIELDS = [
    ['introTitle', 'Heading above the form', 'text'],
    ['introBody', 'Intro paragraph', 'area'],
    ['submitLabel', 'Submit button label', 'text'],
    ['successTitle', 'Success heading', 'text'],
    ['successMessage', 'Success message (blank = auto)', 'area'],
    ['closedMessage', 'Message when registration has closed', 'area'],
    ['showProgress', 'Show the step progress rail', 'bool'],
    ['showPrice', 'Show the running fee total', 'bool']
  ];

  function paintSettings() {
    if (!els.settings) return;
    els.settings.innerHTML = SETTING_FIELDS.map(function (s) {
      var k = s[0], label = s[1], kind = s[2], v = settings[k];
      if (kind === 'bool') {
        return '<label class="fbx__toggle"><input type="checkbox" data-setting="' + k + '"' + (v ? ' checked' : '') + '> ' + esc(label) + '</label>';
      }
      if (kind === 'area') {
        return '<label class="fbx__lab">' + esc(label) + '<textarea class="fbx__in" rows="2" data-setting="' + k + '">' + esc(v || '') + '</textarea></label>';
      }
      return '<label class="fbx__lab">' + esc(label) + '<input class="fbx__in" data-setting="' + k + '" value="' + esc(v || '') + '"></label>';
    }).join('');
  }

  /* ---- panes -------------------------------------------------- */

  function showPane(name) {
    root.querySelectorAll('[data-fbx-pane]').forEach(function (p) {
      if (p.getAttribute('data-fbx-pane') === name) p.removeAttribute('hidden');
      else p.setAttribute('hidden', '');
    });
    root.querySelectorAll('[data-fbx-tab]').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-fbx-tab') === name);
    });
    if (name === 'logic') paintLogicMap();
    if (name === 'settings') paintSettings();
    if (name === 'preview') loadPreview();
  }

  function loadPreview() {
    if (!els.frame) return;
    var which = canEdit ? 'draft' : 'live';
    var show = function () {
      // A cache-buster, or the browser serves the previous draft.
      els.frame.src = B.endpoints.preview + '&which=' + which + '&t=' + Date.now();
      if (els.frameNote) els.frameNote.textContent = which === 'draft'
        ? 'Previewing your saved draft — publish to make it live.'
        : 'Previewing the live form.';
    };
    if (!canEdit) return show();
    if (els.frameNote) els.frameNote.textContent = 'Saving the draft, then loading it…';
    saveDraft(true).then(function (ok) {
      if (ok) show();
      else if (els.frameNote) els.frameNote.textContent = 'Fix the problem above, then reopen Preview.';
    });
  }

  function paintAll() { paintList(); paintBar(); }

  /* ---- event wiring ------------------------------------------- */

  root.addEventListener('click', function (ev) {
    var t = ev.target;

    var tab = t.closest('[data-fbx-tab]');
    if (tab) { showPane(tab.getAttribute('data-fbx-tab')); return; }

    if (t.closest('[data-fbx-save]')) { saveDraft(); return; }
    if (t.closest('[data-fbx-publish]')) { publish(); return; }

    var add = t.closest('[data-add]');
    if (add) { if (guard()) addField(add.getAttribute('data-add')); return; }

    var row = t.closest('.fbx__row');
    if (!row) return;
    var id = row.getAttribute('data-id');
    var f = byId(id);
    if (!f) return;

    if (t.closest('[data-toggle]')) { openId = (openId === id) ? null : id; paintList(); return; }
    if (!guard()) return;

    var mv = t.closest('[data-mv]');
    if (mv) {
      var dir = Number(mv.getAttribute('data-mv'));
      var i = indexOf(id), j = i + dir;
      if (j >= 0 && j < fields.length) {
        var tmp = fields[i]; fields[i] = fields[j]; fields[j] = tmp;
        mark(); paintList();
      }
      return;
    }
    if (t.closest('[data-dup]')) {
      var copy = decorate(JSON.parse(JSON.stringify(stripId(f))));
      if (!isLayout(copy.type)) copy.key = (copy.key || 'field') + '_copy';
      copy.locked = false;
      // A duplicate must not fight the original for the same record column.
      delete copy.map;
      fields.splice(indexOf(id) + 1, 0, copy);
      openId = copy._id;
      mark(); paintList();
      return;
    }
    if (t.closest('[data-del]')) {
      if (!window.confirm('Remove "' + (f.label || f.key) + '"? Answers already collected keep their record.')) return;
      fields.splice(indexOf(id), 1);
      if (openId === id) openId = null;
      mark(); paintAll();
      return;
    }
    if (t.closest('[data-opt-add]')) {
      f.options = (f.options || []).concat([{ value: 'option_' + ((f.options || []).length + 1), label: 'Option ' + ((f.options || []).length + 1) }]);
      mark(); paintList();
      return;
    }
    var optDel = t.closest('[data-opt-del]');
    if (optDel) {
      var oi = Number(optDel.closest('[data-opt]').getAttribute('data-opt'));
      f.options.splice(oi, 1);
      mark(); paintList();
      return;
    }
    var logicAdd = t.closest('[data-logic-add]');
    if (logicAdd) {
      var slot = logicAdd.closest('[data-logic]').getAttribute('data-logic');
      var above = fieldsAbove(id);
      if (!above.length) { message('Move this block below the field you want to test.', 'err'); return; }
      f[slot] = f[slot] || { action: slot === 'logic' ? 'show' : 'require', match: 'all', rules: [] };
      f[slot].rules.push({ field: above[above.length - 1].key, op: 'equals', value: '' });
      mark(); paintList();
      return;
    }
    var logicClear = t.closest('[data-logic-clear]');
    if (logicClear) {
      delete f[logicClear.closest('[data-logic]').getAttribute('data-logic')];
      mark(); paintList();
      return;
    }
    var ruleDel = t.closest('[data-rule-del]');
    if (ruleDel) {
      var slot2 = ruleDel.closest('[data-logic]').getAttribute('data-logic');
      var ri = Number(ruleDel.closest('[data-rule]').getAttribute('data-rule'));
      f[slot2].rules.splice(ri, 1);
      if (!f[slot2].rules.length) delete f[slot2];
      mark(); paintList();
      return;
    }
  });

  root.addEventListener('input', onEdit);
  root.addEventListener('change', onEdit);

  function onEdit(ev) {
    var t = ev.target;

    if (t === els.name) { formName = t.value; mark(); return; }

    var setting = t.getAttribute && t.getAttribute('data-setting');
    if (setting) {
      settings[setting] = (t.type === 'checkbox') ? t.checked : t.value;
      mark();
      return;
    }

    var row = t.closest && t.closest('.fbx__row');
    if (!row) return;
    var f = byId(row.getAttribute('data-id'));
    if (!f || !guard()) return;

    // Option row edits.
    var optWrap = t.closest('[data-opt]');
    if (optWrap && t.getAttribute('data-opt-field')) {
      var oi = Number(optWrap.getAttribute('data-opt'));
      var of_ = t.getAttribute('data-opt-field');
      f.options[oi][of_] = of_ === 'price' ? (t.value === '' ? '' : Number(t.value)) : t.value;
      // Blank stored value follows the label, which is what an operator means.
      if (of_ === 'label' && !f.options[oi].value) f.options[oi].value = keyFromLabel(t.value, 'option', oi);
      mark();
      paintChipsOnly(row, f);
      return;
    }

    // Logic group / rule edits.
    var logicWrap = t.closest('[data-logic]');
    if (logicWrap) {
      var slot = logicWrap.getAttribute('data-logic');
      var lf = t.getAttribute('data-logic-field');
      if (lf) { f[slot][lf] = t.value; mark(); paintList(); return; }
      var ruleWrap = t.closest('[data-rule]');
      var rf = t.getAttribute('data-rule-field');
      if (ruleWrap && rf) {
        var ri = Number(ruleWrap.getAttribute('data-rule'));
        f[slot].rules[ri][rf] = t.value;
        mark();
        // Switching operator changes whether a value box belongs there.
        if (rf === 'op') paintList();
        return;
      }
      return;
    }

    // Plain field property edits.
    var name = t.getAttribute('data-field');
    if (!name) return;
    var value = (t.type === 'checkbox') ? t.checked : t.value;

    if (name === 'type') {
      f.type = value;
      if (hasOptions(value) && !(f.options || []).length && !f.optionsFrom) {
        f.options = [{ value: 'option_1', label: 'Option 1' }];
      }
      if (!hasOptions(value)) { delete f.options; delete f.optionsFrom; }
      if (isLayout(value)) { delete f.map; delete f.required; delete f.requiredIf; }
      mark(); paintList();
      return;
    }
    if (name === 'key') {
      f.key = String(value).replace(/[^a-zA-Z0-9_]/g, '');
      // Once a key is typed by hand, the label stops rewriting it.
      f._keyTouched = true;
      mark();
      paintChipsOnly(row, f);
      return;
    }
    if (name === 'optionsFrom') {
      if (value) { f.optionsFrom = value; delete f.options; }
      else { delete f.optionsFrom; f.options = f.options || [{ value: 'option_1', label: 'Option 1' }]; }
      mark(); paintList();
      return;
    }
    if (['min', 'max', 'minLen', 'maxLen', 'minSelect', 'maxSelect', 'rows', 'price'].indexOf(name) > -1) {
      if (value === '') delete f[name]; else f[name] = Number(value);
      mark(); paintChipsOnly(row, f);
      return;
    }
    if (value === '' || value === false) delete f[name]; else f[name] = value;
    // The label is a key source until the operator sets one explicitly.
    if (name === 'label' && !isLayout(f.type) && !f.locked && !f._keyTouched) {
      f.key = keyFromLabel(value, f.type, indexOf(f._id));
    }
    mark();
    // Typed fields repaint the summary line only — a full rebuild mid-word
    // would steal the caret. Dropdowns and checkboxes can afford the rebuild,
    // which keeps the chip row honest.
    if (t.tagName === 'SELECT' || t.type === 'checkbox') paintList();
    else paintChipsOnly(row, f);
  }

  /* Repaint the summary line without rebuilding the open editor —
     rebuilding on every keystroke would steal focus mid-word. */
  function paintChipsOnly(row, f) {
    var i = indexOf(f._id);
    var key = f.key || keyFromLabel(f.label, f.type, i);
    var title = row.querySelector('.fbx__row-title');
    var sub = row.querySelector('.fbx__row-sub');
    if (title && f.type !== 'step') title.textContent = f.label || key;
    if (sub) sub.textContent = typeLabel(f.type) + (isLayout(f.type) ? '' : ' · ' + key);
  }

  function stripId(f) { var c = Object.assign({}, f); delete c._id; delete c._keyTouched; return c; }

  function guard() {
    if (canEdit) return true;
    message('You have read-only access to the builder.', 'err');
    return false;
  }

  window.addEventListener('beforeunload', function (ev) {
    if (!dirty) return;
    ev.preventDefault();
    ev.returnValue = '';
  });

  /* ---- go ----------------------------------------------------- */

  paintPalette();
  paintAll();
  showPane('build');
})();
