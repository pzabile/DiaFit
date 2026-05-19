<?php
$pageTitle = 'Your DiaFitus Plan is Ready';
$bodyClass = 'offer';
require __DIR__ . '/includes/header.php';

$answers = answers();
$locMap = [
    'gym' => 'at the gym',
    'home' => 'at home',
    'both' => 'gym + home',
    'outdoors' => 'outdoors',
];
$planLoc = isset($answers['location']) ? ($locMap[$answers['location']] ?? 'gym or home') : 'gym or home';
$planDays = isset($answers['days_per_week'])
    ? $answers['days_per_week'] . ' days/week, ' . ($answers['minutes_per_day'] ?? '30') . ' min/day'
    : 'your chosen schedule';

$priceReg = (int) cfg('price_regular');
$priceNow = (int) cfg('price_today');
$savings  = $priceReg - $priceNow;
?>
  <header class="nav slim">
    <a href="index.php" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <div class="timer-pill">
      <span class="dot pulse"></span>
      <span>Offer ends in <strong id="timer">15:00</strong></span>
    </div>
  </header>

  <main class="offer-main">
    <section class="offer-hero">
      <span class="pill green">✓ Plan ready</span>
      <h1>Your personalized DiaFitus program is built.</h1>
      <p class="lede">Based on your answers, we've prepared a 12-week glucose-aware training plan,
      a custom nutrition PDF, and a direct line to your coach and doctor on Telegram.</p>

      <div class="summary-card">
        <h3>What's in your plan</h3>
        <ul class="check-list">
          <li>Personalized training program — <?= e($planLoc) ?>, <?= e($planDays) ?></li>
          <li>Nutrition PDF — what to eat before / during / after workouts</li>
          <li>Glucose-safe exercise progression reviewed by a doctor</li>
          <li>Direct chat with coaches &amp; doctors on Telegram, 24/7</li>
          <li>Access to your private dashboard to log workouts, meals &amp; glucose</li>
          <li>Weekly plan adjustments based on your progress</li>
        </ul>
      </div>
    </section>

    <section class="pricing">
      <div class="price-card">
        <div class="discount-banner">⚡ 50% off — today only</div>
        <h2>DiaFitus Coaching</h2>
        <div class="price-row">
          <span class="old-price">$<?= e($priceReg) ?></span>
          <span class="new-price">$<?= e($priceNow) ?><span class="per">/month</span></span>
        </div>
        <p class="save">You save $<?= e($savings) ?> every month. Cancel anytime.</p>

        <div class="countdown">
          <span>This price expires in</span>
          <strong id="timer2">15:00</strong>
        </div>

        <a href="signup.php" class="btn btn-primary btn-xl">Claim 50% off — Start now</a>

        <ul class="micro-trust">
          <li>🔒 Secure Stripe checkout</li>
          <li>↩️ Cancel anytime</li>
          <li>👨‍⚕️ Doctor-reviewed</li>
        </ul>
      </div>

      <div class="benefits">
        <h3>Everything you get for $<?= e($priceNow) ?>/month</h3>
        <div class="benefit"><div class="bi">🏋️</div><div><strong>Personalized exercise program</strong><p>Built around your diabetes type, fitness level and schedule.</p></div></div>
        <div class="benefit"><div class="bi">📕</div><div><strong>Nutrition guide (PDF)</strong><p>A clear, doctor-reviewed guide on what to eat to stabilize blood sugar.</p></div></div>
        <div class="benefit"><div class="bi">💬</div><div><strong>Telegram access to coaches &amp; doctors</strong><p>Ask anything, anytime — real humans on the other end.</p></div></div>
        <div class="benefit"><div class="bi">📊</div><div><strong>Private dashboard</strong><p>Log workouts, meals, blood sugar, soreness and observations.</p></div></div>
        <div class="benefit"><div class="bi">🔄</div><div><strong>Weekly adjustments</strong><p>Your coach reviews your logs and tunes the plan every week.</p></div></div>
      </div>
    </section>

    <section class="testimonials">
      <h2>What members are saying</h2>
      <p class="muted" style="margin-bottom:1.5rem">4.9 / 5 average from 3,400+ verified members.</p>
      <div class="grid-3">
        <?php
        $reviews = [
          ['Marcus T.', 'Type 2',       'A1C: 8.1 → 6.4 in four months. The coaches actually understand diabetes.'],
          ['Lena R.',   'Type 1',       'Finally a program that does not crash my blood sugar. Telegram support is gold.'],
          ['David P.',  'Pre-diabetes', 'Lost 12 kg, off two medications. I would pay much more.'],
          ['Aisha K.',  'Type 2',       'Stronger at 49 than I was at 35. The plan respected my limits.'],
          ['Tom B.',    'Type 1',       'The nutrition PDF alone is worth the subscription.'],
          ['Priya N.',  'Gestational',  'Safe pregnancy routine, doctor-approved within a day. Reassuring.'],
          ['Carlos M.', 'Type 2',       '58 and never more active. Started with 15-min walks, now 4x at the gym.'],
          ['Hannah G.', 'Type 1',       'Months without a hypo thanks to the pre-workout fueling guide.'],
          ['Yusuf A.',  'Pre-diabetes', 'Fasting glucose 118 → 92. Weekly check-ins keep me on track.'],
          ['Megan F.',  'Type 2',       'Energy in the afternoons came back within three weeks.'],
          ['Rajiv S.',  'Type 2',       'Home program needs zero equipment. Game-changer.'],
          ['Olivia W.', 'Type 1',       '24/7 doctor on Telegram replies faster than my own clinic.'],
          ['Sophie L.', 'Type 2',       'No more guesswork — I know exactly what to do every single day.'],
          ['Ethan J.',  'Type 1',       'My CGM graphs have never looked this flat. Worth every cent.'],
          ['Nadia O.',  'Pre-diabetes', 'I learned more about nutrition in a week than in years of Googling.'],
        ];
        foreach ($reviews as $r): [$name, $tag, $text] = $r; ?>
          <div class="testi">
            <div class="stars">★★★★★</div>
            <p><?= e($text) ?></p>
            <span class="who">— <?= e($name) ?> · <?= e($tag) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="guarantee">
      <h3>🛡️ 14-day money-back guarantee</h3>
      <p>Try DiaFitus for two weeks. If it's not for you, email us and we'll refund every cent — no questions asked.</p>
    </section>

    <section class="disclaimer">
      <strong>Important:</strong> DiaFitus is a fitness and lifestyle coaching service. It is not medical advice
      and is not a substitute for consultation with a licensed physician. Always talk to your doctor before starting
      any new exercise or nutrition program, especially with diabetes. Read our
      <a href="terms.php">Terms</a> and <a href="privacy.php">Privacy Policy</a>.
    </section>

    <section class="final-cta">
      <h2>Don't lose your 50% off</h2>
      <p>The discount disappears in <strong id="timer3">15:00</strong>. After that, it's $<?= e($priceReg) ?>/month.</p>
      <a href="signup.php" class="btn btn-primary btn-xl">Lock in $<?= e($priceNow) ?>/month →</a>
    </section>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
