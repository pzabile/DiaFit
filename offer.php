<?php
require __DIR__ . '/includes/avatar.php';
$pageTitle = 'Your DiaFitus Plan is Ready';
$bodyClass = 'offer offer-v2';
require __DIR__ . '/includes/header.php';

$answers = answers();
$plans = cfg('plans');
$default = cfg('default_plan');
$selected = $_GET['plan'] ?? $default;
if (!isset($plans[$selected])) $selected = $default;

function pct($r, $t) { return $r > 0 ? (int) round((($r - $t) / $r) * 100) : 0; }
function perDay($t, $d) { return $d > 0 ? number_format($t / $d, 2) : '0.00'; }
?>
  <style>
  /* ── Before / After ── */
  .before-after-section { margin: 2.5rem 0; text-align: center; }
  .ba-title { font-size: clamp(1.3rem,3vw,1.8rem); font-weight: 700; margin-bottom: .3rem; color: #0f1a14; }
  .ba-sub { font-size: .9rem; color: #6b7a72; margin-bottom: 1.4rem; }
  .ba-cards { display: flex; align-items: stretch; gap: 1rem; justify-content: center; }
  .ba-arrow { font-size: 2rem; color: #16a36a; display: flex; align-items: center; font-weight: 700; flex-shrink: 0; }
  .ba-card {
    flex: 1;
    background: #fff;
    border: 1.5px solid #e3e0d6;
    border-radius: 18px;
    padding: 1.25rem 1.5rem;
    text-align: left;
    min-width: 0;
  }
  .ba-label {
    font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    margin-bottom: 1rem; padding: .25rem .7rem; border-radius: 20px; display: inline-block;
  }
  .ba-label-now  { background: #fde8e6; color: #b83228; }
  .ba-label-goal { background: #d6f0e1; color: #0d7d4f; }
  .ba-stat { padding: .55rem 0; border-bottom: 1px solid #e3e0d6; }
  .ba-stat:last-child { border-bottom: none; }
  .ba-stat-label { font-size: .78rem; color: #8a8f8b; display: block; margin-bottom: .15rem; }
  .ba-stat strong { font-size: .95rem; color: #0f1a14; }
  .ba-green { color: #0d7d4f !important; }
  .ba-dots { display: flex; gap: .35rem; margin-top: .2rem; }
  .ba-dot { width: 26px; height: 9px; border-radius: 4px; background: #e5e0d5; }
  .ba-dot-on   { background: #d8493c; }
  .ba-dot-goal { background: #16a36a; }
  .ba-disclaimer { font-size: .75rem; color: #8a8f8b; margin-top: .85rem; }
  @media (max-width: 500px) {
    .ba-cards { flex-direction: column; }
    .ba-arrow { transform: rotate(90deg); justify-content: center; }
  }

  /* ── What You Get ── */
  .what-you-get {
    margin: 2.5rem 0;
    background: #fff;
    border: 1.5px solid #e3e0d6;
    border-radius: 22px;
    padding: clamp(1.25rem,3vw,2rem);
  }
  .what-you-get h2 {
    font-size: clamp(1.2rem,3vw,1.6rem); font-weight: 700;
    text-align: center; margin: 0 0 1.2rem; color: #0f1a14;
  }
  .wyg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 560px) { .wyg-grid { grid-template-columns: 1fr; } }
  .wyg-item {
    display: flex; align-items: flex-start; gap: 13px;
    border: 1.5px solid #e3e0d6; border-radius: 14px;
    padding: 16px 14px; background: #faf6f0;
  }
  .wyg-icon { font-size: 1.5rem; line-height: 1; flex-shrink: 0; margin-top: 1px; }
  .wyg-item strong { display: block; font-size: .9rem; font-weight: 700; color: #0f1a14; margin-bottom: 4px; }
  .wyg-item p { font-size: .82rem; color: #6b7a72; margin: 0; line-height: 1.5; }
  .wyg-cta { display: block; margin: 1.4rem auto 0; max-width: 340px; text-align: center; }

  /* ── Why it works ── */
  .highlights-section { margin: 2.5rem 0; }
  .highlights-section h2 {
    font-size: clamp(1.2rem,3vw,1.6rem); font-weight: 700;
    text-align: center; margin: 0 0 1.1rem; color: #0f1a14;
  }
  .hl-list { display: flex; flex-direction: column; gap: 10px; }
  .hl-item {
    display: flex; align-items: flex-start; gap: 13px;
    border: 1.5px solid #e3e0d6; border-radius: 14px;
    padding: 16px; background: #fff;
  }
  .hl-icon { font-size: 1.5rem; line-height: 1; flex-shrink: 0; margin-top: 1px; }
  .hl-item strong { display: block; font-size: .9rem; font-weight: 700; color: #0f1a14; margin-bottom: 4px; }
  .hl-item p { font-size: .82rem; color: #6b7a72; margin: 0; line-height: 1.5; }
  </style>
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

  <main class="offer-v2-main">
    <div class="offer-trust-row">
      <span>✓ Tailored <strong>step-by-step</strong> diabetes program</span>
      <span>✓ Based on <strong>doctor-reviewed exercise science</strong></span>
      <span>✓ Built around your <strong>blood sugar response</strong></span>
    </div>

    <section class="offer-card">
      <h1>Get visible results in 4 weeks!</h1>

      <form id="planForm" method="get" action="/signup">
        <?php foreach ($plans as $key => $p):
          $pct  = pct($p['price_regular'], $p['price_today']);
          $perD = perDay($p['price_today'], $p['days']);
          $isPop = !empty($p['most_popular']);
          $isSel = $key === $selected;
        ?>
          <label class="plan-radio<?= $isPop ? ' popular' : '' ?><?= $isSel ? ' selected' : '' ?>">
            <?php if ($isPop): ?>
              <div class="popular-ribbon">👍 MOST POPULAR · <?= $pct ?>% OFF</div>
            <?php endif; ?>
            <input type="radio" name="plan" value="<?= e($key) ?>" <?= $isSel ? 'checked' : '' ?> />
            <span class="radio-dot"></span>
            <div class="plan-info">
              <strong><?= e(strtoupper($p['name'])) ?></strong>
              <div class="plan-money">
                <span class="old">$<?= number_format($p['price_regular'], 2) ?></span>
                <span class="new">$<?= number_format($p['price_today'], 2) ?></span>
              </div>
            </div>
            <div class="price-tag<?= $isPop ? ' pop' : '' ?>">
              <span class="dollar">$</span>
              <strong><?= explode('.', $perD)[0] ?></strong>
              <span class="cents">.<?= explode('.', $perD)[1] ?? '00' ?></span>
              <small>per day</small>
            </div>
          </label>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-orange btn-xl get-plan-btn">GET MY PLAN</button>

        <label class="agree-row">
          <input type="checkbox" required />
          <span>I agree to the <a href="/terms" target="_blank">Terms &amp; Conditions</a> and <a href="/privacy" target="_blank">Privacy Policy</a>.</span>
        </label>

        <p class="bill-note" id="billNote">
          By clicking "GET MY PLAN", I agree to pay
          <strong id="billPrice">$<?= number_format($plans[$selected]['price_today'], 2) ?></strong>
          for my <span id="billPlan"><?= e($plans[$selected]['name']) ?></span> one-time.
          After the plan period ends, I'll be invited to renew at the same price — DiaFitus will not auto-charge me again.
          DiaFitus is fitness coaching and is not medical advice. All sales are final once digital content is delivered (see <a href="/terms" target="_blank">Terms</a>).
        </p>

        <div class="safe-checkout">
          <p>GUARANTEED <strong>SAFE CHECKOUT</strong></p>
          <div class="pay-badges">
            <span>Visa</span><span>Mastercard</span><span>Amex</span><span>Discover</span><span>Apple&nbsp;Pay</span><span>Google&nbsp;Pay</span><span>Stripe</span>
          </div>
        </div>
      </form>
    </section>

    <!-- BEFORE / AFTER -->
    <section style="margin:2.5rem 0;text-align:center">
      <h2 style="font-size:clamp(1.3rem,3vw,1.8rem);font-weight:700;margin-bottom:.3rem;color:#0f1a14">Your transformation, step by step</h2>
      <p style="font-size:.9rem;color:#6b7a72;margin-bottom:1.4rem">Based on answers from members with a similar profile</p>
      <div style="display:flex;align-items:stretch;gap:1rem;justify-content:center;flex-wrap:wrap">
        <div style="flex:1;min-width:200px;max-width:280px;background:#fff;border:1.5px solid #e3e0d6;border-radius:18px;padding:1.25rem 1.5rem;text-align:left">
          <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:1rem;padding:.25rem .7rem;border-radius:20px;display:inline-block;background:#fde8e6;color:#b83228">Now</div>
          <div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Blood sugar</span>
              <strong style="font-size:.95rem;color:#0f1a14">Elevated</strong>
            </div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Energy levels</span>
              <strong style="font-size:.95rem;color:#0f1a14">Low</strong>
            </div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Fitness level</span>
              <div style="display:flex;gap:.35rem;margin-top:.2rem">
                <span style="width:26px;height:9px;border-radius:4px;background:#d8493c;display:inline-block"></span>
                <span style="width:26px;height:9px;border-radius:4px;background:#e5e0d5;display:inline-block"></span>
                <span style="width:26px;height:9px;border-radius:4px;background:#e5e0d5;display:inline-block"></span>
              </div>
            </div>
            <div style="padding:.55rem 0">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">A1C control</span>
              <strong style="font-size:.95rem;color:#0f1a14">Needs work</strong>
            </div>
          </div>
        </div>
        <div style="font-size:2rem;color:#16a36a;display:flex;align-items:center;font-weight:700;flex-shrink:0">&#187;</div>
        <div style="flex:1;min-width:200px;max-width:280px;background:#fff;border:1.5px solid #e3e0d6;border-radius:18px;padding:1.25rem 1.5rem;text-align:left">
          <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-bottom:1rem;padding:.25rem .7rem;border-radius:20px;display:inline-block;background:#d6f0e1;color:#0d7d4f">After 28 Days</div>
          <div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Blood sugar</span>
              <strong style="font-size:.95rem;color:#0d7d4f">More stable</strong>
            </div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Energy levels</span>
              <strong style="font-size:.95rem;color:#0d7d4f">Noticeably higher</strong>
            </div>
            <div style="padding:.55rem 0;border-bottom:1px solid #e3e0d6">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">Fitness level</span>
              <div style="display:flex;gap:.35rem;margin-top:.2rem">
                <span style="width:26px;height:9px;border-radius:4px;background:#16a36a;display:inline-block"></span>
                <span style="width:26px;height:9px;border-radius:4px;background:#16a36a;display:inline-block"></span>
                <span style="width:26px;height:9px;border-radius:4px;background:#16a36a;display:inline-block"></span>
              </div>
            </div>
            <div style="padding:.55rem 0">
              <span style="font-size:.78rem;color:#8a8f8b;display:block;margin-bottom:.15rem">A1C control</span>
              <strong style="font-size:.95rem;color:#0d7d4f">Trending down</strong>
            </div>
          </div>
            <div class="ba-stat">
              <span class="ba-stat-label">A1C control</span>
              <strong class="ba-green">Trending down</strong>
            </div>
          </div>
        </div>
      </div>
      <p style="font-size:.75rem;color:#8a8f8b;margin-top:.85rem">Individual results vary. Based on member-reported outcomes.</p>
    </section>

    <!-- WHAT YOU GET -->
    <section class="what-you-get">
      <h2>Everything included in your plan</h2>
      <div class="wyg-grid">
        <div class="wyg-item">
          <div class="wyg-icon">🩸</div>
          <div>
            <strong>Blood-sugar-aware workouts</strong>
            <p>Every exercise is chosen to help stabilize glucose — safe for Type&nbsp;1, Type&nbsp;2 &amp; pre-diabetes.</p>
          </div>
        </div>
        <div class="wyg-item">
          <div class="wyg-icon">🥗</div>
          <div>
            <strong>Personalized meal guide</strong>
            <p>Eat the foods you love with a nutrition plan built around your blood sugar response, not a generic diet.</p>
          </div>
        </div>
        <div class="wyg-item">
          <div class="wyg-icon">💬</div>
          <div>
            <strong>24/7 coach messaging</strong>
            <p>Message your coach any time — questions about your glucose, your workout, or your next meal are answered fast.</p>
          </div>
        </div>
        <div class="wyg-item">
          <div class="wyg-icon">📊</div>
          <div>
            <strong>Progress &amp; glucose tracker</strong>
            <p>Log weight, steps, and readings in one place. See your trends and celebrate every milestone.</p>
          </div>
        </div>
        <div class="wyg-item">
          <div class="wyg-icon">🏠</div>
          <div>
            <strong>100+ home-friendly exercises</strong>
            <p>No gym, no equipment needed. Short, effective sessions you can fit around your day.</p>
          </div>
        </div>
        <div class="wyg-item">
          <div class="wyg-icon">📅</div>
          <div>
            <strong>Weekly check-ins</strong>
            <p>Your coach reviews your week and adjusts the plan so you're always moving in the right direction.</p>
          </div>
        </div>
      </div>
      <a href="#planForm" class="btn btn-orange btn-xl wyg-cta">GET MY PLAN</a>
    </section>

    <!-- HIGHLIGHTS -->
    <section class="highlights-section">
      <h2>Why DiaFitus works for diabetics</h2>
      <div class="hl-list">
        <div class="hl-item">
          <div class="hl-icon">🎯</div>
          <div>
            <strong>Built specifically for diabetes</strong>
            <p>Every workout, meal tip, and check-in is designed with blood sugar management at its core — not adapted from a generic fitness app.</p>
          </div>
        </div>
        <div class="hl-item">
          <div class="hl-icon">🔬</div>
          <div>
            <strong>Doctor-reviewed exercise science</strong>
            <p>Our protocols are based on clinical research on exercise and glucose metabolism, reviewed by licensed physicians.</p>
          </div>
        </div>
        <div class="hl-item">
          <div class="hl-icon">⚡</div>
          <div>
            <strong>Feel results in the first week</strong>
            <p>Members report steadier energy and fewer spikes within days of starting — not months.</p>
          </div>
        </div>
        <div class="hl-item">
          <div class="hl-icon">🏆</div>
          <div>
            <strong>3,400+ members, 4.9-star rating</strong>
            <p>Real people with Type 1, Type 2, pre-diabetes and gestational diabetes — all seeing real results.</p>
          </div>
        </div>
        <div class="hl-item">
          <div class="hl-icon">🔒</div>
          <div>
            <strong>Safe &amp; private — no auto-billing</strong>
            <p>One-time payment, no recurring charges. Your data stays private and you stay in full control.</p>
          </div>
        </div>
      </div>
      <a href="#planForm" class="btn btn-orange btn-xl wyg-cta">GET MY PLAN →</a>
    </section>

    <section class="testimonials reviews-block">
      <h2 style="text-align:center">What members are saying</h2>
      <p class="muted" style="text-align:center;margin-bottom:1.5rem">4.9 / 5 average from 3,400+ verified members. Hover the strip to pause.</p>
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

    <section class="disclaimer">
      <strong>Important:</strong> DiaFitus is a fitness and lifestyle coaching service. It is not medical advice and is not a substitute for consultation with a licensed physician. Always talk to your doctor before starting any new exercise or nutrition program, especially with diabetes. All sales are final once digital content is delivered. Read our <a href="/terms">Terms &amp; Conditions</a> and <a href="/privacy">Privacy Policy</a>.
    </section>
  </main>

  <script>
    (function () {
      const form = document.getElementById('planForm');
      if (!form) return;
      const radios = form.querySelectorAll('input[name="plan"]');
      const billPrice = document.getElementById('billPrice');
      const billPlan  = document.getElementById('billPlan');
      const plans = <?= json_encode(array_map(fn($p) => ['name' => $p['name'], 'price' => $p['price_today']], $plans)) ?>;
      radios.forEach(r => r.addEventListener('change', () => {
        form.querySelectorAll('.plan-radio').forEach(el => el.classList.remove('selected'));
        r.closest('.plan-radio').classList.add('selected');
        const p = plans[r.value];
        if (p && billPrice) billPrice.textContent = '$' + Number(p.price).toFixed(2);
        if (p && billPlan)  billPlan.textContent  = p.name;
      }));
    })();
  </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
