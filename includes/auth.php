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

function lead_mark_paid($email, $stripeCustomer, $stripeSub, $firstName = null, $phone = null) {
    $email = strtolower(trim($email));
    $lead = lead_find_by_email($email);
    $plainPassword = null;
    $hash = null;
    if (!$lead || empty($lead['password_hash'])) {
        $plainPassword = generate_password();
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    if ($lead) {
        db_exec(
            'UPDATE leads SET paid = 1,
              stripe_customer = COALESCE(NULLIF(?, ""), stripe_customer),
              stripe_sub      = COALESCE(NULLIF(?, ""), stripe_sub),
              first_name      = COALESCE(NULLIF(?, ""), first_name),
              phone           = COALESCE(NULLIF(?, ""), phone),
              password_hash   = COALESCE(password_hash, ?),
              started_at      = COALESCE(started_at, CURDATE()),
              updated_at = NOW()
             WHERE id = ?',
            [$stripeCustomer, $stripeSub, $firstName, $phone, $hash, $lead['id']]
        );
        $id = (int) $lead['id'];
    } else {
        $id = db_insert(
            'INSERT INTO leads (email, phone, first_name, paid, stripe_customer, stripe_sub, password_hash, started_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, CURDATE())',
            [$email, $phone, $firstName, $stripeCustomer, $stripeSub, $hash]
        );
    }
    return ['id' => $id, 'password' => $plainPassword];
}

function login_lead($identifier, $password) {
    $identifier = trim((string) $identifier);
    $lead = null;
    if (strpos($identifier, '@') !== false) {
        $lead = lead_find_by_email($identifier);
    } else {
        // Fallback: allow username-style logins (matches first_name exactly).
        $lead = db_get('SELECT * FROM leads WHERE LOWER(first_name) = LOWER(?) LIMIT 1', [$identifier]);
    }
    if (!$lead || empty($lead['password_hash'])) return false;
    if (!password_verify($password, $lead['password_hash'])) return false;
    $_SESSION['member_id'] = (int) $lead['id'];
    session_regenerate_id(true);
    db_exec('UPDATE leads SET last_login_at = NOW() WHERE id = ?', [$lead['id']]);
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
    if (empty($lead['started_at'])) return 1;
    $start = new DateTime($lead['started_at']);
    $now   = new DateTime('today');
    $days  = (int) $start->diff($now)->days;
    return max(1, (int) floor($days / 7) + 1);
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
