<?php
/**
 * POST /paypal_webhook
 * PayPal sends this when a payment event fires, even if the buyer's browser
 * closed before our JS redirect ran. Register this URL in:
 *   developer.paypal.com → Your App → Webhooks → Add Webhook
 *   URL: https://diafitus.com/paypal_webhook
 *   Events: PAYMENT.CAPTURE.COMPLETED
 *
 * Set paypal.webhook_id in config.php after registering.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/paypal.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/pdf.php';

$body  = file_get_contents('php://input');
$event = json_decode($body, true) ?: [];

// Always 200 immediately so PayPal doesn't retry unnecessarily
http_response_code(200);
echo 'OK';

// Only handle completed captures
if (($event['event_type'] ?? '') !== 'PAYMENT.CAPTURE.COMPLETED') exit;

$capture = $event['resource'] ?? [];
$orderId = $capture['supplementary_data']['related_ids']['order_id'] ?? '';
$status  = $capture['status'] ?? '';

if (!$orderId || $status !== 'COMPLETED') {
    error_log('paypal_webhook: skipping — orderId=' . $orderId . ' status=' . $status);
    exit;
}

// If paypal_capture.php already processed this, the paypal_order_id column was cleared
$lead = db_get('SELECT * FROM leads WHERE paypal_order_id = ? LIMIT 1', [$orderId]);
if (!$lead) {
    // Order already processed (column cleared) or unknown — nothing to do
    exit;
}

// Already paid?
if (!empty($lead['paid'])) {
    db_exec('UPDATE leads SET paypal_order_id = NULL WHERE id = ?', [(int)$lead['id']]);
    exit;
}

$email     = $lead['email'];
$firstName = $lead['first_name'] ?? '';
$phone     = $lead['phone'] ?? '';
$planDays  = (int)($lead['plan_days'] ?? 84);
$amtCents  = isset($capture['amount']['value']) ? (float)$capture['amount']['value'] * 100 : null;
$answers   = json_decode($lead['answers_json'] ?? '{}', true) ?: [];

// Mark paid
try {
    $res    = lead_mark_paid($email, 'paypal', $orderId, $firstName, $phone, $planDays);
    $leadId = $res['id'];
} catch (Throwable $ex) {
    error_log('paypal_webhook lead_mark_paid: ' . $ex->getMessage());
    exit;
}

// Send welcome email
try {
    $setupUrl = rtrim(cfg('site_url'), '/') . '/login';
    try {
        $tok      = create_account_setup_token($leadId, 168);
        $setupUrl = rtrim(cfg('site_url'), '/') . '/setup?token=' . $tok;
    } catch (Throwable $ex) { error_log('paypal_webhook setup token: ' . $ex->getMessage()); }

    send_email(
        $email, $firstName ?: 'there',
        'Welcome to DiaFitus — you\'re in 🎉',
        account_setup_email_html($firstName ?: 'there', $setupUrl, $email),
        null,
        nutrition_guide_attachment()
    );
} catch (Throwable $ex) { error_log('paypal_webhook welcome email: ' . $ex->getMessage()); }

// Telegram
try {
    $amt   = $amtCents !== null ? number_format($amtCents / 100, 2) : '?';
    $lines = ['<b>💸 DiaFitus — NEW PAID MEMBER (webhook)</b>'];
    $lines[] = 'Amount: $' . $amt . ' (PayPal)';
    $lines[] = '<b>Name:</b> '  . htmlspecialchars($firstName);
    $lines[] = '<b>Email:</b> ' . htmlspecialchars($email);
    foreach ($answers as $k => $v) {
        $val     = is_array($v) ? implode(', ', $v) : $v;
        $lines[] = '<b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ':</b> ' . htmlspecialchars($val);
    }
    tg_send_message(implode("\n", $lines));
} catch (Throwable $ex) { error_log('paypal_webhook telegram: ' . $ex->getMessage()); }

// Clear order ID (marks as processed for webhook deduplication)
try { db_exec('UPDATE leads SET paypal_order_id = NULL WHERE id = ?', [$leadId]); } catch (Throwable $ignored) {}
