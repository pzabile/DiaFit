<?php
require __DIR__ . '/includes/avatar.php';
$pageTitle = 'Your DiaFitus Plan is Ready';
$bodyClass = 'offer';
require __DIR__ . '/includes/header.php';

$answers = answers();
$locMap = ['gym' => 'at the gym', 'home' => 'at home', 'both' => 'gym + home', 'outdoors' => 'outdoors'];
$planLoc  = isset($answers['location']) ? ($locMap[$answers['location']] ?? 'gym or home') : 'gym or home';
$planDays = isset($answers['days_per_week'])
    ? $answers['days_per_week'] . ' days/week, ' . ($answers['minutes_per_day'] ?? '30') . ' min/day'
    : 'your chosen schedule';

$plans = cfg('plans');
$default = cfg('default_plan');

function discount_pct($r, $t) { return $r > 0 ? (int) round((($r - $t) / $r) * 100) : 0; }
function per_day($t, $d)      { return $d > 0 ? number_format($t / $d, 2) : '0.00'; }
?>
  <header class="nav slim">
    <a href="/" class="brand">
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
      <p class="lede">Based on your answers, we've prepared a glucose-aware training plan, a custom nutrition PDF, and 24/7 access to your coach. Choose how long you want to start with — you'll see your first wins by week 2.</p>

      <div class="summary-card">
        <h3>What's in your plan</h3>
        <ul class="check-list">
          <li>Personalized training program — <?= e($planLoc) ?>, <?= e($planDays) ?></li>
          <li>Nutrition PDF — what to eat before / during / after workouts</li>
          <li>Glucose-safe exercise progression reviewed by our medical team</li>
          <li>24/7 access to your coach — message us any time</li>
          <li>Private dashboard to log workouts, meals, glucose &amp; meal photos</li>
          <li>Weekly plan adjustments based on your check-ins</li>
        </ul>
      </div>
    </section>

    <section class="plan-banner">
      <strong>🎯 Get visible results in 4 weeks.</strong>
      <span>Members typically see their first stable blood-sugar week by day 14 and sustained energy by week 4.</span>
    </section>

    <section class="plans-grid">
      <?php foreach ($plans as $key => $p):
        $pct  = discount_pct($p['price_regular'], $p['price_today']);
        $perD = per_day($p['price_today'], $p['days']);
        $popular = !empty($p['most_popular']);
      ?>
        <div class="plan-card<?= $popular ? ' popular' : '' ?>">
          <?php if ($popular): ?><div class="popular-banner">⭐ Most popular · <?= $pct ?>% off</div><?php endif; ?>
          <?php if (!empty($p['badge']) && !$popular): ?><div class="plan-badge"><?= e($p['badge']) ?></div><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <div class="plan-price">
            <span class="old">$<?= number_format($p['price_regular'], 2) ?></span>
            <span class="now">$<?= number_format($p['price_today'], 2) ?></span>
          </div>
          <div class="plan-perday">
            <strong>$<?= $perD ?></strong><span>/day</span>
          </div>
          <div class="plan-discount"><?= $pct ?>% off · save $<?= number_format($p['price_regular'] - $p['price_today'], 2) ?></div>
          <a href="/signup?plan=<?= e($key) ?>" class="btn btn-primary plan-cta">
            Choose <?= e($p['name']) ?>
          </a>
          <ul class="plan-includes">
            <li>✓ Full personalized program</li>
            <li>✓ Nutrition PDF guide</li>
            <li>✓ 24/7 coach access</li>
            <li>✓ Dashboard tracking</li>
            <?php if ($p['days'] >= 28): ?><li>✓ Weekly plan adjustments</li><?php endif; ?>
            <?php if ($p['days'] >= 84): ?><li>✓ 12-week complete transformation</li><?php endif; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="micro-trust-row">
      <span>🔒 Secure Stripe checkout</span>
      <span>↩️ Cancel anytime</span>
      <span>🛡️ 14-day money-back guarantee</span>
      <span>👨‍⚕️ Doctor-reviewed</span>
    </section>

    <section class="testimonials reviews-block">
      <h2>What members are saying</h2>
      <p class="muted" style="margin-bottom:1.5rem">4.9 / 5 average from 3,400+ verified members. Hover the strip to pause.</p>
      <?php
      $reviews = [
        ['Marcus T.', 'Type 2',       'A1C: 8.1 → 6.4 in four months. The coaches actually understand diabetes.'],
        ['Lena R.',   'Type 1',       'Finally a program that does not crash my blood sugar. The 24/7 support is gold.'],
        ['David P.',  'Pre-diabetes', 'Lost 12 kg, off two medications. I would pay much more.'],
        ['Aisha K.',  'Type 2',       'Stronger at 49 than I was at 35. The plan respected my limits.'],
        ['Tom B.',    'Type 1',       'The nutrition PDF alone is worth the subscription.'],
        ['Priya N.',  'Gestational',  'Safe pregnancy routine, doctor-approved within a day. Reassuring.'],
        ['Carlos M.', 'Type 2',       '58 and never more active. Started with 15-min walks, now 4x at the gym.'],
        ['Hannah G.', 'Type 1',       'Months without a hypo thanks to the pre-workout fueling guide.'],
        ['Yusuf A.',  'Pre-diabetes', 'Fasting glucose 118 → 92. Weekly check-ins keep me on track.'],
        ['Megan F.',  'Type 2',       'Energy in the afternoons came back within three weeks.'],
        ['Rajiv S.',  'Type 2',       'Home program needs zero equipment. Game-changer.'],
        ['Olivia W.', 'Type 1',       '24/7 messaging replies faster than my own clinic.'],
        ['Sophie L.', 'Type 2',       'No more guesswork — I know exactly what to do every single day.'],
        ['Ethan J.',  'Type 1',       'My CGM graphs have never looked this flat. Worth every cent.'],
        ['Nadia O.',  'Pre-diabetes', 'I learned more about nutrition in a week than in years of Googling.'],
      ];
      ?>
      <div class="marquee-wrap">
        <div class="reviews-marquee">
          <?php for ($d = 0; $d < 2; $d++): ?>
            <?php foreach ($reviews as $r): [$name, $tag, $text] = $r; ?>
              <article class="rev-card">
                <header>
                  <div class="rev-avatar"><?= avatar_svg($name, 56) ?></div>
                  <div class="rev-meta">
                    <strong><?= e($name) ?></strong>
                    <small><?= e($tag) ?></small>
                  </div>
                  <div class="rev-stars">★★★★★</div>
                </header>
                <p><?= e($text) ?></p>
              </article>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
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
      <a href="/terms">Terms</a> and <a href="/privacy">Privacy Policy</a>.
    </section>

    <section class="final-cta">
      <h2>Don't miss your launch discount</h2>
      <p>The offer disappears in <strong id="timer3">15:00</strong>. Lock in 62% off across every plan.</p>
      <a href="/signup?plan=<?= e($default) ?>" class="btn btn-primary btn-xl">Start the 12-week transformation →</a>
    </section>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
