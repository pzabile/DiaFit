<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$mealPhotos = db_all('SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY created_at DESC LIMIT 120', [$me['id']]);

$pageTitle = 'Meal photos — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'meals';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px">
      <div>
        <p class="eyebrow sage">Nutrition</p>
        <h1 class="h2 serif" style="margin-top:4px">Meal photos</h1>
        <p style="color:var(--muted);font-size:13.5px;margin-top:6px">Snap what you eat — your coach uses these to calibrate your nutrition plan.</p>
      </div>
      <span style="background:var(--sage-tint);color:var(--sage-2);font-size:12px;font-weight:600;padding:5px 12px;border-radius:99px;white-space:nowrap;align-self:flex-start"><?= count($mealPhotos) ?> shared</span>
    </div>

    <!-- Upload form -->
    <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px;margin-bottom:28px">
      <div style="font-weight:700;font-size:15px;margin-bottom:16px">Upload a meal photo</div>
      <form method="post" action="/save_meal" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px">
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Photo
            <input type="file" name="photo" accept="image/*" required style="padding:8px 10px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13px" />
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Meal
            <select name="meal_type" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px">
              <option>Breakfast</option><option>Lunch</option><option>Dinner</option>
              <option>Snack</option><option>Pre-workout</option><option>Post-workout</option>
            </select>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            What was it?
            <input type="text" name="caption" maxlength="500" placeholder="e.g. oats + berries" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
          </label>
        </div>
        <button type="submit" style="background:var(--ink);color:#F4F1E9;padding:10px 22px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer">Upload meal</button>
      </form>
    </div>

    <!-- Gallery -->
    <div style="font-weight:700;font-size:15px;margin-bottom:16px">Your gallery</div>
    <?php if (!$mealPhotos): ?>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:48px 24px;text-align:center">
        <div style="font-size:32px;margin-bottom:12px">🍽️</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">No meals yet</div>
        <p style="color:var(--muted);font-size:13.5px;margin:0">Upload your first meal above and it'll appear here. Your coach can see them too.</p>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
        <?php foreach ($mealPhotos as $m): ?>
          <figure style="margin:0;background:var(--card);border-radius:var(--r);border:1px solid var(--line);overflow:hidden">
            <img src="<?= e($m['file_path']) ?>" alt="" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" />
            <figcaption style="padding:10px 12px">
              <div style="font-weight:600;font-size:13px"><?= e($m['meal_type'] ?: 'Meal') ?></div>
              <?php if ($m['caption']): ?><div style="font-size:12.5px;color:var(--muted);margin-top:2px"><?= e($m['caption']) ?></div><?php endif; ?>
              <div style="font-size:11.5px;color:var(--muted-2);margin-top:4px"><?= e(date('M j, g:ia', strtotime($m['created_at']))) ?></div>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
