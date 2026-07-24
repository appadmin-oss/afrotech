<?php

class Controller {
    public function view(string $page, array $data = [], string $layout = 'main'): void {
        View::render($page, $data, $layout);
    }

    public function redirect(string $path, int $code = 302): void {
        $target = preg_match('#^https?://#', $path) ? $path : ('/' . ltrim($path, '/'));
        header('Location: ' . $target, true, $code);
        exit;
    }

    public function json(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** True when the request wants JSON back (AJAX / fetch). */
    protected function wantsJson(): bool {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || stripos($accept, 'application/json') !== false
            || stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
    }

    protected function input(string $key, $default = null) {
        static $json = null;
        if ($json === null) {
            $json = [];
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($ct, 'application/json') !== false) {
                $raw = (string) file_get_contents('php://input');
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) $json = $decoded;
                }
            }
        }
        $src = $_POST + $_GET + $json;
        if (!isset($src[$key])) return $default;
        $val = $src[$key];
        return is_string($val) ? trim($val) : $val;
    }

    public function notFound(): void {
        http_response_code(404);
        $this->view('pages/404', ['title' => 'Page not found · ' . AFT_NAME], 'main');
        exit;
    }
}
