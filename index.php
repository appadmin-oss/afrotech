<?php
/**
 * Afrotech Academy — front controller.
 * All non-asset traffic enters here via the .htaccess rewrite.
 *
 * Afrotech is Afrostrength's youth technology academy. The stack is a
 * dependency-light PHP MVC (no framework, no Composer required to boot)
 * so it deploys cleanly onto shared cPanel hosting.
 */

declare(strict_types=1);

define('AFT_ROOT', __DIR__);
define('AFT_START', microtime(true));

// Local dev only: when run via `php -S host:port index.php`, let the built-in
// server serve real static files (assets) directly instead of routing them
// through the front controller. On Apache/cPanel this block never runs
// (.htaccess serves statics before PHP).
if (PHP_SAPI === 'cli-server') {
    $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__file = AFT_ROOT . '/' . ltrim($__path, '/');
    if ($__path !== '/' && is_file($__file)) return false;
}

// Hardened session cookie: HttpOnly keeps it out of JS reach (an XSS can't
// lift it); SameSite=Lax blocks cross-site subrequests while surviving
// top-level navigation; Secure is set only on HTTPS; strict-mode makes PHP
// reject an adopted/forged session id (session-fixation defence).
$aftHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $aftHttps,
    'samesite' => 'Lax',
]);
session_start();

require AFT_ROOT . '/config/app.php';
require AFT_ROOT . '/src/core/Helpers.php';
require AFT_ROOT . '/src/core/Database.php';
require AFT_ROOT . '/src/core/Csrf.php';
require AFT_ROOT . '/src/core/Rbac.php';
require AFT_ROOT . '/src/core/Auth.php';
require AFT_ROOT . '/src/core/StudentAuth.php';
require AFT_ROOT . '/src/core/View.php';
require AFT_ROOT . '/src/core/Validator.php';
require AFT_ROOT . '/src/core/Ids.php';
require AFT_ROOT . '/src/core/Mailer.php';
require AFT_ROOT . '/src/core/Paystack.php';
require AFT_ROOT . '/src/core/Security.php';
Security::sendHeaders();
require AFT_ROOT . '/src/core/Controller.php';
require AFT_ROOT . '/src/core/Router.php';

// Lightweight autoload for controllers/models (incl. Admin\ namespace).
spl_autoload_register(function (string $class): void {
    $candidates = [
        AFT_ROOT . '/src/controllers/' . str_replace('\\', '/', $class) . '.php',
        AFT_ROOT . '/src/models/' . $class . '.php',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) { require $path; return; }
    }
});

$router = new Router();
require AFT_ROOT . '/config/routes.php';

try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    if (AFT_DEBUG) {
        http_response_code(500);
        echo '<pre style="font:12px/1.5 monospace;padding:24px;">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
    } else {
        error_log('[aft] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        try {
            http_response_code(500);
            (new Controller())->view('pages/500', ['title' => 'Something broke'], 'main');
        } catch (Throwable $inner) {
            error_log('[aft/500] ' . $inner->getMessage());
            if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Service interrupted</title>'
               . '<style>body{font:16px/1.6 system-ui,sans-serif;padding:48px;max-width:640px;margin:auto;}'
               . 'h1{font-size:28px;}a{color:#E4022B;}</style>'
               . '<h1>The academy is briefly unavailable.</h1>'
               . '<p>We logged this. Try again in a moment, or '
               . '<a href="mailto:reachus@afrostrength.com">email us</a> if it persists.</p>';
        }
    }
} finally {
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
}
