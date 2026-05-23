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

$pageTitle = 'Sign in — DiaFitus';
$bodyClass = 'login-v3-page';
require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --bg:#F4F1E9;--bg-2:#EDE8DC;--card:#FFFFFF;--ink:#1B201C;--ink-2:#3A413C;
  --muted:#7A8278;--line:#E2DCCD;--line-2:#D5CDB8;--sage:#4A8A68;--sage-2:#3B6E54;
  --sage-3:#264033;--sage-tint:#E6EFE6;--amber:#C68A2E;
}
*{box-sizing:border-box}
body.login-v3-page{
  margin:0;padding:0;background:var(--bg);color:var(--ink);
  font-family:"Plus Jakarta Sans","Helvetica Neue",Helvetica,Arial,sans-serif;
  font-size:14.5px;line-height:1.5;-webkit-font-smoothing:antialiased;
}
body.login-v3-page button{font:inherit;color:inherit;cursor:pointer;border:0;background:transparent}
body.login-v3-page input{font:inherit;color:inherit}
body.login-v3-page a{color:inherit;text-decoration:none}

.v3-shell{
  min-height:100vh;display:grid;grid-template-columns:1.05fr 1fr;
}
@media(max-width:900px){.v3-shell{grid-template-columns:1fr}.v3-visual{display:none}}

/* Form side */
.v3-form-side{display:flex;flex-direction:column;padding:32px 56px;min-height:100vh}
@media(max-width:600px){.v3-form-side{padding:24px 24px}}

.v3-brand{display:flex;align-items:center;gap:12px}
.v3-brand-mark{
  width:38px;height:38px;border-radius:12px;background:var(--ink);color:#F4F1E9;
  display:grid;place-items:center;font-weight:700;font-size:16px;letter-spacing:-.02em;
  position:relative;overflow:hidden;
}
.v3-brand-mark::after{content:"";position:absolute;inset:auto -5px -5px auto;width:14px;height:14px;border-radius:50%;background:var(--sage)}
.v3-brand-name{font-family:"Instrument Serif",serif;font-size:22px;letter-spacing:-.01em;line-height:1}
.v3-brand-name em{font-style:italic;color:var(--sage-2)}
.v3-brand-tag{font-size:11px;color:var(--muted);margin-top:2px;letter-spacing:.02em}

.v3-form-wrap{flex:1;display:flex;align-items:center}
.v3-form{max-width:420px;width:100%;margin:0 auto}

.v3-eyebrow{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--sage-2);font-weight:600;display:inline-flex;align-items:center;gap:6px}
.v3-eyebrow::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--sage)}
.v3-h1{font-family:"Instrument Serif",serif;font-size:44px;line-height:1.05;letter-spacing:-.02em;margin:10px 0 10px;font-weight:400}
.v3-h1 em{font-style:italic;color:var(--sage-2)}
.v3-lede{color:var(--muted);margin:0 0 28px;max-width:42ch;font-size:14px}

/* error */
.v3-error{
  background:#FDE8E6;border:1px solid #F5C0BA;border-radius:12px;
  padding:12px 16px;font-size:13.5px;line-height:1.5;color:#7D2018;
  margin-bottom:22px;display:flex;gap:10px;align-items:flex-start;
}

/* inputs */
label.v3-lbl{display:block;font-size:12.5px;font-weight:600;color:var(--ink-2);margin:0 0 5px}
.v3-inp-wrap{position:relative;margin-bottom:14px}
.v3-inp{
  width:100%;border:1px solid var(--line);background:#fff;border-radius:11px;
  padding:13px 14px 13px 42px;font-size:14.5px;outline:none;transition:.15s;
  -webkit-appearance:none;appearance:none;
}
.v3-inp:focus{border-color:var(--sage);box-shadow:0 0 0 4px var(--sage-tint)}
.v3-inp-wrap .v3-ic{
  position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);
  display:grid;place-items:center;pointer-events:none;
}
.v3-show-pw{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  font-size:11px;font-weight:600;color:var(--muted);padding:6px 10px;border-radius:7px;background:var(--bg-2);
}
.v3-show-pw:hover{color:var(--ink)}

.v3-row{display:flex;align-items:center;justify-content:space-between;margin:-4px 0 22px;font-size:13px}
.v3-row label{display:flex;align-items:center;gap:8px;color:var(--ink-2);cursor:pointer}
.v3-row label input{width:16px;height:16px;accent-color:var(--sage)}
.v3-row a{color:var(--sage-2);font-weight:600}

.v3-submit{
  width:100%;background:var(--ink);color:#F4F1E9;border-radius:12px;padding:14px;
  font-weight:600;font-size:14.5px;display:flex;align-items:center;justify-content:center;gap:10px;
  box-shadow:0 1px 0 rgba(0,0,0,.1),0 14px 24px -14px rgba(0,0,0,.4);
  transition:background .15s,transform .1s;
}
.v3-submit:hover{background:var(--sage-3);transform:translateY(-1px)}
.v3-submit:active{transform:none}
.v3-submit .v3-k{font-family:"JetBrains Mono",monospace;font-size:11px;background:rgba(244,241,233,.18);padding:3px 6px;border-radius:5px}

