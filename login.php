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
.serif{font-family:"Instrument Serif",Georgia,serif;font-weight:400;letter-spacing:-.01em}

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

/* role toggle */
.v3-role-toggle{
  display:inline-flex;background:var(--bg-2);border:1px solid var(--line);
  border-radius:999px;padding:4px;gap:2px;margin-bottom:20px;
}
.v3-role-toggle button{
  padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;color:var(--ink-2);
  display:inline-flex;align-items:center;gap:6px;
}
.v3-role-toggle button.on{background:var(--ink);color:#F4F1E9}
.v3-pip{width:6px;height:6px;border-radius:50%;background:var(--muted);display:inline-block}
.v3-role-toggle button.on .v3-pip{background:#9CC9A8}

.v3-admin-banner{
  display:none;background:#F5E9D2;border:1px dashed #E2C68B;border-radius:11px;
  padding:10px 12px 10px 38px;font-size:12px;color:#7C5215;margin-bottom:18px;position:relative;
}
.v3-admin-banner::before{content:"";position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;border-radius:50%;background:var(--amber)}
.v3-admin-banner::after{content:"!";position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#fff;font-weight:700;font-size:12px;line-height:1}
body[data-role="admin"] .v3-admin-banner{display:block}
body[data-role="admin"] .v3-h1 em{color:var(--amber)}

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

.v3-admin-btn{
  display:none;width:100%;background:var(--ink);color:#F4F1E9;border-radius:12px;padding:14px;
  font-weight:600;font-size:14.5px;align-items:center;justify-content:center;gap:10px;
  box-shadow:0 1px 0 rgba(0,0,0,.1),0 14px 24px -14px rgba(0,0,0,.4);
  transition:background .15s,transform .1s;
}
.v3-admin-btn:hover{background:var(--sage-3);transform:translateY(-1px)}
body[data-role="admin"] .v3-member-form{display:none}
body[data-role="admin"] .v3-admin-btn{display:flex}
body[data-role="admin"] .v3-sso-section{display:none}
body[data-role="admin"] #v3-signup-line{display:none}

.v3-divider{display:flex;align-items:center;gap:10px;margin:18px 0;color:var(--muted);font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;font-weight:600}
.v3-divider::before,.v3-divider::after{content:"";flex:1;height:1px;background:var(--line)}

.v3-sso{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.v3-sso button{
  display:flex;align-items:center;justify-content:center;gap:10px;
  border:1px solid var(--line);background:#fff;border-radius:11px;padding:11px;
  font-weight:600;font-size:13.5px;color:var(--ink-2);
}
.v3-sso button:hover{border-color:var(--line-2)}

.v3-bottom-note{color:var(--muted);font-size:12.5px;text-align:center;margin-top:24px}
.v3-bottom-note a{color:var(--sage-2);font-weight:600}

.v3-legal{font-size:11.5px;color:var(--muted);text-align:center;margin-top:auto;padding-top:24px}
.v3-legal a{color:var(--muted);text-decoration:underline}

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

<div class="v3-shell" id="v3-shell">

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
          <button type="button" class="on" data-role="member"><span class="v3-pip"></span> Member</button>
          <button type="button" data-role="admin"><span class="v3-pip"></span> Coach / Admin</button>
        </div>

        <div class="v3-admin-banner">Admin access · this area is restricted to coaches and operations staff.</div>

        <div class="v3-eyebrow" id="v3Eyebrow">Welcome back</div>
        <h1 class="v3-h1" id="v3Title">Sign in to <em id="v3Brand">your plan</em>.</h1>
        <p class="v3-lede" id="v3Lede">Pick up where you left off — today's check-in is one tap away. We saved your streak.</p>

        <?php if ($error): ?>
        <div class="v3-error">
          <span>⚠</span>
          <span><?= $error ?></span>
        </div>
        <?php endif; ?>

        <!-- Member login form -->
        <form method="post" class="v3-member-form" autocomplete="on">
          <?= csrf_input() ?>

          <label class="v3-lbl" for="v3Email">Email</label>
          <div class="v3-inp-wrap">
            <span class="v3-ic">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
            </span>
            <input class="v3-inp" id="v3Email" type="email" name="email" placeholder="you@diafitus.com" autocomplete="email" required
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

        <!-- Admin redirect button (shown when role = admin) -->
        <button class="v3-admin-btn" onclick="location.href='/admin/'">
          Open admin console →
        </button>

        <!-- SSO (UI only) -->
        <div class="v3-sso-section">
          <div class="v3-divider">or continue with</div>
          <div class="v3-sso">
            <button type="button">
              <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.6 12.2c0-.7-.1-1.4-.2-2.1H12v4h6c-.3 1.4-1.1 2.6-2.4 3.4v2.8h3.9c2.3-2.1 3.1-5.2 3.1-8.1z"/><path fill="#34A853" d="M12 23c3.2 0 5.9-1 7.8-2.9l-3.9-2.8c-1 .7-2.3 1.1-3.9 1.1-3 0-5.6-2-6.5-4.7H1.4v2.9C3.4 20.4 7.4 23 12 23z"/><path fill="#FBBC04" d="M5.5 13.7c-.2-.7-.4-1.4-.4-2.2s.1-1.5.4-2.2V6.4H1.4C.5 8.1 0 10 0 12s.5 3.9 1.4 5.6l4.1-3.9z"/><path fill="#EA4335" d="M12 4.7c1.7 0 3.3.6 4.5 1.7L20 3c-2.1-2-4.8-3-8-3-4.6 0-8.6 2.6-10.6 6.4l4.1 2.9c.9-2.7 3.5-4.6 6.5-4.6z"/></svg>
              Google
            </button>
            <button type="button">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.3 12.6c0-2.8 2.3-4.1 2.4-4.2-1.3-1.9-3.4-2.2-4.1-2.2-1.7-.2-3.4 1-4.3 1-.9 0-2.3-1-3.8-1-2 .1-3.8 1.1-4.8 2.9-2.1 3.6-.5 8.9 1.5 11.8 1 1.4 2.2 3 3.7 2.9 1.5-.1 2-1 3.8-1s2.3 1 3.8.9c1.6 0 2.6-1.4 3.6-2.8 1.1-1.6 1.5-3.1 1.6-3.2-.1 0-3.1-1.2-3.4-4.1zM14.4 4.4c.8-1 1.4-2.4 1.2-3.8-1.2.1-2.6.8-3.4 1.8-.7.9-1.4 2.3-1.2 3.6 1.4.2 2.7-.6 3.4-1.6z"/></svg>
              Apple
            </button>
          </div>
        </div>

        <div class="v3-bottom-note" id="v3-signup-line">
          New to diafitus? <a href="/questionnaire">Take the assessment →</a>
        </div>

        <div class="v3-legal">
          By signing in you agree to our <a href="#">Terms</a> &amp; <a href="#">Privacy</a>.
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
(function(){
  var body = document.body;
  var toggle = document.getElementById('v3RoleToggle');
  var title = document.getElementById('v3Title');
  var lede = document.getElementById('v3Lede');
  var eyebrow = document.getElementById('v3Eyebrow');
  var signup = document.getElementById('v3-signup-line');
  var brand = document.getElementById('v3Brand');

  toggle.addEventListener('click', function(e){
    var b = e.target.closest('button'); if (!b) return;
    [].forEach.call(toggle.children, function(x){ x.classList.remove('on'); });
    b.classList.add('on');
    var r = b.dataset.role;
    body.setAttribute('data-role', r);
    if (r === 'admin'){
      eyebrow.textContent = 'Restricted access';
      title.innerHTML = 'Coach <em>console</em>.';
      lede.textContent = 'Members, inbox, programs and trends — everything you need to run the day.';
    } else {
      eyebrow.textContent = 'Welcome back';
      title.innerHTML = 'Sign in to <em id="v3Brand">your plan</em>.';
      lede.textContent = "Pick up where you left off — today's check-in is one tap away. We saved your streak.";
    }
  });

  window.v3TogglePw = function(){
    var pw = document.getElementById('v3Pw');
    var btn = document.querySelector('.v3-show-pw');
    if (pw.type === 'password'){ pw.type='text'; btn.textContent='Hide'; }
    else { pw.type='password'; btn.textContent='Show'; }
  };
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
