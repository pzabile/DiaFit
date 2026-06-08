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

      <form id="signupForm" class="form" onsubmit="return false;">
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

        <div id="paypal-button-container" style="margin-top:4px"></div>
        <div id="paypalProcessing" style="display:none;text-align:center;padding:14px 0;color:var(--muted,#888);font-size:14px">
          Processing your payment…
        </div>
        <p class="micro">Secure checkout via PayPal. All sales final once digital content is delivered.</p>
      </form>
    </div>

    <aside class="signup-side">
      <h3>What happens next</h3>
      <ol class="next-steps">
        <li>You pay securely via PayPal ($<?= number_format($priceNow, 2) ?>)</li>
        <li>You receive a welcome email with a link to set your password</li>
        <li>You sign in at <strong>diafitus.com/login</strong></li>
        <li>Our team builds your personalized program and reaches out within 24 hours</li>
      </ol>
      <div class="trust">
        <div>🔒 Bank-level encryption (PayPal)</div>
        <div>👨‍⚕️ Reviewed by licensed doctors</div>
        <div>🛡️ Buyer protection via PayPal</div>
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
<?php
$ppClientId = urlencode(cfg('paypal.client_id'));
$ppCurrency = strtoupper(cfg('currency', 'usd'));
?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= $ppClientId ?>&currency=<?= $ppCurrency ?>&intent=capture"></script>
<script>
(function() {
  function getFormData() {
    var f = document.getElementById('signupForm');
    return {
      firstName:  f.querySelector('[name=firstName]').value.trim(),
      email:      f.querySelector('[name=email]').value.trim(),
      phone:      f.querySelector('[name=phone]').value.trim(),
      plan:       f.querySelector('[name=plan]').value,
      agreed:     f.querySelector('[name=agreed]').checked,
      promo_code: document.getElementById('promoHidden').value || ''
    };
  }

  function validateForm(d) {
    if (!d.firstName) return 'Please enter your first name.';
    if (!d.email || d.email.indexOf('@') < 0) return 'Please enter a valid email address.';
    if (!d.phone) return 'Please enter your phone number.';
    if (!d.agreed) return 'Please agree to the Terms & Conditions to continue.';
    return null;
  }

  paypal.Buttons({
    style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },

    onClick: function(data, actions) {
      var err = validateForm(getFormData());
      if (err) { alert(err); return actions.reject(); }
      return actions.resolve();
    },

    createOrder: function() {
      var d = getFormData();
      var fd = new FormData();
      fd.append('firstName',  d.firstName);
      fd.append('email',      d.email);
      fd.append('phone',      d.phone);
      fd.append('plan',       d.plan);
      fd.append('agreed',     '1');
      fd.append('promo_code', d.promo_code);
      return fetch('/paypal_create_order.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (res.error) { alert(res.error); throw new Error(res.error); }
          return res.id;
        });
    },

    onApprove: function(data) {
      document.getElementById('paypal-button-container').style.display = 'none';
      document.getElementById('paypalProcessing').style.display = 'block';
      return fetch('/paypal_capture.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderID: data.orderID })
      })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.ok) {
          window.location.href = '/success?via=paypal';
        } else {
          document.getElementById('paypal-button-container').style.display = 'block';
          document.getElementById('paypalProcessing').style.display = 'none';
          alert('Payment could not be confirmed: ' + (res.error || 'Unknown error') + '\n\nPlease contact support@diafitus.com');
        }
      })
      .catch(function() {
        document.getElementById('paypal-button-container').style.display = 'block';
        document.getElementById('paypalProcessing').style.display = 'none';
        alert('A network error occurred. Please check your connection and try again.');
      });
    },

    onCancel: function() { /* user closed popup — no action needed */ },

    onError: function(err) {
      console.error('PayPal error:', err);
      alert('A payment error occurred. Please try again or contact support@diafitus.com');
    }

  }).render('#paypal-button-container');
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
