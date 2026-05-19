<?php
// DiaFitus — central configuration.
// Copy this file into your Hostinger account and replace placeholder
// values with the real credentials before going live.

return [
    // -----------------------------------------------------------------
    // Brand
    // -----------------------------------------------------------------
    'brand_name'     => 'DiaFitus',
    'company_name'   => 'Benux Corp',
    'company_email'  => 'hello@diafitus.com',
    'support_email'  => 'support@diafitus.com',
    'site_url'       => 'https://diafitus.com',
    'dashboard_url'  => 'https://my.diafitus.com',

    // -----------------------------------------------------------------
    // Pricing — values are in whole units of the currency (USD).
    // -----------------------------------------------------------------
    'price_regular'  => 80,
    'price_today'    => 40,
    'currency'       => 'usd',
    'offer_minutes'  => 15,

    // -----------------------------------------------------------------
    // Stripe — fill these in from your Stripe dashboard.
    // -----------------------------------------------------------------
    'stripe' => [
        'publishable_key' => 'pk_test_REPLACE_ME',
        'secret_key'      => 'sk_test_REPLACE_ME',
        'webhook_secret'  => 'whsec_REPLACE_ME',
        'success_url'     => 'https://diafitus.com/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'      => 'https://diafitus.com/offer.php?canceled=1',
        'product_name'    => 'DiaFitus Coaching — Monthly',
    ],

    // -----------------------------------------------------------------
    // Telegram — create a bot with @BotFather, then fill these in.
    // chat_id can be a personal chat id or a channel id (e.g. -100...).
    // -----------------------------------------------------------------
    'telegram' => [
        'bot_token' => 'REPLACE_WITH_BOT_TOKEN',
        'chat_id'   => 'REPLACE_WITH_CHAT_ID',
    ],

    // -----------------------------------------------------------------
    // Email — defaults to PHP mail(). Hostinger mail() works out of the
    // box if the domain's MX records are pointed at Hostinger.
    // -----------------------------------------------------------------
    'mail' => [
        'from_name'    => 'DiaFitus Team',
        'from_email'   => 'no-reply@diafitus.com',
        'reply_to'     => 'support@diafitus.com',
    ],
];
