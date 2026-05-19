<?php
// Copy this file to config.php on your server and replace every REPLACE_*
// placeholder with the real values from Hostinger / Stripe / Telegram.
return [
    'brand_name'    => 'DiaFitus',
    'company_name'  => 'Benux Corp',
    'company_email' => 'hello@diafitus.com',
    'support_email' => 'support@diafitus.com',
    'site_url'      => 'https://diafitus.com',
    'dashboard_url' => 'https://my.diafitus.com',

    'price_regular' => 80,
    'price_today'   => 40,
    'currency'      => 'usd',
    'offer_minutes' => 15,

    'db' => [
        'host'    => 'localhost',
        'name'    => 'REPLACE_DB_NAME',
        'user'    => 'REPLACE_DB_USER',
        'pass'    => 'REPLACE_DB_PASS',
        'charset' => 'utf8mb4',
    ],

    'stripe' => [
        'publishable_key' => 'pk_live_REPLACE_ME',
        'secret_key'      => 'sk_live_REPLACE_ME',
        'webhook_secret'  => 'whsec_REPLACE_ME',
        'success_url'     => 'https://diafitus.com/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'      => 'https://diafitus.com/offer?canceled=1',
        'product_name'    => 'DiaFitus Coaching — Monthly',
    ],

    'telegram' => [
        'bot_token'   => 'REPLACE_BOT_TOKEN',
        'chat_id'     => 'REPLACE_CHAT_ID',
        'member_link' => 'https://t.me/REPLACE_HANDLE',
    ],

    'mail' => [
        'from_name'  => 'DiaFitus Team',
        'from_email' => 'no-reply@diafitus.com',
        'reply_to'   => 'support@diafitus.com',
    ],

    'uploads' => [
        'dir'    => __DIR__ . '/uploads',
        'public' => '/uploads',
        'max_mb' => 8,
        'mime'   => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
    ],

    'session' => [
        'lifetime_days' => 30,
        'cookie_name'   => 'diafitus_session',
    ],
];
