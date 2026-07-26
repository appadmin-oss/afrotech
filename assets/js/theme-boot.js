/* Apply the persisted theme before first paint, so a dark-theme visitor never
   sees a white flash.

   This lives in a file rather than inline because the site's CSP is
   `script-src 'self'` with no 'unsafe-inline' — an inline copy is refused by
   the browser and the theme silently fails to restore. Loaded synchronously
   (no defer) from <head> so it runs before the body renders.

   The storage key comes from the tag: <script src="…" data-key="aft-theme">.
   Pass data-default="dark" for surfaces that are dark unless told otherwise
   (the operator console). */
(function () {
  'use strict';
  var el = document.currentScript;
  var key = (el && el.getAttribute('data-key')) || 'aft-theme';
  var fallback = (el && el.getAttribute('data-default')) || '';
  var theme = fallback;
  try { theme = localStorage.getItem(key) || fallback; } catch (e) { /* private mode */ }
  if (theme) document.documentElement.setAttribute('data-theme', theme);
})();
