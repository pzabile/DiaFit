<?php
/**
 * PayPal Orders API v2 helpers.
 * Requires bootstrap.php (for cfg()) to be loaded first.
 */

function _paypal_base(): string {
    return cfg('paypal.sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

function _paypal_curl(string $url, array $headers, ?string $postBody = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($postBody !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = $postBody;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException('PayPal cURL error: ' . $err);
    return ['code' => $code, 'body' => $body];
}

function paypal_get_token(): string {
    $res = _paypal_curl(
        _paypal_base() . '/v1/oauth2/token',
        [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(cfg('paypal.client_id') . ':' . cfg('paypal.secret')),
        ],
        'grant_type=client_credentials'
    );
    if ($res['code'] !== 200) {
        throw new RuntimeException('PayPal auth failed HTTP ' . $res['code'] . ': ' . $res['body']);
    }
    $data = json_decode($res['body'], true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('PayPal: no access_token in response');
    }
    return $data['access_token'];
}

function paypal_create_order(string $token, string $planId, float $amount): array {
    $currency = strtoupper(cfg('currency', 'usd'));
    $payload  = json_encode([
        'intent'              => 'CAPTURE',
        'purchase_units'      => [[
            'custom_id'   => $planId,
            'description' => 'DiaFitus — ' . $planId . ' plan',
            'amount'      => [
                'currency_code' => $currency,
                'value'         => number_format($amount, 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'PAY_NOW',
            'brand_name'          => cfg('brand_name', 'DiaFitus'),
        ],
    ]);
    $res = _paypal_curl(
        _paypal_base() . '/v2/checkout/orders',
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        $payload
    );
    if ($res['code'] !== 201) {
        throw new RuntimeException('PayPal create order failed HTTP ' . $res['code'] . ': ' . $res['body']);
    }
    $data = json_decode($res['body'], true);
    if (empty($data['id'])) {
        throw new RuntimeException('PayPal: no order ID in response');
    }
    return $data;
}

function paypal_capture_order(string $token, string $orderId): array {
    $res = _paypal_curl(
        _paypal_base() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        '{}'
    );
    if ($res['code'] !== 201 && $res['code'] !== 200) {
        throw new RuntimeException('PayPal capture failed HTTP ' . $res['code'] . ': ' . $res['body']);
    }
    return json_decode($res['body'], true) ?: [];
}

function paypal_get_order(string $token, string $orderId): array {
    $res = _paypal_curl(
        _paypal_base() . '/v2/checkout/orders/' . rawurlencode($orderId),
        ['Authorization: Bearer ' . $token]
    );
    if ($res['code'] !== 200) {
        throw new RuntimeException('PayPal get order failed HTTP ' . $res['code'] . ': ' . $res['body']);
    }
    return json_decode($res['body'], true) ?: [];
}

/**
 * Extract captured amount in cents (multiplied by 100) from a capture/get-order response.
 */
function paypal_captured_cents(array $orderData): ?float {
    foreach (($orderData['purchase_units'] ?? []) as $pu) {
        foreach (($pu['payments']['captures'] ?? []) as $capture) {
            if (isset($capture['amount']['value'])) {
                return (float)$capture['amount']['value'] * 100;
            }
        }
    }
    return null;
}
