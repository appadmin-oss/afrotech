<?php
// Dev-server router: serve real files (assets), send everything else through
// the app's front controller — mirroring what .htaccess does in production.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
require __DIR__ . '/index.php';
