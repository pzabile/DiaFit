<?php
/**
 * POST /paypal_create_order
 * Called by the PayPal JS SDK createOrder callback.
 * Validates the signup form, persists user info + order ID to the DB (so
 * paypal_capture.php can process the payment even if the session is lost),
 * and returns {"id": "<paypal_order_id>"}.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/paypal.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$firstName = trim($_POST['firstName'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone'] ?? '');
$planKey   = trim($_POST['plan'] ?? '');
$agreed    = !empty($_POST['agreed']);
$promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));

// Validate
$errors = [];
if (!$firstName)                               $errors[] = 'First name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if (!$phone)                                   $errors[] = 'Phone number is required.';
if (!$agreed)                                  $errors[] = 'You must agree to the Terms & Conditions.';

$plans = cfg('plans');
if (!isset($plans[$planKey])) $planKey = cfg('default_plan');
$plan = $plans[$planKey];

if ($errors) {
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Apply promo
$price = (float)$plan['price_today'];
if ($promoCode === 'JUSTFORYOU') {
    $price = round($price * 0.80, 2);
}

// Store in session (backup)
$_SESSION['plan']         = $planKey;
$_SESSION['user']         = ['firstName' => $firstName, 'email' => $email, 'phone' => $phone];
$_SESSION['paypal_plan']  = $planKey;
$_SESSION['paypal_price'] = $price;

try {
    $token   = paypal_get_token();
    $order   = paypal_create_order($token, $planKey, $price);
    $orderId = $order['id'];

    // Persist order + user info to DB so capture works even if session is lost
    try {
        $existing = lead_find_by_email($email);
        if ($existing) {
            db_exec(
                'UPDATE leads SET
                    first_name      = COALESCE(NULLIF(?, ""), first_name),
                    phone           = COALESCE(NULLIF(?, ""), phone),
                    paypal_order_id = ?,
                    updated_at      = NOW()
                 WHERE id = ?',
                [$firstName, $phone, $orderId, $existing['id']]
            );
        } else {
            db_insert(
                'INSERT INTO leads (email, first_name, phone, paypal_order_id) VALUES (?, ?, ?, ?)',
                [$email, $firstName, $phone, $orderId]
            );
        }
    } catch (Throwable $dbEx) {
        error_log('paypal_create_order DB upsert: ' . $dbEx->getMessage());
        // Continue — DB failure must not block the payment
    }

    $_SESSION['paypal_order_id'] = $orderId;
    echo json_encode(['id' => $orderId]);
} catch (Throwable $ex) {
    error_log('paypal_create_order.php: ' . $ex->getMessage());
    echo json_encode(['error' => 'Payment system error. Please try again or contact support@diafitus.com']);
}
