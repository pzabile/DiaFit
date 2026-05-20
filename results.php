<?php
require __DIR__ . '/includes/avatar.php';

$step = (int) ($_GET['step'] ?? 1);
if ($step < 1 || $step > 4) $step = 1;
$totalSteps = 4;

$pageTitle = 'Your DiaFitus Results';
$bodyClass = 'results-page';
require __DIR__ . '/includes/header.php';

$answers = answers();
$days = (int) ($answers['days_per_week'] ?? 3);
$mins = (int) ($answers['minutes_per_day'] ?? 30);

function reviewer_image($name) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = __DIR__ . '/assets/reviews/' . $slug . '.' . $ext;
        if (file_exists($path)) return '/assets/reviews/' . $slug . '.' . $ext;
    }
    return null;
}

$nextUrl = $step < $totalSteps ? '/results?step=' . ($step + 1) : '/offer';
$btnText = $step < $totalSteps ? 'Continue →' : 'See my plan →';
?>
  <header class="nav slim results-top">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <div class="step-dots" aria-label="Step <?= $step ?> of <?= $totalSteps ?>">
      <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
        <span class="dot <?= $i <= $step ? 'on' : '' ?>"></span>
      <?php endfor; ?>
    </div>
  </header>

  <main class="results-main">

  <?php if ($step === 1): ?>
    <section class="r-card">
      <span class="pill">Step 1 of <?= $totalSteps ?> · Your projection</span>
      <h1>This is where DiaFitus can take you.</h1>
      <p class="lede">Most members feel a clear difference by the second week — fewer blood-sugar swings, more energy, and the first real wins. By week 12 you're a different person.</p>
      <div class="curve-wrap">
        <svg viewBox="0 0 600 280" xmlns="http://www.w3.org/2000/svg" class="curve-svg" preserveAspectRatio="none">
          <defs>
            <linearGradient id="curveLine" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%"   stop-color="#d8493c"/>
              <stop offset="50%"  stop-color="#f0a830"/>
              <stop offset="100%" stop-color="#16a36a"/>
            </linearGradient>
            <linearGradient id="curveFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%"   stop-color="#16a36a" stop-opacity=".35"/>
              <stop offset="100%" stop-color="#16a36a" stop-opacity="0"/>
            </linearGradient>
          </defs>
          <path d="M 40 230 L 560 230" stroke="#e3e0d6" stroke-width="1.5" stroke-dasharray="4 4"/>
          <path d="M 40 230 Q 180 230 240 180 T 420 90 T 560 50 L 560 230 Z" fill="url(#curveFill)"/>
          <path d="M 40 230 Q 180 230 240 180 T 420 90 T 560 50" fill="none" stroke="url(#curveLine)" stroke-width="5" stroke-linecap="round"/>
          <circle cx="40"  cy="230" r="7" fill="#d8493c"/>
          <circle cx="240" cy="180" r="7" fill="#fff" stroke="#f0a830" stroke-width="4"/>
          <circle cx="560" cy="50"  r="9" fill="#16a36a"/>
          <g font-family="Inter, Arial" font-size="13" fill="#4a5651">
            <text x="40"  y="260">TODAY</text>
            <text x="200" y="260">WEEK 4</text>
            <text x="520" y="260">WEEK 12</text>
          </g>
          <g font-family="Inter, Arial" font-size="12" font-weight="700">
            <rect x="10" y="195" width="80" height="22" rx="11" fill="#fdecea"/>
            <text x="50" y="210" fill="#a3261c" text-anchor="middle">Stuck</text>
            <rect x="190" y="140" width="120" height="22" rx="11" fill="#fff5e5"/>
            <text x="250" y="155" fill="#a3530a" text-anchor="middle">First wins</text>
            <rect x="480" y="14" width="120" height="22" rx="11" fill="#d6f0e1"/>
            <text x="540" y="29" fill="#0d7d4f" text-anchor="middle">Thriving</text>
          </g>
        </svg>
      </div>
      <p class="motivation"><strong>You can do this.</strong> Thousands of people with diabetes have walked this exact path — and the only difference between week 1 and week 12 is showing up consistently. We'll help you do that.</p>
    </section>
  <?php endif; ?>

  <?php if ($step === 2): ?>
    <section class="r-card">
      <span class="pill">Step 2 of <?= $totalSteps ?> · Where you are now</span>
      <h1>What's holding you back right now.</h1>
      <p class="lede">Your answers point to a few patterns most people with diabetes feel — and that we can fix together.</p>
      <div class="zone-grid">
        <div class="zone stuck">
          <div class="zone-head">
            <div class="gauge-wrap">
              <svg viewBox="0 0 200 110" xmlns="http://www.w3.org/2000/svg">
                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#f3b6ad" stroke-width="14" stroke-linecap="round"/>
                <g transform="translate(100 100) rotate(-50)">
                  <line x1="0" y1="0" x2="0" y2="-72" stroke="#1f1b16" stroke-width="4" stroke-linecap="round"/>
                  <circle r="6" fill="#1f1b16"/>
                </g>
              </svg>
            </div>
            <h3>Stuck with diabetes</h3>
          </div>
          <ul>
            <li>Unpredictable blood sugar swings</li>
            <li>Low energy, brain fog</li>
            <li>Workouts that crash you or cause hypos</li>
            <li>Confusing, contradicting nutrition advice</li>
            <li>Poor sleep &amp; recovery</li>
            <li>No clear progress, low confidence</li>
          </ul>
        </div>
        <div class="zone potential">
          <div class="zone-head">
            <div class="gauge-wrap">
              <svg viewBox="0 0 200 110" xmlns="http://www.w3.org/2000/svg">
                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#b6e3c8" stroke-width="14" stroke-linecap="round"/>
                <g transform="translate(100 100) rotate(50)">
                  <line x1="0" y1="0" x2="0" y2="-72" stroke="#1f1b16" stroke-width="4" stroke-linecap="round"/>
                  <circle r="6" fill="#1f1b16"/>
                </g>
              </svg>
            </div>
            <h3>With DiaFitus</h3>
          </div>
          <ul>
            <li>Steady, predictable blood sugar</li>
            <li>Energy that lasts the whole afternoon</li>
            <li>Workouts that work <em>with</em> your insulin</li>
            <li>A simple, doctor-reviewed nutrition plan</li>
            <li>Deeper sleep, faster recovery</li>
            <li>Weekly visible wins, real confidence</li>
          </ul>
        </div>
      </div>
      <p class="motivation"><strong>This is fixable.</strong> Diabetes doesn't get the last word on your energy, your strength or your life.</p>
    </section>
  <?php endif; ?>

  <?php if ($step === 3): ?>
    <section class="r-card">
      <span class="pill">Step 3 of <?= $totalSteps ?> · Personal summary</span>
      <h1>Now vs. where DiaFitus takes you.</h1>
      <p class="lede">Based on your assessment, we mapped your starting point against where a consistent <?= (int) $days ?>-day, <?= (int) $mins ?>-minute weekly program can get you.</p>
      <div class="now-goal">
        <div class="ng now">
          <div class="ng-head">
            <span class="badge red">Now</span>
            <strong>Where you are today</strong>
          </div>
          <?php
          $rows = [
            ['Blood sugar control', 1],
            ['Energy levels',       1],
            ['Strength',            1],
            ['Cardio fitness',      1],
            ['Confidence',          2],
          ];
          foreach ($rows as [$label, $val]): ?>
            <div class="ng-row">
              <div class="ng-label"><strong><?= e($label) ?></strong><span><?= ['Very low','Low','Medium'][$val-1] ?? 'Low' ?></span></div>
              <div class="bars red">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="bar <?= $i <= $val ? 'on' : '' ?>"></span>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="ng-arrow">»</div>

        <div class="ng goal">
          <div class="ng-head">
            <span class="badge green">Your goal</span>
            <strong>Where DiaFitus takes you</strong>
          </div>
          <?php
          $rows = [
            ['Blood sugar control', 5],
            ['Energy levels',       5],
            ['Strength',            4],
            ['Cardio fitness',      4],
            ['Confidence',          5],
          ];
          foreach ($rows as [$label, $val]): ?>
            <div class="ng-row">
              <div class="ng-label"><strong><?= e($label) ?></strong><span><?= ['Low','Medium','Good','High','Excellent'][$val-1] ?></span></div>
              <div class="bars green">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="bar <?= $i <= $val ? 'on' : '' ?>"></span>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="potential-note">
        <span class="icon">📈</span>
        <p>Your answers show <strong>strong potential</strong> to hit every one of these goals. Most people see the first jump in just two weeks.</p>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($step === 4): ?>
    <section class="r-card reviews-block">
      <span class="pill">Step 4 of <?= $totalSteps ?> · Real members</span>
      <h1>People just like you, already living it.</h1>
      <p class="lede">4.9 / 5 average from 3,400+ verified members. Hover the strip to pause.</p>

      <?php
      $reviews = [
        ['Marcus T.',  'Type 2',        'My A1C dropped from 8.1 to 6.4 in four months. The coaches actually understand diabetes.'],
        ['Lena R.',    'Type 1',        'Finally a program that does not crash my blood sugar. Having 24/7 support is gold.'],
        ['David P.',   'Pre-diabetes',  'Lost 12 kg, off two medications. I would pay much more for what I have gotten.'],
        ['Aisha K.',   'Type 2',        'Stronger at 49 than I was at 35. The plan respected my limits and grew with me.'],
        ['Tom B.',     'Type 1',        'The nutrition PDF alone is worth the subscription. Clear, no fluff.'],
        ['Priya N.',   'Gestational',   'They built me a safe routine during pregnancy. Doctor-approved within a day.'],
        ['Carlos M.',  'Type 2',        '58 and never more active. Started with 15-min walks, now 4x a week at the gym.'],
        ['Hannah G.',  'Type 1',        'Hypos used to scare me away from cardio. Months without one now.'],
        ['Yusuf A.',   'Pre-diabetes',  'Fasting glucose 118 → 92. Weekly check-ins keep me on track.'],
        ['Megan F.',   'Type 2',        'Energy in the afternoons came back within three weeks.'],
        ['Rajiv S.',   'Type 2',        'Home program needs zero equipment. Game-changer.'],
        ['Olivia W.',  'Type 1',        '24/7 messaging replies faster than my own clinic.'],
      ];
      ?>
      <div class="marquee-wrap">
        <div class="reviews-marquee">
          <?php for ($d = 0; $d < 2; $d++): ?>
            <?php foreach ($reviews as $r):
              [$name, $tag, $text] = $r;
              $img = reviewer_image($name);
            ?>
              <article class="rev-card">
                <header>
                  <div class="rev-avatar">
                    <?php if ($img): ?>
                      <img src="<?= e($img) ?>" alt="" loading="lazy" />
                    <?php else: ?>
                      <?= avatar_svg($name, 56) ?>
                    <?php endif; ?>
                  </div>
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
      <p class="motivation" style="margin-top:1.5rem"><strong>Your turn.</strong> One decision today is what separates you from these stories.</p>
    </section>
  <?php endif; ?>

    <section class="step-actions">
      <a href="<?= e($nextUrl) ?>" class="btn btn-primary btn-xl"><?= e($btnText) ?></a>
      <?php if ($step > 1): ?>
        <a href="/results?step=<?= $step - 1 ?>" class="back-link">← Back</a>
      <?php endif; ?>
    </section>

    <p class="muted disclaimer-small">
      DiaFitus is general fitness and lifestyle coaching. It is not medical advice and is not a substitute for consultation with your doctor. Always talk to your physician before starting any new exercise or nutrition program.
    </p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
