<?php
// Stripe webhook endpoint — point Stripe to https://YOUR-DOMAIN/webhook.php
// Listens for checkout.session.completed and dispatches Telegram + welcome email.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/stripe.php';
require __DIR__ . '/includes/telegram.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/pdf.php';

$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!stripe_verify_webhook($payload, $sig)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';

if ($type === 'checkout.session.completed') {
    $sess = $event['data']['object'] ?? [];
    $email = $sess['customer_details']['email'] ?? '';
    $name  = $sess['customer_details']['name']  ?? ($sess['metadata']['first_name'] ?? 'there');
    $phone = $sess['metadata']['phone'] ?? '';

    $answers = [
        'diabetes_type'   => $sess['metadata']['diabetes_type']   ?? '',
        'location'        => $sess['metadata']['location']        ?? '',
        'days_per_week'   => $sess['metadata']['days_per_week']   ?? '',
        'minutes_per_day' => $sess['metadata']['minutes_per_day'] ?? '',
    ];

    try {
        if ($email) {
            send_email($email, $name, 'Welcome to DiaFitus — your program is being built', welcome_email_html($name));
        }
    } catch (Throwable $ex) { error_log('webhook welcome email: ' . $ex->getMessage()); }

    try {
        $tmpPdf = sys_get_temp_dir() . '/diafitus_wh_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
        build_lead_pdf($tmpPdf, $answers, ['firstName' => $name, 'email' => $email, 'phone' => $phone]);
        $lines = [
            "<b>💸 DiaFitus — paid (webhook)</b>",
            "Name: " . htmlspecialchars($name),
            "Email: " . htmlspecialchars($email),
            "Phone: " . htmlspecialchars($phone),
            "Stripe session: " . ($sess['id'] ?? ''),
        ];
        tg_send_message(implode("\n", $lines));
        tg_send_document($tmpPdf, 'Paid member — questionnaire (PDF)');
        @unlink($tmpPdf);
    } catch (Throwable $ex) { error_log('webhook telegram: ' . $ex->getMessage()); }
}

http_response_code(200);
echo 'ok';
