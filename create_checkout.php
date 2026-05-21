<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/stripe.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup'); exit;
}

$firstName = trim($_POST['firstName'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$agreed    = !empty($_POST['agreed']);

if (!$firstName || !$email || !$phone || !$agreed) {
    $_SESSION['signup_error'] = 'Please fill in all fields and accept the terms.';
    header('Location: signup'); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['signup_error'] = 'Please enter a valid email address.';
    header('Location: signup'); exit;
}

$plans = cfg('plans');
$planKey = $_POST['plan'] ?? ($_SESSION['plan'] ?? cfg('default_plan'));
if (!isset($plans[$planKey])) $planKey = cfg('default_plan');
$_SESSION['plan'] = $planKey;
$plan = $plans[$planKey];

$user = ['firstName' => $firstName, 'email' => $email, 'phone' => $phone];
$_SESSION['user'] = $user;

try {
    $session = stripe_create_checkout_session($user, answers(), $plan);
    if (empty($session['url'])) {
        throw new RuntimeException('Stripe did not return a checkout URL.');
    }
    $_SESSION['stripe_session_id'] = $session['id'] ?? null;
    header('Location: ' . $session['url']);
    exit;
} catch (Throwable $ex) {
    error_log('create_checkout error: ' . $ex->getMessage());
    $_SESSION['signup_error'] = 'Sorry, we could not start checkout. Please try again or contact ' . cfg('support_email') . '.';
    header('Location: signup');
    exit;
}
