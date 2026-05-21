<?php
$pageTitle = 'Create your DiaFitus account';
$bodyClass = 'signup-page';
require __DIR__ . '/includes/header.php';

$plans = cfg('plans');
$default = cfg('default_plan');
$planKey = $_GET['plan'] ?? ($_SESSION['plan'] ?? $default);
if (!isset($plans[$planKey])) $planKey = $default;
$_SESSION['plan'] = $planKey;
$plan = $plans[$planKey];

$priceReg = (float) $plan['price_regular'];
$priceNow = (float) $plan['price_today'];
$savings  = $priceReg - $priceNow;
$pct      = $priceReg > 0 ? (int) round(($savings / $priceReg) * 100) : 0;

$answers  = answers();
$prefillEmail = $answers['email'] ?? '';
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <div class="timer-pill">
      <span class="dot pulse"></span>
      <span><?= (int) $pct ?>% off ends in <strong id="timer">15:00</strong></span>
    </div>
  </header>

  <main class="signup-main">
    <div class="signup-card">
      <span class="pill green">Step 2 of 2 — Create your account</span>
      <h1>Last step: connect to your dashboard</h1>
      <p class="sub">You'll get your nutrition PDF, exercise program and Telegram invite at the email below. Your private logging dashboard lives at <strong>my.diafitus.com</strong>.</p>

      <div class="plan-pick">
        <p class="kicker">Selected plan</p>
        <strong><?= e($plan['name']) ?></strong>
        <p class="muted">Switch:
          <?php foreach ($plans as $k => $p): if ($k === $planKey) continue; ?>
            <a href="/signup?plan=<?= e($k) ?>"><?= e($p['name']) ?> — $<?= number_format($p['price_today'], 2) ?></a>
          <?php endforeach; ?>
        </p>
      </div>

      <form id="signupForm" class="form" action="/create_checkout" method="post">
        <input type="hidden" name="plan" value="<?= e($planKey) ?>" />
        <label>
          First name
          <input type="text" name="firstName" required placeholder="Alex" />
        </label>
        <label>
          Email
          <input type="email" name="email" required placeholder="you@example.com" value="<?= e($prefillEmail) ?>" />
        </label>
        <label>
          Phone number
          <input type="tel" name="phone" required placeholder="+1 555 123 4567" />
        </label>

        <div class="order-box">
          <div class="row"><span><?= e($plan['name']) ?></span><span>$<?= number_format($priceReg, 2) ?></span></div>
          <div class="row discount"><span>Launch discount (<?= (int) $pct ?>%)</span><span>−$<?= number_format($savings, 2) ?></span></div>
          <div class="row total"><span>Today's total</span><span><strong>$<?= number_format($priceNow, 2) ?></strong></span></div>
        </div>

        <label class="check">
          <input type="checkbox" name="agreed" value="1" required />
          <span>I agree to the <a href="/terms" target="_blank">Terms &amp; Conditions</a> and <a href="/privacy" target="_blank">Privacy Policy</a>. I understand DiaFitus is fitness coaching, not medical advice, and I am responsible for consulting my doctor.</span>
        </label>

        <button type="submit" class="btn btn-primary btn-xl">Continue to secure checkout →</button>
        <p class="micro">You'll be redirected to Stripe to complete payment. 14-day money-back guarantee.</p>
      </form>
    </div>

    <aside class="signup-side">
      <h3>What happens next</h3>
      <ol class="next-steps">
        <li>You pay securely via Stripe ($<?= number_format($priceNow, 2) ?>)</li>
        <li>You receive a welcome email with a link to set your password</li>
        <li>You sign in at <strong>diafitus.com/login</strong></li>
        <li>Our team builds your personalized program and reaches out within 24 hours</li>
      </ol>
      <div class="trust">
        <div>🔒 Bank-level encryption (Stripe)</div>
        <div>👨‍⚕️ Reviewed by licensed doctors</div>
        <div>↩️ 14-day money-back guarantee</div>
      </div>
    </aside>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
