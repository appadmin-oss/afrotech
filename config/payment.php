<?php
/**
 * Payment gateway settings. Live keys come from the environment so secrets
 * never sit in the repo. When no secret key is present, the app falls back to
 * manual (bank-transfer) instructions instead of a gateway redirect.
 */
return [
    'provider'        => getenv('PAYMENT_PROVIDER') ?: 'paystack',
    'paystack' => [
        'secret_key'     => getenv('PAYSTACK_SECRET_KEY')     ?: '',
        'public_key'     => getenv('PAYSTACK_PUBLIC_KEY')     ?: '',
        'webhook_secret' => getenv('PAYSTACK_WEBHOOK_SECRET') ?: '',
    ],
];
