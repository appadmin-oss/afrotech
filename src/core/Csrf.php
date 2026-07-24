<?php

class Csrf {
    public static function token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(?string $candidate): bool {
        return is_string($candidate)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $candidate);
    }

    /**
     * Enforce CSRF on a mutating request. Candidate is pulled, in order:
     * $_POST, X-CSRF-Token header, then a JSON body field. Refusal mode is
     * content-negotiated (JSON 419 for AJAX, HTML 419 otherwise) so a stale
     * token never white-screens.
     */
    public static function require(): void {
        $tok = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$tok && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
            $raw = (string) file_get_contents('php://input');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['_csrf'])) {
                    $tok = (string) $decoded['_csrf'];
                }
            }
        }

        if (self::check($tok)) return;

        http_response_code(419);
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
               || stripos($accept, 'application/json') !== false;

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'csrf', 'message' => 'Session expired. Refresh and try again.']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8">'
               . '<title>Session expired · ' . e(AFT_NAME) . '</title>'
               . '<style>body{font:16px/1.6 system-ui,sans-serif;padding:48px;max-width:640px;margin:auto;color:#0E0E0E;}'
               . 'a{color:#E4022B;}.b{font-family:monospace;color:#777;font-size:12px;}</style>'
               . '<p class="b">// 419 — CSRF mismatch</p>'
               . '<h1>Session expired.</h1>'
               . '<p>Your form token has aged out. Go back, refresh the page, and submit again.</p>'
               . '<p><a href="javascript:history.back()">← Go back</a> &nbsp;·&nbsp; <a href="/">Home</a></p>';
        }
        exit;
    }
}
