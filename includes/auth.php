<?php
require_once __DIR__ . '/db.php';

function generate_password($len = 12) {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alpha[random_int(0, strlen($alpha) - 1)];
    }
    return $out;
}

function lead_find_by_email($email) {
    return db_get('SELECT * FROM leads WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
}

function lead_find_by_id($id) {
    return db_get('SELECT * FROM leads WHERE id = ? LIMIT 1', [(int)$id]);
}

function lead_upsert_from_assessment($email, $phone, $firstName, array $answers) {
    $email = strtolower(trim($email));
    $existing = lead_find_by_email($email);
    $json = json_encode($answers, JSON_UNESCAPED_UNICODE);
    if ($existing) {
        db_exec(
            'UPDATE leads SET phone = COALESCE(NULLIF(?, ""), phone),
             first_name = COALESCE(NULLIF(?, ""), first_name),
             answers_json = ?, updated_at = NOW() WHERE id = ?',
            [$phone, $firstName, $json, $existing['id']]
        );
        return (int) $existing['id'];
    }
    return db_insert(
        'INSERT INTO leads (email, phone, first_name, answers_json) VALUES (?, ?, ?, ?)',
        [$email, $phone, $firstName, $json]
    );
}

function lead_mark_paid($email, $stripeCustomer, $stripeSub, $firstName = null, $phone = null, $planDays = 84) {
    $email    = strtolower(trim($email));
    $planDays = max(1, (int) $planDays);
    $lead = lead_find_by_email($email);
    if ($lead) {
        db_exec(
            'UPDATE leads SET paid = 1,
              stripe_customer = COALESCE(NULLIF(?, ""), stripe_customer),
              stripe_sub      = COALESCE(NULLIF(?, ""), stripe_sub),
              first_name      = COALESCE(NULLIF(?, ""), first_name),
              phone           = COALESCE(NULLIF(?, ""), phone),
              plan_days       = ?,
              started_at      = COALESCE(started_at, CURDATE()),
              updated_at      = NOW()
             WHERE id = ?',
            [$stripeCustomer, $stripeSub, $firstName, $phone, $planDays, $lead['id']]
        );
        $id = (int) $lead['id'];
    } else {
        $id = db_insert(
            'INSERT INTO leads (email, phone, first_name, paid, stripe_customer, stripe_sub, plan_days, started_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, CURDATE())',
            [$email, $phone, $firstName, $stripeCustomer, $stripeSub, $planDays]
        );
    }
    return ['id' => $id];
}

/**
 * Generate a one-time token the member uses to set their initial password.
 * Reuses the password_reset columns but with a longer expiry (7 days).
 * Returns the plain token (only stored in DB as a sha256 hash).
 */
function create_account_setup_token($leadId, $hours = 168) {
    $plain   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $plain);
    $expires = (new DateTime('+' . (int)$hours . ' hours'))->format('Y-m-d H:i:s');
    db_exec(
        'UPDATE leads SET password_reset_hash = ?, password_reset_expires = ? WHERE id = ?',
        [$hash, $expires, (int) $leadId]
    );
    return $plain;
}

function login_lead($email, $password) {
    $email = trim((string) $email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $lead = lead_find_by_email($email);
    if (!$lead || empty($lead['password_hash'])) return false;
    if (!password_verify($password, $lead['password_hash'])) return false;
    $_SESSION['member_id'] = (int) $lead['id'];
    session_regenerate_id(true);
    try { db_exec('UPDATE leads SET last_login_at = NOW() WHERE id = ?', [$lead['id']]); } catch (Throwable $ignored) {}
    return $lead;
}

function current_member() {
    if (empty($_SESSION['member_id'])) return null;
    return lead_find_by_id($_SESSION['member_id']);
}

function require_member() {
    $m = current_member();
    if (!$m || !$m['paid']) {
        header('Location: login');
        exit;
    }
    return $m;
}

function logout_member() {
    unset($_SESSION['member_id']);
    session_regenerate_id(true);
}

function member_week_number($lead) {
    return member_program_info($lead)['current'];
}

function member_program_info($lead) {
    $planDays = max(1, (int) ($lead['plan_days'] ?? 84));
    $elapsed  = 0;
    if (!empty($lead['started_at'])) {
        $tz = new DateTimeZone('America/New_York');
        $todayEt = (new DateTime('now', $tz))->format('Y-m-d');
        $elapsed = (int) (new DateTime($lead['started_at']))->diff(new DateTime($todayEt))->days;
    }
    $elapsed    = max(0, min($planDays - 1, $elapsed));
    $pct        = min(100, (int) round((($elapsed + 1) / $planDays) * 100));
    $currentDay = $elapsed + 1;

    if ($planDays <= 7) {
        return [
            'current'      => $currentDay,
            'total'        => $planDays,
            'label'        => 'day',
            'label_plural' => 'days',
            'pct'          => $pct,
            'plan_days'    => $planDays,
            'current_day'  => $currentDay,
            'total_days'   => $planDays,
        ];
    }
    $weeksTotal  = (int) ceil($planDays / 7);
    $currentWeek = min($weeksTotal, (int) floor($elapsed / 7) + 1);
    return [
        'current'      => $currentWeek,
        'total'        => $weeksTotal,
        'label'        => 'week',
        'label_plural' => 'weeks',
        'pct'          => $pct,
        'plan_days'    => $planDays,
        'current_day'  => $currentDay,
        'total_days'   => $planDays,
    ];
}

// ---------- Admin auth ----------
function login_admin($username, $password) {
    $row = db_get('SELECT * FROM admins WHERE username = ? LIMIT 1', [trim($username)]);
    if (!$row) return false;
    if (!password_verify($password, $row['password_hash'])) return false;
    $_SESSION['admin_id'] = (int) $row['id'];
    session_regenerate_id(true);
    return $row;
}

function current_admin() {
    if (empty($_SESSION['admin_id'])) return null;
    return db_get('SELECT id, username FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
}

function require_admin() {
    // Authentication is handled at the web-server level (Hostinger ->
    // "Password Protect Directories" or an IP whitelist on /admin/).
    // We intentionally do not enforce a PHP login here.
}

function logout_admin() {
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}

// ---------- Password reset ----------
function create_password_reset_token($email) {
    $lead = lead_find_by_email($email);
    if (!$lead) return null; // do not reveal whether email exists
    $plain   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $plain);
    $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
    db_exec(
        'UPDATE leads SET password_reset_hash = ?, password_reset_expires = ? WHERE id = ?',
        [$hash, $expires, $lead['id']]
    );
    return ['token' => $plain, 'lead' => $lead];
}

function find_lead_by_reset_token($plainToken) {
    if (!$plainToken || strlen($plainToken) !== 64) return null;
    $hash = hash('sha256', $plainToken);
    return db_get(
        'SELECT * FROM leads
         WHERE password_reset_hash = ? AND password_reset_expires > NOW()
         LIMIT 1',
        [$hash]
    );
}

function consume_password_reset($leadId, $newPassword) {
    db_exec(
        'UPDATE leads
         SET password_hash = ?, password_reset_hash = NULL,
             password_reset_expires = NULL, updated_at = NOW()
         WHERE id = ?',
        [password_hash($newPassword, PASSWORD_BCRYPT), (int) $leadId]
    );
}

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_check($token) {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}
function csrf_input() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '" />';
}
