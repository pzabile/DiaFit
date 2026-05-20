<?php
$pageTitle = 'DiaFitus — Personalized Diabetes Fitness Coaching';
$pageDescription = 'A fully personalized fitness and nutrition program designed around your diabetes type, goals and lifestyle.';
$bodyClass = 'landing-min';
require __DIR__ . '/includes/header.php';

// Collage photos: scan /assets/clients/ if present, else use placeholder tiles.
$clientDir = __DIR__ . '/assets/clients';
$clientPhotos = [];
if (is_dir($clientDir)) {
    foreach (glob($clientDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) as $p) {
        $clientPhotos[] = '/assets/clients/' . basename($p);
    }
}

$placeholders = [
    ['#d6f0e1', '💪'], ['#cde7ff', '🥗'], ['#fde6c1', '🏃'],
    ['#f3d9d9', '🩺'], ['#e6dffb', '📈'], ['#d4f4e1', '🍎'],
    ['#fff1c4', '🧘'], ['#dbecff', '🚴'], ['#fce0ec', '😊'],
    ['#d6f0e1', '🏆'], ['#cde7ff', '⚡'], ['#fde6c1', '🔥'],
];

$cols = [[], [], []];
if ($clientPhotos) {
    foreach ($clientPhotos as $i => $src) $cols[$i % 3][] = ['photo', $src];
} else {
    foreach ($placeholders as $i => $ph) $cols[$i % 3][] = ['ph', $ph];
}
foreach ($cols as &$col) { if (empty($col)) $col[] = ['ph', ['#d6f0e1', '✨']]; }
unset($col);
?>
  <header class="nav">
    <div class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </div>
  </header>

  <main class="landing-min-main">
    <section class="lm-copy">
      <h1>Personalized fitness coaching for people with <span class="accent">diabetes</span>.</h1>
      <p class="lede">A program built around your blood sugar, your goals and your life — designed with the people who live it every day.</p>

      <div class="gender-pick">
        <p class="pick-label">Start by selecting your gender:</p>
        <div class="pick-row">
          <a href="/questionnaire?gender=male" class="gender-btn male">Men</a>
          <a href="/questionnaire?gender=female" class="gender-btn female">Women</a>
        </div>
      </div>
    </section>

    <aside class="lm-collage" aria-hidden="true">
      <div class="collage-marquee">
        <?php foreach ($cols as $colIndex => $col):
          $direction = $colIndex % 2 === 0 ? 'm-up' : 'm-down';
          $speed     = ['28s', '34s', '30s'][$colIndex];
        ?>
          <div class="m-col <?= $direction ?>" style="animation-duration: <?= $speed ?>">
            <?php for ($d = 0; $d < 2; $d++): ?>
              <?php foreach ($col as $tile):
                [$kind, $data] = $tile;
                if ($kind === 'photo'): ?>
                  <div class="m-tile"><img src="<?= e($data) ?>" alt="" loading="lazy" /></div>
                <?php else: [$bg, $emoji] = $data; ?>
                  <div class="m-tile ph" style="background:<?= e($bg) ?>"><span><?= e($emoji) ?></span></div>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endfor; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </aside>
  </main>

  <footer class="lm-footer">
    <a href="/" class="brand"><span class="logo-dot"></span><span class="brand-name">DiaFitus</span></a>
    <div class="lm-foot-links">
      <a href="/terms">Terms &amp; Conditions</a>
      <a href="/privacy">Privacy Policy</a>
      <a href="mailto:<?= e(cfg('support_email')) ?>">Contact</a>
    </div>
    <p class="lm-foot-legal">© <?= date('Y') ?> <?= e(cfg('company_name')) ?>. DiaFitus provides general fitness and lifestyle suggestions and is not a substitute for medical advice. Always consult your physician.</p>
  </footer>

  <script src="/script.js"></script>
</body>
</html>
