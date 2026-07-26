/* Afrotech Academy — progressive enhancement.
   Nothing here is required for the site to work; it degrades cleanly. */
(function () {
  'use strict';

  /* ---- Mobile nav toggle ------------------------------------- */
  var burger = document.querySelector('.nav__burger');
  var links = document.querySelector('.nav__links');
  if (burger && links) {
    burger.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---- Theme toggle (persisted) ------------------------------ */
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('aft-theme', next); } catch (e) {}
    });
  });

  /* The binary "10101" backdrop is its own module (binary-matrix.js),
     loaded before this file and auto-initialising on [data-binary]. */

  function debounce(fn, ms) {
    var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); };
  }

  /* ---- Reveal-on-scroll -------------------------------------- */
  (function () {
    var els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;
    if (!('IntersectionObserver' in window) || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
      els.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
  })();

  /* ---- Countdown timers (promo ribbon / summer deadline) ----- */
  document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var end = new Date(el.getAttribute('data-countdown')).getTime();
    if (isNaN(end)) return;
    function tick() {
      var diff = end - Date.now();
      if (diff <= 0) { el.textContent = 'Closed'; return; }
      var d = Math.floor(diff / 86400000),
          h = Math.floor(diff % 86400000 / 3600000),
          m = Math.floor(diff % 3600000 / 60000),
          s = Math.floor(diff % 60000 / 1000);
      el.innerHTML = (d ? '<b>' + d + '</b>d ' : '') + '<b>' + h + '</b>h <b>' + m + '</b>m <b>' + s + '</b>s';
      requestAnimationFrame(function () {});
    }
    tick();
    setInterval(tick, 1000);
  });

  /* ---- Async form submit (registration / contact) ------------ */
  document.querySelectorAll('form[data-async]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      var original = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
      clearErrors(form);

      fetch(form.action, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form)
      })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        if (res.ok && res.body.ok) {
          if (res.body.redirect) { window.location = res.body.redirect; return; }
          renderSuccess(form, res.body);
        } else {
          applyErrors(form, res.body);
          if (btn) { btn.disabled = false; btn.textContent = original; }
        }
      })
      .catch(function () {
        // Network failure → let the browser do a real POST so nothing is lost.
        form.removeAttribute('data-async');
        form.submit();
      });
    });
  });

  function clearErrors(form) {
    form.querySelectorAll('.field.has-error').forEach(function (f) { f.classList.remove('has-error'); });
    form.querySelectorAll('.err').forEach(function (e) { e.textContent = ''; });
    var box = form.querySelector('[data-form-alert]');
    if (box) { box.textContent = ''; box.className = ''; }
  }

  function applyErrors(form, body) {
    var errors = (body && body.errors) || {};
    Object.keys(errors).forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      if (input) {
        var field = input.closest('.field');
        if (field) {
          field.classList.add('has-error');
          var slot = field.querySelector('.err');
          if (slot) slot.textContent = errors[name];
        }
      }
    });
    var box = form.querySelector('[data-form-alert]');
    if (box) {
      box.className = 'alert alert--err';
      box.textContent = (body && body.message) || 'Please check the highlighted fields.';
    }
  }

  /* ---- Receipt: print / save as PDF -------------------------- */
  /* A button rather than "press Ctrl+P": most parents open the receipt on a
     phone, where the print dialog is the route to a shareable PDF. */
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  /* ---- Checkout: live discount quote ------------------------- */
  (function () {
    var cfgEl = document.getElementById('ck-cfg');
    var applyBtn = document.getElementById('ck-apply');
    if (!cfgEl || !applyBtn) return;
    var cfg = {};
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
    var codeEl = document.getElementById('ck-code');
    var msgEl = document.getElementById('ck-msg');
    var fmt = function (n) { return cfg.sym + Number(n).toLocaleString(); };

    applyBtn.addEventListener('click', function () {
      var code = (codeEl.value || '').trim();
      if (!code) { msgEl.textContent = 'Enter a code first.'; return; }
      applyBtn.disabled = true; applyBtn.textContent = '…';
      fetch(cfg.quote, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf },
        body: new URLSearchParams({ code: code, reg_code: cfg.reg || '', _csrf: cfg.csrf })
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var discRow = document.getElementById('ck-disc-row');
        if (j.ok && j.discount > 0) {
          document.getElementById('ck-disc').textContent = '−' + fmt(j.discount);
          document.getElementById('ck-disc-label').textContent = j.label ? '· ' + j.label : '';
          document.getElementById('ck-total').textContent = fmt(j.total);
          discRow.style.display = 'flex';
          msgEl.style.color = 'var(--success)';
          msgEl.textContent = 'Code applied — new total shown above.';
        } else {
          discRow.style.display = 'none';
          document.getElementById('ck-total').textContent = fmt(j.base);
          msgEl.style.color = 'var(--red)';
          msgEl.textContent = j.message || 'That code could not be applied.';
        }
      })
      .catch(function () { msgEl.textContent = 'Could not check that code right now.'; })
      .finally(function () { applyBtn.disabled = false; applyBtn.textContent = 'Apply'; });
    });
  })();

  function renderSuccess(form, body) {
    var target = form.querySelector('[data-success]') || form;
    var code = body.reg_code || body.code || '';
    target.innerHTML =
      '<div class="receipt">' +
        '<div class="eyebrow" style="justify-content:center">Registration received</div>' +
        '<h2 style="margin-top:12px">' + (body.title || "You're on the list! 🎉") + '</h2>' +
        (code ? '<div class="receipt__code">' + code + '</div>' : '') +
        '<p>' + (body.message || 'Check your email for confirmation. Save your reference code.') + '</p>' +
      '</div>';
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

})();
