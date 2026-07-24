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

  /* ---- Binary "10101" background effect ----------------------
     A subtle Matrix-style rain of 0s and 1s, echoing the flier's
     digital backdrop. Respects prefers-reduced-motion, pauses when
     off-screen, and is purely decorative (aria-hidden). */
  function initBinary(field) {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;

    var canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    field.insertBefore(canvas, field.firstChild);
    var ctx = canvas.getContext('2d');
    var fontSize = 16, cols = 0, drops = [], raf = null, running = false;

    function readColor() {
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      return dark ? 'rgba(255,255,255,0.14)' : 'rgba(228,2,43,0.16)';
    }
    var color = readColor();

    function resize() {
      var r = field.getBoundingClientRect();
      canvas.width = Math.max(1, r.width);
      canvas.height = Math.max(1, r.height);
      cols = Math.ceil(canvas.width / fontSize);
      drops = [];
      for (var i = 0; i < cols; i++) drops[i] = Math.floor(Math.random() * -40);
      color = readColor();
    }

    function draw() {
      // Fade the previous frame for the trailing-glyph look.
      ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--paper').trim() || '#F4F1EA';
      ctx.globalAlpha = 0.08;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.globalAlpha = 1;

      ctx.font = '700 ' + fontSize + 'px "JetBrains Mono", monospace';
      ctx.fillStyle = color;
      for (var i = 0; i < drops.length; i++) {
        var ch = Math.random() > 0.5 ? '1' : '0';
        var x = i * fontSize;
        var y = drops[i] * fontSize;
        ctx.fillText(ch, x, y);
        if (y > canvas.height && Math.random() > 0.975) drops[i] = 0;
        drops[i]++;
      }
      raf = requestAnimationFrame(draw);
    }

    function start() { if (!running) { running = true; draw(); } }
    function stop() { running = false; if (raf) cancelAnimationFrame(raf); raf = null; }

    resize();
    window.addEventListener('resize', debounce(resize, 200));

    // Only animate while the hero is on screen.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { e.isIntersecting ? start() : stop(); });
      }, { threshold: 0.01 }).observe(field);
    } else {
      start();
    }
  }

  function debounce(fn, ms) {
    var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); };
  }

  document.querySelectorAll('.binary-field').forEach(initBinary);

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
