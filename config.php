<?php
// DiaFitus — central configuration.
// This file is excluded from the web by .htaccess. Even so, treat its
// contents as secrets — anyone with read access to it can charge cards
// and send Telegram messages on your behalf.
//
// SECURITY NOTE: if this file is committed to a public git repository,
// rotate every credential below as soon as you go live.

return [
    // Brand --------------------------------------------------------------
    'brand_name'     => 'DiaFitus',
    'company_name'   => 'Benux Corp',
    'company_email'  => 'hello@diafitus.com',
    'support_email'  => 'support@diafitus.com',
    'site_url'       => 'https://diafitus.com',
    'dashboard_url'  => 'https://my.diafitus.com',

    // Pricing ------------------------------------------------------------
    // Three one-time plans; same discount (~62% off). Per-day price is
    // computed automatically from price_today / days. The 12-week plan
    // is featured as the most popular.
    'plans' => [
        'week' => [
            'id'            => 'week',
            'name'          => '7-day jump-start',
            'days'          => 7,
            'price_regular' => 26,
            'price_today'   => 9.99,
        ],
        'month' => [
            'id'            => 'month',
            'name'          => '4-week reset',
            'days'          => 28,
            'price_regular' => 45,
            'price_today'   => 16.99,
            'badge'         => 'Visible results in 4 weeks',
        ],
        'quarter' => [
            'id'            => 'quarter',
            'name'          => '12-week transformation',
            'days'          => 84,
            'price_regular' => 129,
            'price_today'   => 49,
            'most_popular'  => true,
            'badge'         => 'Best value',
        ],
    ],
    'default_plan'   => 'quarter',
    'price_regular'  => 129,
    'price_today'    => 49,
    'currency'       => 'usd',
    'offer_minutes'  => 15,

    // Database (Hostinger MySQL) ----------------------------------------
    // Find these in hPanel -> Databases -> MySQL Databases after you
    // create a database. host is usually "localhost".
    'db' => [
        'host'    => 'localhost',
        'name'    => 'REPLACE_DB_NAME',
        'user'    => 'REPLACE_DB_USER',
        'pass'    => 'REPLACE_DB_PASS',
        'charset' => 'utf8mb4',
    ],

    // Stripe -------------------------------------------------------------
    'stripe' => [
        'publishable_key' => 'pk_test_REPLACE_ME',
        'secret_key'      => 'sk_test_REPLACE_ME',
        'webhook_secret'  => 'whsec_REPLACE_ME',
        'success_url'     => 'https://diafitus.com/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'      => 'https://diafitus.com/offer?canceled=1',
        'product_name'    => 'DiaFitus Coaching — Monthly',
    ],

    // Telegram -----------------------------------------------------------
    // Used ONLY for owner notifications (you receive a message when an
    // assessment is submitted or a member pays). Members never see this.
    'telegram' => [
        'bot_token' => '8656432898:AAHZo5Iv6apbauNaLcyGgUIlP_7P_aP9qXg',
        'chat_id'   => '325385972',
        // Public Telegram link shown to paying members on their dashboard
        // (the user clicks this to start chatting with you). Replace with
        // your own t.me/<username> or t.me/<channel> URL.
        'member_link' => 'https://t.me/DiaFitusSupport',
    ],

    // Mail ---------------------------------------------------------------
    'mail' => [
        'from_name'  => 'DiaFitus Team',
        'from_email' => 'support@diafitus.com',
        'reply_to'   => 'support@diafitus.com',
    ],

    // Uploads ------------------------------------------------------------
    'uploads' => [
        'dir'    => __DIR__ . '/uploads',
        'public' => '/uploads',
        'max_mb' => 8,
        'mime'   => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
    ],

    // Session ------------------------------------------------------------
    'session' => [
        'lifetime_days' => 30,
        'cookie_name'   => 'diafitus_session',
    ],
];
