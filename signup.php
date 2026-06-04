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
          <div class="row discount" id="promoRow" style="display:none"><span>Promo code (<span id="promoCodeLbl"></span>)</span><span id="promoSaving"></span></div>
          <div class="row total"><span>Today's total</span><span><strong id="orderTotal">$<?= number_format($priceNow, 2) ?></strong></span></div>
        </div>

        <div style="display:flex;gap:8px;align-items:stretch;margin-bottom:14px">
          <input type="text" id="promoInput" placeholder="Promo code" autocomplete="off"
            style="flex:1;padding:10px 14px;border:1.5px solid #e3e0d6;border-radius:10px;font-size:14px;outline:none">
          <button type="button" onclick="applyPromo()"
            style="padding:10px 18px;background:#0f1a14;color:#fff;border:none;border-radius:10px;font-weight:600;font-size:14px;cursor:pointer">Apply</button>
          <input type="hidden" name="promo_code" id="promoHidden" value="">
        </div>
        <div id="promoMsg" style="font-size:13px;margin:-8px 0 12px;min-height:18px"></div>

        <label class="check">
          <input type="checkbox" name="agreed" value="1" required />
          <span>I agree to the <a href="/terms" target="_blank">Terms &amp; Conditions</a> and <a href="/privacy" target="_blank">Privacy Policy</a>. I understand DiaFitus is fitness coaching, not medical advice, and I am responsible for consulting my doctor.</span>
        </label>

        <button type="submit" class="btn btn-primary btn-xl">Continue to secure checkout →</button>
        <p class="micro">You'll be redirected to Stripe to complete payment. All sales final once digital content is delivered.</p>
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
        <div>🔐 PCI-DSS compliant via Stripe</div>
      </div>
    </aside>
  </main>

<script>
var basePrice = <?= json_encode((float)$priceNow) ?>;
function applyPromo() {
  var code = document.getElementById('promoInput').value.trim().toUpperCase();
  var msg  = document.getElementById('promoMsg');
  if (code === 'JUSTFORYOU') {
    var discount = basePrice * 0.20;
    var newTotal = basePrice - discount;
    document.getElementById('promoHidden').value = code;
    document.getElementById('promoCodeLbl').textContent = code;
    document.getElementById('promoSaving').textContent = '−$' + discount.toFixed(2);
    document.getElementById('promoRow').style.display = '';
    document.getElementById('orderTotal').textContent = '$' + newTotal.toFixed(2);
    msg.textContent = '✓ 20% discount applied!';
    msg.style.color = '#16a36a';
  } else if (code === '') {
    msg.textContent = '';
  } else {
    msg.textContent = 'Invalid promo code.';
    msg.style.color = '#d8493c';
    document.getElementById('promoHidden').value = '';
    document.getElementById('promoRow').style.display = 'none';
    document.getElementById('orderTotal').textContent = '$' + basePrice.toFixed(2);
  }
}
document.getElementById('promoInput').addEventListener('keydown', function(e){
  if (e.key === 'Enter') { e.preventDefault(); applyPromo(); }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
