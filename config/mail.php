<?php
/**
 * SMTP settings for PHPMailer. The Mailer soft-fails to storage/mail.log
 * when creds are missing, so registration keeps working during setup.
 */

return [
    'enabled'    => filter_var(getenv('MAIL_ENABLED') ?: '0', FILTER_VALIDATE_BOOL),
    'host'       => getenv('MAIL_HOST')       ?: 'smtp.example.com',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls', // 'tls' or 'ssl'
    'username'   => getenv('MAIL_USERNAME')   ?: '',
    'password'   => getenv('MAIL_PASSWORD')   ?: '',
    'from'       => getenv('MAIL_FROM')       ?: 'reachus@afrostrength.com',
    'from_name'  => getenv('MAIL_FROM_NAME')  ?: 'Afrotech Academy',
    'to_inbox'   => getenv('MAIL_INBOX')      ?: 'reachus@afrostrength.com',
];
