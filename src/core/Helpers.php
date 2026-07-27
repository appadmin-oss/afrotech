<?php
/** Tiny global helper set. */

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string {
    if (preg_match('#^https?://#', $path)) return $path;
    return rtrim(AFT_URL, '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return rtrim(AFT_URL, '/') . '/assets/' . ltrim($path, '/');
}

/** Versioned asset URL — appends ?v=<mtime> so browsers refresh after deploy. */
function asset_v(string $path): string {
    $clean = ltrim($path, '/');
    $abs   = AFT_ROOT . '/assets/' . $clean;
    $u     = rtrim(AFT_URL, '/') . '/assets/' . $clean;
    if (is_file($abs)) {
        $mt = filemtime($abs);
        if ($mt) return $u . '?v=' . $mt;
    }
    return $u;
}

function current_path(): string {
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function is_active(string $prefix): bool {
    $p = current_path();
    if ($prefix === '/') return $p === '/';
    return str_starts_with($p, rtrim($prefix, '/'));
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    return preg_replace('~[^-\w]+~', '', $text) ?: 'item';
}

function excerpt(string $body, int $words = 28): string {
    $text = strip_tags($body);
    $arr  = preg_split('/\s+/', trim($text)) ?: [];
    if (count($arr) <= $words) return $text;
    return implode(' ', array_slice($arr, 0, $words)) . '…';
}

function date_pretty(?string $iso): string {
    if (!$iso) return '—';
    $t = strtotime($iso);
    return $t ? date('M j, Y', $t) : $iso;
}

function datetime_pretty(?string $iso): string {
    if (!$iso) return '—';
    $t = strtotime($iso);
    return $t ? date('M j, Y · g:i A', $t) : $iso;
}

function partial(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    require AFT_ROOT . '/src/views/partials/' . $name . '.php';
}

/**
 * Render a transactional email template to a string.
 *
 * The locals are deliberately mangled. With the obvious `$name` / `$vars`,
 * `extract(..., EXTR_SKIP)` won't overwrite an existing variable — so a `name`
 * key (a person's name, the obvious thing to pass) reached the template as the
 * TEMPLATE name instead, silently. Obscure locals plus EXTR_OVERWRITE means a
 * template receives exactly what the caller passed.
 */
function render_email(string $__tpl, array $__vars = []): string {
    $__path = AFT_ROOT . '/src/views/emails/' . $__tpl . '.php';
    if (!is_file($__path)) return '';
    extract($__vars, EXTR_OVERWRITE);
    ob_start();
    require $__path;
    return (string) ob_get_clean();
}

function flash_set(string $key, string $msg): void { $_SESSION['_flash'][$key] = $msg; }
function flash_pop(string $key): ?string {
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

/** Format an integer naira amount for display, e.g. 40000 → ₦40,000. */
function naira(int $amount): string {
    return '₦' . number_format($amount);
}
