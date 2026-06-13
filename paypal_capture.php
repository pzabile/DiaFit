<?php
/**
 * POST /paypal_capture
 * Called by the PayPal JS SDK onApprove callback.
 *
 * This endpoint does ALL the post-payment work:
 *   1. Verify the order against the DB (not the session, so session loss is OK)
 *   2. Capture the PayPal payment
 *   3. Mark the lead as paid in the DB
 *   4. Send the welcome email + PDF
 *   5. Send the Telegram notification
 *
 * success.php only shows the confirmation page — it no longer sends any emails.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/paypal.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/pdf.php';

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

// ── Resolve user info from DB (preferred) or session (fallback) ─────────────
$lead = db_get('SELECT * FROM leads WHERE paypal_order_id = ? LIMIT 1', [$orderId]);

if ($lead) {
    $email     = $lead['email'];
    $firstName = $lead['first_name'] ?? '';
    $phone     = $lead['phone'] ?? '';
    $planDays  = (int)($lead['plan_days'] ?? 84);
    // Map plan_days back to plan key for price lookups
    $planKey   = $planDays <= 7 ? 'week' : ($planDays <= 28 ? 'month' : 'quarter');
} else {
    // Session fallback (covers first-load where DB upsert may have been skipped)
    if (empty($_SESSION['paypal_order_id']) || !hash_equals((string)$_SESSION['paypal_order_id'], $orderId)) {
        error_log('paypal_capture: order not in DB and session mismatch for ' . $orderId);
        echo json_encode(['error' => 'Order not found. Please contact support@diafitus.com']);
        exit;
    }
    $sess = $_SESSION['user'] ?? [];
    if (empty($sess['email'])) {
        echo json_encode(['error' => 'Session expired. Please start checkout again.']);
        exit;
    }
    $email     = $sess['email'];
    $firstName = $sess['firstName'] ?? '';
    $phone     = $sess['phone'] ?? '';
    $planKey   = $_SESSION['paypal_plan'] ?? cfg('default_plan');
    $plans     = cfg('plans');
    if (!isset($plans[$planKey])) $planKey = cfg('default_plan');
    $planDays  = (int)$plans[$planKey]['days'];
}

// ── Capture payment ──────────────────────────────────────────────────────────
try {
    $token  = paypal_get_token();
    $result = paypal_capture_order($token, $orderId);
    $status = $result['status'] ?? '';

    if ($status !== 'COMPLETED') {
        error_log('paypal_capture: status "' . $status . '" for order ' . $orderId);
        echo json_encode(['error' => 'Payment not completed (status: ' . $status . '). Please contact support@diafitus.com']);
        exit;
    }
} catch (Throwable $ex) {
    error_log('paypal_capture capture call: ' . $ex->getMessage());
    echo json_encode(['error' => 'Capture failed. Your card has NOT been charged. Please contact support@diafitus.com']);
    exit;
}

$amtCents = paypal_captured_cents($result);

// ── Mark paid in DB ──────────────────────────────────────────────────────────
try {
    $res    = lead_mark_paid($email, 'paypal', $orderId, $firstName, $phone, $planDays);
    $leadId = $res['id'];
} catch (Throwable $ex) {
    error_log('paypal_capture lead_mark_paid: ' . $ex->getMessage());
    echo json_encode(['error' => 'Payment captured but account setup failed. Email support@diafitus.com with order ID: ' . $orderId]);
    exit;
}

// Update session so success.php can display the name/email
$_SESSION['user']        = ['firstName' => $firstName, 'email' => $email, 'phone' => $phone];
$_SESSION['paypal_paid'] = true;

// ── Send welcome email + notifications (deduplicated) ────────────────────────
if (empty($_SESSION['welcome_sent_' . $leadId])) {
    $_SESSION['welcome_sent_' . $leadId] = true;

    // Build account-setup link
    $setupUrl = rtrim(cfg('site_url'), '/') . '/login';
    try {
        $tok      = create_account_setup_token($leadId, 168);
        $setupUrl = rtrim(cfg('site_url'), '/') . '/setup?token=' . $tok;
    } catch (Throwable $ex) { error_log('paypal setup token: ' . $ex->getMessage()); }

    // Welcome email
    try {
        $sent = send_email(
            $email, $firstName ?: 'there',
            'Welcome to DiaFitus — you\'re in 🎉',
            account_setup_email_html($firstName ?: 'there', $setupUrl, $email),
            null,
            nutrition_guide_attachment()
        );
        if (!$sent) error_log('paypal welcome email returned false for ' . $email);
    } catch (Throwable $ex) { error_log('paypal welcome email: ' . $ex->getMessage()); }

    // Telegram + PDF
    try {
        // Pull answers from session (filled during questionnaire) or DB
        $answers = answers();
        if (empty($answers) && !empty($lead['answers_json'])) {
            $answers = json_decode($lead['answers_json'], true) ?: [];
        }

        $amt   = $amtCents !== null ? number_format($amtCents / 100, 2) : '?';
        $lines = ['<b>💸 DiaFitus — NEW PAID MEMBER</b>'];
        $lines[] = 'Amount: $' . $amt . ' (one-time, PayPal)';
        $lines[] = '<b>Name:</b> '  . htmlspecialchars($firstName);
        $lines[] = '<b>Email:</b> ' . htmlspecialchars($email);
        $lines[] = '<b>Phone:</b> ' . htmlspecialchars($phone);
        $lines[] = '';
        foreach ($answers as $k => $v) {
            $val     = is_array($v) ? implode(', ', $v) : $v;
            $lines[] = '<b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $k))) . ':</b> ' . htmlspecialchars($val);
        }
        tg_send_message(implode("\n", $lines));

        $tmpPdf = sys_get_temp_dir() . '/diafitus_paid_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
        build_lead_pdf($tmpPdf, $answers, ['firstName' => $firstName, 'email' => $email, 'phone' => $phone]);
        tg_send_document($tmpPdf, 'New paid member — questionnaire (PDF)');
        @unlink($tmpPdf);
    } catch (Throwable $ex) { error_log('paypal telegram: ' . $ex->getMessage()); }
}

// Clear the pending order ID so it can't be replayed
try { db_exec('UPDATE leads SET paypal_order_id = NULL WHERE id = ?', [$leadId]); } catch (Throwable $ignored) {}

echo json_encode(['ok' => true]);