.v3-bottom-note{color:var(--muted);font-size:12.5px;text-align:center;margin-top:24px}
.v3-bottom-note a{color:var(--sage-2);font-weight:600}

.v3-legal{font-size:11.5px;color:var(--muted);text-align:center;margin-top:auto;padding-top:24px}
.v3-legal a{color:var(--muted);text-decoration:underline}

/* role toggle */
.v3-role-toggle{
  display:inline-flex;background:var(--bg-2);border:1px solid var(--line);
  border-radius:999px;padding:4px;gap:2px;margin-bottom:22px;
}
.v3-role-toggle button{
  padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;color:var(--ink-2);
  display:inline-flex;align-items:center;gap:6px;transition:.12s;
}
.v3-role-toggle button.on{background:var(--ink);color:#F4F1E9}
.v3-role-toggle .pip{width:6px;height:6px;border-radius:50%;background:var(--muted)}
.v3-role-toggle button.on .pip{background:#9CC9A8}

/* admin mode banner */
.v3-admin-banner{
  background:#F5E9D2;border:1px dashed #E2C68B;border-radius:11px;
  padding:11px 14px 11px 40px;font-size:12.5px;color:#7C5215;margin-bottom:20px;position:relative;
}
.v3-admin-banner::before{content:"";position:absolute;left:13px;top:50%;transform:translateY(-50%);
  width:18px;height:18px;border-radius:50%;background:var(--amber)}
.v3-admin-banner::after{content:"!";position:absolute;left:19px;top:50%;transform:translateY(-50%);
  color:#fff;font-weight:700;font-size:12px;line-height:1}

/* Visual side */
.v3-visual{
  position:relative;background:linear-gradient(155deg,#1F3A2C 0%,#173023 60%,#102016 100%);
  color:#E6EFE6;overflow:hidden;padding:48px;display:flex;flex-direction:column;justify-content:space-between;
}
.v3-visual::before{
  content:"";position:absolute;top:-120px;right:-90px;width:380px;height:380px;border-radius:50%;
  background:radial-gradient(circle,rgba(74,138,104,.4),transparent 65%);pointer-events:none;
}
.v3-visual::after{
  content:"";position:absolute;bottom:-160px;left:-100px;width:420px;height:420px;border-radius:50%;
  background:radial-gradient(circle,rgba(198,138,46,.18),transparent 60%);pointer-events:none;
}
.v3-v-top{position:relative;z-index:1;display:flex;align-items:center;gap:10px;color:#9CC9A8;font-size:12px;letter-spacing:.14em;text-transform:uppercase;font-weight:600}
.v3-v-top::before{content:"";width:7px;height:7px;border-radius:50%;background:#9CC9A8}

.v3-v-quote{position:relative;z-index:1;max-width:520px}
.v3-v-quote .mark{font-family:"Instrument Serif",serif;font-size:120px;line-height:1;color:#9CC9A8;opacity:.5;letter-spacing:-.02em}
.v3-v-quote blockquote{font-family:"Instrument Serif",serif;font-size:38px;line-height:1.15;letter-spacing:-.015em;margin:-32px 0 18px;font-weight:400}
.v3-v-quote blockquote em{font-style:italic;color:#9CC9A8}
.v3-v-quote .who{display:flex;gap:12px;align-items:center}
.v3-v-quote .who .av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#D6C9A8,#A77F4C);color:#fff;display:grid;place-items:center;font-weight:600;font-size:13px}
.v3-v-quote .who .nm{font-weight:600;font-size:14px}
.v3-v-quote .who .ttl{font-size:11.5px;color:#9CC9A8;letter-spacing:.06em;text-transform:uppercase;font-weight:600}

.v3-v-stats{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);gap:18px;border-top:1px solid #2A4738;padding-top:20px}
.v3-v-stats .lbl{font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:#9CC9A8;font-weight:600;margin-bottom:4px}
.v3-v-stats .num{font-family:"Instrument Serif",serif;font-size:34px;letter-spacing:-.02em;line-height:1}
.v3-v-stats .sub{font-size:11.5px;color:#B5C7BC;margin-top:4px}
</style>

<div class="v3-shell">

  <!-- ============ FORM SIDE ============ -->
  <div class="v3-form-side">

    <div class="v3-brand">
      <div class="v3-brand-mark">
        <img src="/assets/logo/logo-mark.svg" alt="" style="width:22px;height:22px;filter:brightness(0)invert(1);position:relative;z-index:1" onerror="this.style.display='none';this.parentElement.textContent='D'" />
      </div>
      <div>
        <div class="v3-brand-name">Dia<em>fitus</em></div>
        <div class="v3-brand-tag">train smart · log easy · live steady</div>
      </div>
    </div>

    <div class="v3-form-wrap">
      <div class="v3-form">

        <div class="v3-role-toggle" id="v3RoleToggle" role="tablist" aria-label="Sign in as">
          <button type="button" class="on" data-role="member" onclick="v3SetRole('member',this)"><span class="pip"></span> Member</button>
          <button type="button" data-role="admin" onclick="v3SetRole('admin',this)"><span class="pip"></span> Coach / Admin</button>
        </div>

        <div class="v3-eyebrow" id="v3Eyebrow">Welcome back</div>
        <h1 class="v3-h1" id="v3Title">Sign in to <em>your plan</em>.</h1>
        <p class="v3-lede" id="v3Lede">Pick up where you left off — today's check-in is one tap away. We saved your streak.</p>

        <?php if ($error): ?>
        <div class="v3-error">
          <span>⚠</span>
          <span><?= $error ?></span>
        </div>
        <?php endif; ?>

        <!-- Admin mode panel -->
        <div id="v3AdminPanel" style="display:none">
          <div class="v3-admin-banner">Admin access · this area is restricted to coaches and operations staff.</div>
          <a href="/admin/" class="v3-submit" style="text-decoration:none;justify-content:center">
            Enter coach console
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
          <p style="color:var(--muted);font-size:12px;text-align:center;margin:16px 0 0">Your browser will prompt for your coach credentials.</p>
        </div>

        <div id="v3MemberForm">
        <form method="post" autocomplete="on">
          <?= csrf_input() ?>

          <label class="v3-lbl" for="v3Email">Email</label>
          <div class="v3-inp-wrap">
            <span class="v3-ic">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
            </span>
            <input class="v3-inp" id="v3Email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required
              value="<?= e($_POST['email'] ?? '') ?>" autofocus />
          </div>

          <label class="v3-lbl" for="v3Pw">Password</label>
          <div class="v3-inp-wrap">
            <span class="v3-ic">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
            </span>
            <input class="v3-inp" id="v3Pw" type="password" name="password" placeholder="••••••••••" autocomplete="current-password" required />
            <button type="button" class="v3-show-pw" onclick="v3TogglePw()">Show</button>
          </div>

          <div class="v3-row">
            <label><input type="checkbox" checked /> Keep me signed in</label>
            <a href="/forgot">Forgot password?</a>
          </div>

          <button type="submit" class="v3-submit">
            Continue <span class="v3-k">⏎</span>
          </button>
        </form>

        <div class="v3-bottom-note">
          New to diafitus? <a href="/questionnaire">Take the assessment →</a>
        </div>
        </div><!-- /v3MemberForm -->

        <div class="v3-legal">
          By signing in you agree to our <a href="/terms">Terms</a> &amp; <a href="/privacy">Privacy</a>.
        </div>
      </div>
    </div>
  </div>

  <!-- ============ VISUAL SIDE ============ -->
  <aside class="v3-visual">
    <div class="v3-v-top">Member portal · v2</div>

    <div class="v3-v-quote">
      <div class="mark">"</div>
      <blockquote>I stopped guessing whether the workout was helping. Now I can <em>see</em> the line trend down. That changed everything.</blockquote>
      <div class="who">
        <div class="av">PZ</div>
        <div>
          <div class="nm">Paula Z. · type 2</div>
          <div class="ttl">12 weeks in · A1C down 0.6</div>
        </div>
      </div>
    </div>

    <div class="v3-v-stats">
      <div>
        <div class="lbl">Active members</div>
        <div class="num">142</div>
        <div class="sub">+12 this month</div>
      </div>
      <div>
        <div class="lbl">Avg time in range</div>
        <div class="num">81%</div>
        <div class="sub">across cohort</div>
      </div>
      <div>
        <div class="lbl">Coach response</div>
        <div class="num">38<span style="font-family:Plus Jakarta Sans;font-size:14px;color:#B5C7BC;margin-left:4px">min</span></div>
        <div class="sub">median, last 30d</div>
      </div>
    </div>
  </aside>

</div>

<script>
window.v3TogglePw = function(){
  var pw = document.getElementById('v3Pw');
  var btn = document.querySelector('.v3-show-pw');
  if (pw.type === 'password'){ pw.type='text'; btn.textContent='Hide'; }
  else { pw.type='password'; btn.textContent='Show'; }
};

window.v3SetRole = function(role, btn){
  var isAdmin = role === 'admin';
  // toggle active tab style
  document.querySelectorAll('#v3RoleToggle button').forEach(function(b){ b.classList.remove('on'); });
  btn.classList.add('on');
  // toggle panels
  document.getElementById('v3MemberForm').style.display = isAdmin ? 'none' : '';
  document.getElementById('v3AdminPanel').style.display  = isAdmin ? '' : 'none';
  // update heading + lede
  document.getElementById('v3Eyebrow').textContent = isAdmin ? 'Restricted access' : 'Welcome back';
  document.getElementById('v3Title').innerHTML   = isAdmin ? 'Coach <em>console</em>.' : 'Sign in to <em>your plan</em>.';
  document.getElementById('v3Lede').textContent  = isAdmin
    ? 'Members, inbox, programs and trends — everything you need to run the day.'
    : "Pick up where you left off — today's check-in is one tap away. We saved your streak.";
  // visual side label
  var vTop = document.querySelector('.v3-v-top');
  if (vTop) vTop.textContent = isAdmin ? 'Coach console · admin' : 'Member portal · v2';
};
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
