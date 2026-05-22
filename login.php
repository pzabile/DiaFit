<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pw    = (string) ($_POST['password'] ?? '');
        if (!$email || !$pw) {
            $error = 'Please enter your email and password.';
        } else {
            try {
                $lead = login_lead($email, $pw);
            } catch (Throwable $ex) {
                error_log('login error: ' . $ex->getMessage());
                $lead = false;
                $error = 'The login system is temporarily unavailable. Please try again in a moment.';
            }
            if (!$error) {
                if ($lead && !empty($lead['paid'])) {
                    header('Location: /dashboard'); exit;
                }
                if ($lead && empty($lead['paid'])) {
                    $error = 'Your account exists but has not been activated yet. If you recently paid, email <a href="mailto:' . e(cfg('support_email')) . '" style="color:#3B6E54;font-weight:700;">' . e(cfg('support_email')) . '</a> and we\'ll activate it right away.';
                } else {
                    $error = 'Email or password incorrect. If you just paid, check your welcome email for the "Create my account" link to set your password.';
                }
            }
        }
    }
}

$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">';

$pageTitle = 'Sign in — DiaFitus';
$bodyClass = 'lv2-page';
require __DIR__ . '/includes/header.php';
?>
<style>
/* ====== Login v2 — design system from Diafitus Portal ====== */
body.lv2-page {
  background: #F4F1E9;
  font-family: "Plus Jakarta Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
  min-height: 100vh;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

/* Ambient background orbs */
.lv2-orb {
  position: fixed;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}
.lv2-orb-1 { width: 520px; height: 520px; background: #B8DFC9; opacity: .36; top: -160px; right: -150px; }
.lv2-orb-2 { width: 360px; height: 360px; background: #DED6BC; opacity: .5; bottom: -110px; left: -110px; }

/* Sticky nav */
.lv2-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 32px;
  border-bottom: 1px solid #E2DCCD;
  background: rgba(244,241,233,.9);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  position: sticky;
  top: 0;
  z-index: 10;
}

/* Brand */
.lv2-brand {
  display: flex;
  align-items: center;
  gap: 11px;
  text-decoration: none;
}
.lv2-brand-mark {
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: #1B201C;
  display: grid;
  place-items: center;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}
.lv2-brand-mark::after {
  content: "";
  position: absolute;
  inset: auto -5px -5px auto;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #4A8A68;
}
.lv2-brand-mark img {
  width: 22px;
  height: 22px;
  filter: brightness(0) invert(1);
  position: relative;
  z-index: 1;
}
.lv2-brand-name {
  font-family: "Instrument Serif", Georgia, serif;
  font-size: 21px;
  letter-spacing: -.015em;
  line-height: 1;
  color: #1B201C;
}
.lv2-brand-name em { font-style: italic; color: #3B6E54; }

.lv2-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #7A8278;
  text-decoration: none;
  padding: 8px 16px;
  border: 1px solid #E2DCCD;
  border-radius: 999px;
  background: #fff;
  transition: color .15s, border-color .15s;
}
.lv2-back:hover { color: #1B201C; border-color: #C8C0AA; }

/* Centered shell */
.lv2-shell {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 69px);
  padding: 48px 20px 80px;
}

/* Main card */
.lv2-card {
  background: #fff;
  border: 1px solid #E2DCCD;
  border-radius: 22px;
  box-shadow:
    0 1px 0 rgba(27,32,28,.03),
    0 2px 4px rgba(27,32,28,.04),
    0 22px 52px -16px rgba(27,32,28,.15);
  width: 100%;
  max-width: 424px;
  padding: 42px 40px 38px;
}

/* Eyebrow */
.lv2-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 10.5px;
  letter-spacing: .16em;
  text-transform: uppercase;
  font-weight: 700;
  color: #4A8A68;
  margin-bottom: 16px;
}
.lv2-eyebrow .dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #4A8A68;
  box-shadow: 0 0 0 3px #E6EFE6;
  flex-shrink: 0;
}

