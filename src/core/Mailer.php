<?php

/**
 * Thin wrapper around PHPMailer. Soft-fails to storage/mail.log so missing
 * SMTP creds never break a registration; operators still see the payload.
 */
class Mailer {
    /** Send to the academy inbox (admin notifications). */
    public static function send(string $subject, string $bodyHtml, ?string $replyTo = null): bool {
        $cfg = require AFT_ROOT . '/config/mail.php';
        return self::sendVia($cfg, $cfg['to_inbox'] ?? '', $subject, $bodyHtml, $replyTo);
    }

    /** Send a transactional email to a specific recipient (receipts, IDs). */
    public static function sendTo(string $to, string $subject, string $bodyHtml, ?string $replyTo = null): bool {
        $cfg = require AFT_ROOT . '/config/mail.php';
        return self::sendVia($cfg, $to, $subject, $bodyHtml, $replyTo);
    }

    private static function sendVia(array $cfg, string $to, string $subject, string $bodyHtml, ?string $replyTo): bool {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::log("[bad-to] {$subject} -> {$to}");
            return false;
        }
        if (empty($cfg['enabled']) || empty($cfg['username']) || empty($cfg['password'])) {
            self::log("[disabled] {$subject} -> {$to}");
            return false;
        }

        // PHPMailer ships vendored (committed) so cPanel needs no build step.
        $autoload = AFT_ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            self::log("[no-vendor] {$subject} -> {$to}");
            return false;
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = $cfg['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = $cfg['encryption'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($cfg['from'], $cfg['from_name']);
            $mail->addAddress($to);
            if ($replyTo) $mail->addReplyTo($replyTo);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));
            $mail->send();
            return true;
        } catch (Throwable $e) {
            self::log("[fail] {$subject} -> {$to} — {$e->getMessage()}");
            return false;
        }
    }

    private static function log(string $line): void {
        $dir = AFT_ROOT . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/mail.log', '[' . date('c') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
