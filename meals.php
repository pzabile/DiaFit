<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$mealPhotos = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 120', [$me['id']]);

$pageTitle = 'Meal photos — DiaFitus';
$bodyClass = 'dashboard premium member-tab';
$activeTab = 'meals';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/member_sidebar.php';
?>
<main class="dash-main">
  <?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

  <header class="page-head">
    <div>
      <p class="kicker">Nutrition</p>
      <h1>Meal photos</h1>
    </div>
    <span class="chip"><?= count($mealPhotos) ?> shared</span>
  </header>

  <section class="card big">
    <p class="muted">Snap what you eat — breakfast, lunch, dinner, snacks, around training. Your coach uses these to calibrate your nutrition plan.</p>
    <form method="post" action="/save_meal" class="form inline-form" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div class="grid-3">
        <label>Photo<input type="file" name="photo" accept="image/*" required /></label>
        <label>Meal
          <select name="meal_type">
            <option>Breakfast</option><option>Lunch</option><option>Dinner</option>
            <option>Snack</option><option>Pre-workout</option><option>Post-workout</option>
          </select>
        </label>
        <label>What was it?<input type="text" name="caption" maxlength="500" placeholder="e.g. oats + berries" /></label>
      </div>
      <button type="submit" class="btn btn-primary">Upload meal</button>
    </form>
  </section>

  <section class="card big">
    <h2>Your gallery</h2>
    <?php if (!$mealPhotos): ?>
      <div class="empty-state">
        <div class="empty-icon">🍽️</div>
        <strong>No meals yet.</strong>
        <p>Upload your first meal and it'll appear here. Your coach can see them too.</p>
      </div>
    <?php else: ?>
      <div class="meal-grid">
        <?php foreach ($mealPhotos as $m): ?>
          <figure class="meal-tile">
            <img src="<?= e($m['file_path']) ?>" alt="" loading="lazy" />
            <figcaption>
              <strong><?= e($m['meal_type'] ?: 'Meal') ?></strong>
              <span><?= e($m['caption']) ?></span>
              <small><?= e(date('M j, g:ia', strtotime($m['created_at']))) ?></small>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
