<?php
/**
 * App-wide constants. Override per environment via environment variables
 * (recommended) or by editing this file before deploy.
 */

define('AFT_NAME',    'Afrotech Academy');
define('AFT_PARENT',  'Afrostrength Limited');
define('AFT_TAGLINE', 'Building Brands, Strengthening Legacies');
define('AFT_DESC',    'Afrotech Academy — the Afrostrength youth technology school. Cybersecurity, AI & automations, coding, graphics, and digital marketing for the next generation of African builders. Ages 7+.');

// Program facts surfaced on the landing + summer pages. Kept here so copy
// changes don't require touching view templates.
define('AFT_SUMMER_FEE',      '₦40,000');
define('AFT_SUMMER_AGE',      'Age 7+');
define('AFT_PHONE',           '+234 810 019 1456');
define('AFT_EMAIL',           'reachus@afrostrength.com');
define('AFT_SOCIAL',          'afrostrength');

// Public URL — explicit env wins; otherwise auto-detect from the request so
// local dev, staging, and production all generate correct absolute URLs.
// Host is allowlisted to prevent Host-header injection from poisoning
// canonical/OG metadata.
$aft_default_url = (function (): string {
    $allowed = ['afrotech.afrostrength.com', 'afrostrength.com', 'localhost', '127.0.0.1'];
    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host    = preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $rawHost) ?: 'localhost';
    $bareHost = preg_replace('/:\d+$/', '', $host);
    $ok = false;
    foreach ($allowed as $h) if ($bareHost === $h || str_ends_with($bareHost, '.' . $h)) { $ok = true; break; }
    if (!$ok) $host = 'afrotech.afrostrength.com';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host;
})();
define('AFT_URL',    getenv('AFT_URL')    ?: $aft_default_url);
define('AFT_LOCALE', getenv('AFT_LOCALE') ?: 'en');
define('AFT_TZ',     getenv('AFT_TZ')     ?: 'Africa/Lagos');

// Verbose error display while building locally.
define('AFT_DEBUG',  filter_var(getenv('AFT_DEBUG') ?: '0', FILTER_VALIDATE_BOOL));

date_default_timezone_set(AFT_TZ);