/* Heading */
.lv2-h1 {
  font-family: "Instrument Serif", Georgia, serif;
  font-size: 36px;
  line-height: 1.04;
  letter-spacing: -.025em;
  font-weight: 400;
  color: #1B201C;
  margin: 0 0 8px;
}
.lv2-h1 em { font-style: italic; color: #3B6E54; }

.lv2-sub {
  font-size: 14px;
  line-height: 1.55;
  color: #7A8278;
  margin: 0 0 30px;
}

/* Error alert */
.lv2-alert {
  background: #FDE8E6;
  border: 1px solid #F5C0BA;
  border-radius: 12px;
  padding: 12px 16px;
  font-size: 13.5px;
  line-height: 1.5;
  color: #7D2018;
  margin-bottom: 22px;
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.lv2-alert-icon { flex-shrink: 0; font-size: 14px; margin-top: 2px; }

/* Form */
.lv2-form { display: flex; flex-direction: column; gap: 16px; }
.lv2-field { display: flex; flex-direction: column; gap: 6px; }
.lv2-label {
  font-size: 12.5px;
  font-weight: 700;
  color: #3A413C;
  letter-spacing: .01em;
}
.lv2-input-wrap { position: relative; }
.lv2-input {
  width: 100%;
  background: #F4F1E9;
  border: 1.5px solid #E2DCCD;
  border-radius: 12px;
  padding: 13px 16px;
  font-family: "Plus Jakarta Sans", sans-serif;
  font-size: 15px;
  color: #1B201C;
  outline: none;
  transition: background .15s, border-color .15s, box-shadow .15s;
  -webkit-appearance: none;
  appearance: none;
}
.lv2-input:focus {
  background: #fff;
  border-color: #4A8A68;
  box-shadow: 0 0 0 4px rgba(74,138,104,.14);
}
.lv2-input::placeholder { color: #B0B8AC; }
.lv2-input.has-toggle { padding-right: 46px; }

/* Password show/hide toggle */
.lv2-pw-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: #9AA197;
  display: grid;
  place-items: center;
  padding: 4px;
  border-radius: 6px;
  transition: color .12s;
}
.lv2-pw-toggle:hover { color: #3A413C; }

/* Sign in button */
.lv2-submit {
  background: #1B201C;
  color: #F4F1E9;
  border: none;
  border-radius: 999px;
  padding: 15px 22px;
  font-family: "Plus Jakarta Sans", sans-serif;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  width: 100%;
  letter-spacing: -.01em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 1px 0 rgba(0,0,0,.08), 0 8px 20px -8px rgba(0,0,0,.34);
  transition: background .18s, transform .12s, box-shadow .18s;
  margin-top: 6px;
}
.lv2-submit:hover {
  background: #3B6E54;
  transform: translateY(-1px);
  box-shadow: 0 1px 0 rgba(0,0,0,.08), 0 14px 28px -10px rgba(59,110,84,.38);
}
.lv2-submit:active { transform: none; }
.lv2-submit .arrow { display: inline-block; transition: transform .15s; }
.lv2-submit:hover .arrow { transform: translateX(3px); }

/* Footer links */
.lv2-links {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  margin-top: 24px;
  padding-top: 22px;
  border-top: 1px solid #EDE8DC;
}
.lv2-link {
  font-size: 13.5px;
  color: #7A8278;
  text-decoration: none;
  transition: color .12s;
}
.lv2-link:hover { color: #1B201C; }
.lv2-link-cta {
  font-size: 13.5px;
  color: #7A8278;
  text-decoration: none;
  transition: color .12s;
}
.lv2-link-cta:hover { color: #1B201C; }
.lv2-link-cta strong { color: #3B6E54; font-weight: 700; }

@media (max-width: 480px) {
  .lv2-nav { padding: 14px 20px; }
  .lv2-card { padding: 32px 24px 28px; border-radius: 18px; }
  .lv2-h1 { font-size: 30px; }
  .lv2-orb-1 { width: 320px; height: 320px; }
}
</style>

<div class="lv2-orb lv2-orb-1" aria-hidden="true"></div>
<div class="lv2-orb lv2-orb-2" aria-hidden="true"></div>

<header class="lv2-nav">
  <a href="/" class="lv2-brand">
    <div class="lv2-brand-mark">
      <img src="/assets/logo/logo-mark.svg" alt="" />
    </div>
    <span class="lv2-brand-name">Dia<em>fitus</em></span>
  </a>
  <a href="/" class="lv2-back">← Back to site</a>
</header>

<div class="lv2-shell">
  <div class="lv2-card">

    <div class="lv2-eyebrow"><span class="dot"></span> Member portal</div>
    <h1 class="lv2-h1">Welcome <em>back.</em></h1>
    <p class="lv2-sub">Sign in to your dashboard to track progress and connect with your coach.</p>

    <?php if ($error): ?>
    <div class="lv2-alert">
      <span class="lv2-alert-icon">⚠</span>
      <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <form method="post" class="lv2-form" autocomplete="on">
      <?= csrf_input() ?>

      <div class="lv2-field">
        <label class="lv2-label" for="lv2-email">Email address</label>
        <input
          id="lv2-email"
          class="lv2-input"
          type="email"
          name="email"
          required
          autofocus
          value="<?= e($_POST['email'] ?? '') ?>"
          autocomplete="email"
          placeholder="you@example.com"
        />
      </div>

      <div class="lv2-field">
        <label class="lv2-label" for="lv2-pw">Password</label>
        <div class="lv2-input-wrap">
          <input
            id="lv2-pw"
            class="lv2-input has-toggle"
            type="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="••••••••"
          />
          <button type="button" class="lv2-pw-toggle" onclick="togglePw(this)" aria-label="Show password">
            <svg id="lv2-eye-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="lv2-submit">
        Sign in <span class="arrow">→</span>
      </button>
    </form>

    <div class="lv2-links">
      <a href="/forgot" class="lv2-link">Forgot your password?</a>
      <a href="/questionnaire" class="lv2-link-cta">No account yet? <strong>Take the free assessment →</strong></a>
    </div>

  </div>
</div>

<script>
function togglePw(btn) {
  var f = document.getElementById('lv2-pw');
  var showing = f.type === 'text';
  f.type = showing ? 'password' : 'text';
  btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
  document.getElementById('lv2-eye-icon').innerHTML = showing
    ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
    : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
