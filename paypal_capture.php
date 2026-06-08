<?php
/**
 * POST /paypal_capture.php
 * Called by the PayPal JS SDK onApprove callback with JSON body {"orderID": "..."}.
 * Captures the PayPal order and sets a session flag so success.php can mark the
 * lead as paid and send the welcome email.
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

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = trim($input['orderID'] ?? '');

if (!$orderId) {
    echo json_encode(['error' => 'Missing orderID']);
    exit;
}

// Verify the order was created by this session (prevent arbitrary order capture)
if (empty($_SESSION['paypal_order_id']) || !hash_equals((string)$_SESSION['paypal_order_id'], $orderId)) {
    error_log('paypal_capture.php: order mismatch — session=' . ($_SESSION['paypal_order_id'] ?? '') . ' request=' . $orderId);
    echo json_encode(['error' => 'Order verification failed. Please refresh and try again.']);
    exit;
}

$user = $_SESSION['user'] ?? [];
if (empty($user['email'])) {
    echo json_encode(['error' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $token  = paypal_get_token();
    $result = paypal_capture_order($token, $orderId);
    $status = $result['status'] ?? '';

    if ($status !== 'COMPLETED') {
        error_log('paypal_capture.php: unexpected status "' . $status . '" for order ' . $orderId);
        echo json_encode(['error' => 'Payment status: ' . $status . '. Please contact support@diafitus.com']);
        exit;
    }

    // Mark session as paid — success.php will call lead_mark_paid and send emails
    $_SESSION['paypal_paid']    = true;
    $_SESSION['paypal_capture'] = $result;

    echo json_encode(['ok' => true]);
} catch (Throwable $ex) {
    error_log('paypal_capture.php: ' . $ex->getMessage());
    echo json_encode(['error' => 'Capture failed. Your PayPal account has not been charged. Please contact support@diafitus.com']);
}
