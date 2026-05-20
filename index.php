<?php
$pageTitle = 'DiaFitus — Personalized Diabetes Fitness Coaching';
$pageDescription = 'A fully personalized fitness and nutrition program designed around your diabetes type, goals and lifestyle.';
$bodyClass = 'landing-min';
require __DIR__ . '/includes/header.php';

// Look for client collage photos uploaded into /assets/clients/
$clientDir = __DIR__ . '/assets/clients';
$clientPhotos = [];
if (is_dir($clientDir)) {
    foreach (glob($clientDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) as $p) {
        $clientPhotos[] = '/assets/clients/' . basename($p);
    }
}
// Limit to 9 to keep the layout balanced.
$clientPhotos = array_slice($clientPhotos, 0, 9);
?>
  <header class="nav">
    <div class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </div>
    <div class="nav-actions">
      <a href="/login" class="btn btn-ghost">Sign in</a>
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
      <?php if ($clientPhotos): ?>
        <div class="collage-grid">
          <?php foreach ($clientPhotos as $i => $src): ?>
            <div class="collage-tile t<?= $i % 9 ?>"><img src="<?= e($src) ?>" alt="" loading="lazy" /></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="collage-grid">
          <?php
          $stock = [
            ['#d6f0e1', '💪'], ['#cde7ff', '🥗'], ['#fde6c1', '🏃'],
            ['#f3d9d9', '🩺'], ['#e6dffb', '📈'], ['#d4f4e1', '🍎'],
            ['#fff1c4', '🧘'], ['#dbecff', '🚴'], ['#fce0ec', '😊'],
          ];
          foreach ($stock as $i => $s): [$bg, $emoji] = $s; ?>
            <div class="collage-tile placeholder t<?= $i ?>" style="background:<?= $bg ?>">
              <span><?= $emoji ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="collage-hint">Add client photos to <code>/assets/clients/</code> to fill this collage.</p>
      <?php endif; ?>
    </aside>
  </main>

  <footer class="lm-footer">
    <a href="/" class="brand"><span class="logo-dot"></span><span class="brand-name">DiaFitus</span></a>
    <div class="lm-foot-links">
      <a href="/login">Member sign in</a>
      <a href="/terms">Terms &amp; Conditions</a>
      <a href="/privacy">Privacy Policy</a>
      <a href="mailto:<?= e(cfg('support_email')) ?>">Contact</a>
    </div>
    <p class="lm-foot-legal">© <?= date('Y') ?> <?= e(cfg('company_name')) ?>. DiaFitus provides general fitness and lifestyle suggestions and is not a substitute for medical advice. Always consult your physician.</p>
  </footer>

  <script src="/script.js"></script>
</body>
</html>
