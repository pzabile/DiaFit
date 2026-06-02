<?php
require_once __DIR__ . '/bootstrap.php';

function stripe_request($method, $endpoint, $params = []) {
    $secret = cfg('stripe.secret_key');
    if (!$secret || strpos($secret, 'sk_') !== 0) {
        throw new RuntimeException('Stripe secret_key not configured in config.php.');
    }
    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $ch  = curl_init();

    if ($method === 'GET' && $params) {
        $url .= '?' . http_build_query($params);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secret,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Stripe connection failed: ' . $error . ' (curl #' . $errno . ')');
    }

    $data = json_decode($raw, true);
    if ($code >= 400) {
        $msg = $data['error']['message'] ?? ('Stripe API error (HTTP ' . $code . ')');
        throw new RuntimeException($msg);
    }
    return $data;
}

function stripe_create_checkout_session($user, $answers, $plan = null) {
    if (!$plan) {
        $plans = cfg('plans');
        $plan  = $plans[cfg('default_plan')];
    }
    $amountCents = (int) round(((float) $plan['price_today']) * 100);
    $params = [
        'mode'                  => 'payment',
        'success_url'           => cfg('stripe.success_url'),
        'cancel_url'            => cfg('stripe.cancel_url'),
        'customer_email'        => $user['email'] ?? '',
        'allow_promotion_codes' => 'true',
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]'    => cfg('currency'),
        'line_items[0][price_data][unit_amount]' => $amountCents,
        'line_items[0][price_data][product_data][name]'        => 'DiaFitus — ' . $plan['name'],
        'line_items[0][price_data][product_data][description]' => 'Personalized diabetes-aware coaching, nutrition PDF, 24/7 coach access. ' . (int) $plan['days'] . '-day plan.',
        'metadata[plan_id]'         => $plan['id'],
        'metadata[plan_days]'       => (int) $plan['days'],
        'metadata[first_name]'      => $user['firstName'] ?? '',
        'metadata[phone]'           => $user['phone'] ?? '',
        'metadata[diabetes_type]'   => $answers['diabetes_type'] ?? '',
        'metadata[location]'        => $answers['location'] ?? '',
        'metadata[days_per_week]'   => $answers['days_per_week'] ?? '',
        'metadata[minutes_per_day]' => $answers['minutes_per_day'] ?? '',
    ];
    return stripe_request('POST', 'checkout/sessions', $params);
}

function stripe_get_session($sessionId) {
    return stripe_request('GET', 'checkout/sessions/' . urlencode($sessionId));
}

function stripe_verify_webhook($payload, $sigHeader) {
    $secret = cfg('stripe.webhook_secret');
    if (!$secret || strpos($secret, 'whsec_') !== 0) return false;
    if (!$sigHeader) return false;

    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $parts[$k][] = $v;
    }
    if (empty($parts['t']) || empty($parts['v1'])) return false;
    $t = $parts['t'][0];
    if (abs(time() - (int)$t) > 600) return false; // 10-min tolerance

    $signed = $t . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    foreach ($parts['v1'] as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}
