/*!
 * binary-matrix.js — Afrotech Academy
 * A small, self-contained "digital rain" of 0s and 1s that echoes the flier's
 * binary backdrop. No dependencies, CSP-safe (self-hosted).
 *
 * Cinematic: DPR-aware canvas, per-column depth (parallax) so near streams are
 * brighter/faster than far ones, long fading trails, a slow "breathing" glow,
 * occasional accent flares, and periodic word-reveals that spell brand/track
 * words inside the stream.
 * Interactive: a soft spotlight follows the pointer and brightens nearby
 * glyphs; clicking/tapping sends an expanding ripple through the field.
 * Respects prefers-reduced-motion (renders a single static frame).
 *
 * Auto:  <div class="binary-field" data-binary data-words="AFROTECH,AI,CODE"></div>
 * Manual: BinaryMatrix.mount(el, { words:['AFROTECH'], density:1 })
 */
(function (global) {
  'use strict';

  var reduce = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function cssVar(name, fallback) {
    try { var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim(); return v || fallback; }
    catch (e) { return fallback; }
  }
  function resolveColor(input, fallback) {
    if (!input) return fallback;
    var m = /^var\((--[\w-]+)\)$/.exec(input.trim());
    return m ? cssVar(m[1], fallback) : input;
  }
  function hexToRgb(c) {
    c = (c || '').trim();
    var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(c);
    if (m) { var h = m[1]; if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
      return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)]; }
    var rgb = /rgba?\(([^)]+)\)/.exec(c);
    if (rgb) { var p = rgb[1].split(',').map(Number); return [p[0]|0, p[1]|0, p[2]|0]; }
    return [228, 2, 43];
  }
  function debounce(fn, ms) { var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); }; }

  function Matrix(el, opts) {
    opts = opts || {};
    this.el = el;
    this.words = (opts.words || []).filter(Boolean);
    this.density = opts.density != null ? +opts.density : 1;
    this.glow = opts.glow !== false && opts.glow !== 0 && opts.glow !== '0';
    this.accentInput = opts.accent || 'var(--red)';
    this.fontSize = opts.fontSize || 16;
    this.pointer = { x: -9999, y: -9999, tx: -9999, ty: -9999, on: false };
    this.ripples = [];
    this.frame = 0;
    this.running = false;
    this.raf = null;

    this.canvas = document.createElement('canvas');
    this.canvas.setAttribute('aria-hidden', 'true');
    var st = this.canvas.style;
    st.position = 'absolute'; st.inset = '0'; st.width = '100%'; st.height = '100%';
    el.insertBefore(this.canvas, el.firstChild);
    this.ctx = this.canvas.getContext('2d');

    this._resize = debounce(this.resize.bind(this), 150);
    this.readColors();
    this.resize();
    this.bind();

    if (reduce) { this.drawFrame(0.6); return; }

    var self = this;
    if ('IntersectionObserver' in global) {
      this.io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { e.isIntersecting ? self.start() : self.stop(); });
      }, { threshold: 0.01 });
      this.io.observe(el);
    } else { this.start(); }

    var mo = new MutationObserver(this.readColors.bind(this));
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  }

  Matrix.prototype.readColors = function () {
    this.accent = hexToRgb(resolveColor(this.accentInput, '#E4022B'));
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    this.base = dark ? [255, 255, 255] : hexToRgb(cssVar('--ink', '#0E0E0E'));
    this.baseAlpha = dark ? 0.14 : 0.085;
    this.paper = cssVar('--paper', dark ? '#0B0C0F' : '#FBFBFB');
  };

  Matrix.prototype.resize = function () {
    var r = this.el.getBoundingClientRect();
    var dpr = Math.min(2, global.devicePixelRatio || 1);
    this.w = Math.max(1, r.width); this.h = Math.max(1, r.height);
    this.canvas.width = this.w * dpr; this.canvas.height = this.h * dpr;
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    this.cols = Math.ceil(this.w / this.fontSize);
    this.drops = [];
    for (var i = 0; i < this.cols; i++) {
      var depth = 0.45 + Math.random() * 0.55;           // 0.45 (far) .. 1 (near)
      this.drops.push({
        y: Math.floor(Math.random() * -60),
        depth: depth,
        speed: (0.3 + depth * 0.7) * this.density,        // near falls faster
        word: null, wi: 0
      });
    }
    this.ctx.fillStyle = this.paper;
    this.ctx.fillRect(0, 0, this.w, this.h);
  };

  Matrix.prototype.bind = function () {
    global.addEventListener('resize', this._resize);
    var self = this;
    // Track pointer against the whole hero so the spotlight feels alive.
    var host = this.el.parentNode || this.el;
    this._moveTarget = host;
    host.addEventListener('pointermove', function (e) {
      var r = self.el.getBoundingClientRect();
      self.pointer.tx = e.clientX - r.left; self.pointer.ty = e.clientY - r.top; self.pointer.on = true;
    });
    host.addEventListener('pointerleave', function () { self.pointer.on = false; });
    host.addEventListener('pointerdown', function (e) {
      var r = self.el.getBoundingClientRect();
      self.ripples.push({ x: e.clientX - r.left, y: e.clientY - r.top, r: 0, life: 0 });
      if (self.ripples.length > 5) self.ripples.shift();
    });
  };

  Matrix.prototype.maybeWord = function (d) {
    if (!this.words.length || d.word) return;
    if (Math.random() < 0.0035) { d.word = this.words[(Math.random()*this.words.length)|0].toUpperCase(); d.wi = 0; }
  };

  Matrix.prototype.drawFrame = function (staticFade) {
    var ctx = this.ctx, fs = this.fontSize, a = this.accent, b = this.base;
    this.frame++;

    // Long, cinematic trails: fade the previous frame gently.
    ctx.globalAlpha = staticFade != null ? staticFade : 0.055;
    ctx.fillStyle = this.paper;
    ctx.fillRect(0, 0, this.w, this.h);
    ctx.globalAlpha = 1;

    // Ease the spotlight toward the pointer for a smooth, filmic drift.
    if (this.pointer.on) {
      this.pointer.x += (this.pointer.tx - this.pointer.x) * 0.12;
      this.pointer.y += (this.pointer.ty - this.pointer.y) * 0.12;
    }

    // Soft spotlight halo under the pointer.
    if (this.pointer.on && this.glow) {
      var g = ctx.createRadialGradient(this.pointer.x, this.pointer.y, 0, this.pointer.x, this.pointer.y, 150);
      g.addColorStop(0, 'rgba(' + a[0] + ',' + a[1] + ',' + a[2] + ',0.06)');
      g.addColorStop(1, 'rgba(' + a[0] + ',' + a[1] + ',' + a[2] + ',0)');
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, this.w, this.h);
    }

    // advance ripples
    for (var k = this.ripples.length - 1; k >= 0; k--) {
      var rp = this.ripples[k];
      rp.r += 7; rp.life++;
      if (rp.life > 55) this.ripples.splice(k, 1);
    }

    // "breathing" — a slow global shimmer (frame-based, no clock needed)
    var breathe = 0.9 + 0.1 * Math.sin(this.frame * 0.012);

    ctx.font = '700 ' + fs + 'px "JetBrains Mono", ui-monospace, monospace';
    ctx.textBaseline = 'top';

    for (var i = 0; i < this.cols; i++) {
      var d = this.drops[i];
      var x = i * fs, y = d.y * fs;
      this.maybeWord(d);

      var glyph, isWord = false;
      if (d.word) { glyph = d.word[d.wi] || '0'; isWord = true; }
      else glyph = Math.random() > 0.5 ? '1' : '0';

      // interaction boosts: pointer proximity + ripple crossings
      var boost = 0;
      if (this.pointer.on) {
        var dx = x - this.pointer.x, dy = y - this.pointer.y;
        var dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 140) boost = (1 - dist/140) * 0.7;
      }
      for (var r2 = 0; r2 < this.ripples.length; r2++) {
        var R = this.ripples[r2], rx = x - R.x, ry = y - R.y;
        var rd = Math.sqrt(rx*rx + ry*ry);
        if (Math.abs(rd - R.r) < 22) boost = Math.max(boost, (1 - R.life/55) * 0.8);
      }

      if (this.glow) ctx.shadowBlur = 0;

      if (isWord) {
        ctx.fillStyle = 'rgba(' + a[0]+','+a[1]+','+a[2]+',' + Math.min(1, 0.8 + boost) + ')';
        if (this.glow) { ctx.shadowColor = 'rgba('+a[0]+','+a[1]+','+a[2]+',0.9)'; ctx.shadowBlur = 8; }
      } else {
        var flare = Math.random() < 0.045;               // occasional accent spark
        if (flare || boost > 0.25) {
          ctx.fillStyle = 'rgba(' + a[0]+','+a[1]+','+a[2]+',' + Math.min(1, 0.35 + boost) * d.depth + ')';
          if (this.glow) { ctx.shadowColor = 'rgba('+a[0]+','+a[1]+','+a[2]+',0.55)'; ctx.shadowBlur = 6; }
        } else {
          var al = (this.baseAlpha + boost) * d.depth * breathe;   // depth + breathing
          ctx.fillStyle = 'rgba(' + b[0]+','+b[1]+','+b[2]+',' + al + ')';
        }
      }
      ctx.fillText(glyph, x, y);
      if (this.glow) ctx.shadowBlur = 0;

      d.y += d.speed;
      if (isWord) { d.wi++; if (d.wi >= d.word.length) { d.word = null; d.wi = 0; } }
      if (y > this.h && Math.random() > 0.975) {
        d.y = Math.floor(Math.random() * -20);
        var depth = 0.45 + Math.random() * 0.55;
        d.depth = depth; d.speed = (0.3 + depth * 0.7) * this.density;
      }
    }
  };

  Matrix.prototype.loop = function () { if (!this.running) return; this.drawFrame(); this.raf = requestAnimationFrame(this.loop.bind(this)); };
  Matrix.prototype.start = function () { if (!this.running && !reduce) { this.running = true; this.loop(); } };
  Matrix.prototype.stop = function () { this.running = false; if (this.raf) cancelAnimationFrame(this.raf); this.raf = null; };

  var BinaryMatrix = {
    mount: function (el, opts) { return new Matrix(el, opts); },
    auto: function (root) {
      (root || document).querySelectorAll('[data-binary]').forEach(function (el) {
        if (el.__binaryMounted) return; el.__binaryMounted = true;
        BinaryMatrix.mount(el, {
          words: (el.getAttribute('data-words') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean),
          density: parseFloat(el.getAttribute('data-density') || '1'),
          glow: el.getAttribute('data-glow') !== '0',
          accent: el.getAttribute('data-accent') || 'var(--red)',
          fontSize: parseInt(el.getAttribute('data-font') || '16', 10)
        });
      });
    }
  };

  if (document.readyState !== 'loading') BinaryMatrix.auto();
  else document.addEventListener('DOMContentLoaded', function () { BinaryMatrix.auto(); });

  global.BinaryMatrix = BinaryMatrix;
})(window);
