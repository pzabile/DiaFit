<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$me = require_member();
$leadId = (int)$me['id'];

$meals = db_all(
    'SELECT * FROM meal_photos WHERE lead_id = ? ORDER BY eaten_at DESC, created_at DESC LIMIT 50',
    [$leadId]
);

$pageTitle  = 'Meals — DiaFit';
$bodyClass  = 'portal-page';
$activeView = 'meals';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/../includes/portal_layout.php'; ?>
<main class="main">
  <header class="topbar">
    <div class="crumb">
      <span class="dot"></span>
      <span id="crumbText">Meals</span>
      <span style="color:var(--line-2)">·</span>
      <span><?= date('l, M j · g:i A') ?></span>
    </div>
    <div class="top-actions">
      <button type="button" class="quick-log" id="openUpload">
        <span class="plus"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
        Add meal
      </button>
    </div>
  </header>

  <section class="view">
    <div class="row" style="align-items:flex-end;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:14px">
      <div>
        <div class="eyebrow">Nutrition &middot; meals</div>
        <h1 class="h1">Your <em>fueling</em> diary.</h1>
        <p class="muted" style="margin:0;max-width:60ch">Snap a quick photo or just describe what you ate. Your coach browses these weekly to spot patterns.</p>
      </div>
      <div class="chips" id="filterChips">
        <button class="chip" aria-pressed="true" data-filter="all">All</button>
        <button class="chip" data-filter="breakfast">Breakfast</button>
        <button class="chip" data-filter="lunch">Lunch</button>
        <button class="chip" data-filter="dinner">Dinner</button>
        <button class="chip" data-filter="snack">Snack</button>
        <button class="chip" data-filter="pre-post wo">Pre / post WO</button>
      </div>
    </div>

    <div class="meal-grid" id="mealGrid">
      <!-- Upload card -->
      <div class="upload-card" id="uploadCard">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="14" rx="2"/>
          <circle cx="9" cy="11" r="2"/>
          <path d="M21 17l-5-5-10 9"/>
        </svg>
        <div class="ttl">Add a meal</div>
        <div class="sub">Drop a photo or just write a quick note</div>
        <button type="button" class="btn pri sm" style="margin-top:6px" id="openUpload2">+ New entry</button>
      </div>

      <?php foreach ($meals as $m): ?>
        <div class="meal-card" data-meal-type="<?= strtolower(e($m['meal_type'] ?? '')) ?>">
          <div class="ph">
            <span class="tag"><?= e($m['meal_type'] ?? 'Meal') ?></span>
            <?php if ($m['file_path']): ?>
              <img src="/<?= e($m['file_path']) ?>" alt="<?= e($m['caption'] ?? 'Meal photo') ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit">
            <?php else: ?>
              <span style="padding:10px;text-align:center"><?= e(mb_substr($m['caption'] ?? 'No photo', 0, 60)) ?></span>
            <?php endif; ?>
          </div>
          <div class="info">
            <div class="what"><?= e(mb_substr($m['caption'] ?? 'Meal', 0, 60)) ?><?= mb_strlen($m['caption'] ?? '') > 60 ? '…' : '' ?></div>
            <div class="when"><?= $m['eaten_at'] ? e(date('D · g:i A', strtotime($m['eaten_at']))) : e(date('D · g:i A', strtotime($m['created_at']))) ?></div>
            <?php if (!empty($m['admin_comment'])): ?>
              <div style="margin-top:6px;padding:6px 8px;background:var(--sage-tint);border-radius:8px;font-size:11.5px;color:var(--sage-2);line-height:1.4">
                <strong>Coach:</strong> <?= e(mb_substr($m['admin_comment'], 0, 120)) ?><?= mb_strlen($m['admin_comment']) > 120 ? '…' : '' ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(27,32,28,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center">
  <div style="background:var(--card);border-radius:var(--r-xl);padding:32px;width:100%;max-width:520px;margin:20px;box-shadow:var(--shadow-lift)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h2 class="h3">Add a meal</h2>
      <button type="button" id="closeModal" style="color:var(--muted);font-size:20px">&times;</button>
    </div>
    <form id="uploadForm" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div style="margin-bottom:14px">
        <label class="field-lbl">Photo (optional)</label>
        <input type="file" name="file" accept="image/*" class="input" style="padding:8px">
      </div>
      <div style="margin-bottom:14px">
        <label class="field-lbl">Description</label>
        <textarea name="caption" class="ta" placeholder="e.g. Greek yogurt + granola + blueberries"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="field-lbl">Meal type</label>
          <select name="meal_type" class="input">
            <option value="Breakfast">Breakfast</option>
            <option value="Lunch">Lunch</option>
            <option value="Dinner">Dinner</option>
            <option value="Snack">Snack</option>
            <option value="Pre-post WO">Pre / post WO</option>
          </select>
        </div>
        <div>
          <label class="field-lbl">Time eaten</label>
          <input type="datetime-local" name="eaten_at" class="input" value="<?= date('Y-m-d\TH:i') ?>">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" id="cancelModal" class="btn">Cancel</button>
        <button type="submit" class="btn pri">Save meal</button>
      </div>
      <div id="uploadStatus" style="margin-top:10px;font-size:13px;color:var(--muted)"></div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('uploadModal');
function openModal() { modal.style.display = 'flex'; modal.style.alignItems = 'center'; modal.style.justifyContent = 'center'; }
function closeModal() { modal.style.display = 'none'; }
document.getElementById('openUpload').addEventListener('click', openModal);
document.getElementById('openUpload2').addEventListener('click', openModal);
document.getElementById('closeModal').addEventListener('click', closeModal);
document.getElementById('cancelModal').addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

// Filter chips
const filterChips = document.getElementById('filterChips');
filterChips.addEventListener('click', e => {
  const c = e.target.closest('.chip'); if (!c) return;
  filterChips.querySelectorAll('.chip').forEach(x => x.setAttribute('aria-pressed','false'));
  c.setAttribute('aria-pressed','true');
  const filter = c.dataset.filter;
  document.querySelectorAll('.meal-card').forEach(card => {
    const mt = card.dataset.mealType || '';
    card.style.display = (filter === 'all' || mt.includes(filter) || filter.includes(mt)) ? '' : 'none';
  });
});

// Upload form
document.getElementById('uploadForm').addEventListener('submit', async e => {
  e.preventDefault();
  const status = document.getElementById('uploadStatus');
  status.textContent = 'Uploading…';
  const fd = new FormData(e.target);
  try {
    const res = await fetch('/api/meal_upload', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.ok) {
      status.textContent = 'Saved!';
      setTimeout(() => { closeModal(); location.reload(); }, 800);
    } else {
      status.textContent = 'Error: ' + (json.error || 'Upload failed');
    }
  } catch(err) {
    status.textContent = 'Network error — try again';
  }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
