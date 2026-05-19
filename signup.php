<?php
$pageTitle = 'Create your DiaFitus account';
$bodyClass = 'signup-page';
require __DIR__ . '/includes/header.php';

$priceReg = (int) cfg('price_regular');
$priceNow = (int) cfg('price_today');
$savings  = $priceReg - $priceNow;
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
      <span>50% off ends in <strong id="timer">15:00</strong></span>
    </div>
  </header>

  <main class="signup-main">
    <div class="signup-card">
      <span class="pill green">Step 2 of 2 — Create your account</span>
      <h1>Last step: connect to your dashboard</h1>
      <p class="sub">You'll get your nutrition PDF, exercise program and Telegram invite at the email below. Your private logging dashboard lives at <strong>my.diafitus.com</strong>.</p>

      <form id="signupForm" class="form" action="create_checkout" method="post">
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
          <div class="row"><span>DiaFitus Coaching</span><span>$<?= e($priceReg) ?>.00</span></div>
          <div class="row discount"><span>Launch discount (50%)</span><span>−$<?= e($savings) ?>.00</span></div>
          <div class="row total"><span>Today's total</span><span><strong>$<?= e($priceNow) ?>.00</strong>/month</span></div>
        </div>

        <label class="check">
          <input type="checkbox" name="agreed" value="1" required />
          <span>I agree to the <a href="terms" target="_blank">Terms &amp; Conditions</a> and <a href="privacy" target="_blank">Privacy Policy</a>. I understand DiaFitus is fitness coaching, not medical advice, and I am responsible for consulting my doctor.</span>
        </label>

        <button type="submit" class="btn btn-primary btn-xl">Continue to secure checkout →</button>
        <p class="micro">You'll be redirected to Stripe to complete payment. Cancel anytime.</p>
      </form>
    </div>

    <aside class="signup-side">
      <h3>What happens next</h3>
      <ol class="next-steps">
        <li>You pay securely via Stripe ($<?= e($priceNow) ?>/month)</li>
        <li>Your account is created on <strong>my.diafitus.com</strong></li>
        <li>You receive a welcome email immediately</li>
        <li>Our coaches &amp; doctors build your program and reach out within 24 hours</li>
      </ol>
      <div class="trust">
        <div>🔒 Bank-level encryption (Stripe)</div>
        <div>👨‍⚕️ Reviewed by licensed doctors</div>
        <div>↩️ 14-day money-back guarantee</div>
      </div>
    </aside>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
