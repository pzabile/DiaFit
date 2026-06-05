<?php
/**
 * Portal sidebar partial.
 * Requires $me (lead row) and $activeView from the calling page.
 */

// Count today's log (badge for "Daily log")
$todayLogCount = 0;
$todayLog = db_get(
    'SELECT id FROM daily_logs WHERE lead_id = ? AND log_date = CURDATE() LIMIT 1',
    [(int)$me['id']]
);
if ($todayLog) $todayLogCount = 1;

// Count unread coach messages (from_member=0 with no member reply after them)
$lastMemberMsg = db_get(
    "SELECT MAX(id) as lid FROM coach_notes WHERE lead_id = ? AND from_member = 1",
    [(int)$me['id']]
);
$lastMemberId = (int)($lastMemberMsg['lid'] ?? 0);
$unreadCoach = (int)(db_get(
    "SELECT COUNT(*) as cnt FROM coach_notes WHERE lead_id = ? AND from_member = 0 AND id > ?",
    [(int)$me['id'], $lastMemberId]
)['cnt'] ?? 0);

// Program info
$prog = member_program_info($me);

// User initials (first 2 chars of first_name)
$initials = strtoupper(mb_substr($me['first_name'] ?? 'M', 0, 2));

$navItems = [
    ['route'=>'today',   'label'=>'Today',         'section'=>'Daily',
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/></svg>',
     'href'=>'/portal/today'],
    ['route'=>'log',     'label'=>'Daily log',      'section'=>null,
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>',
     'href'=>'/portal/log',
     'badge'=> $todayLogCount ? date('N') . '/7' : null],
    ['route'=>'weekly',  'label'=>'Weekly review',  'section'=>null,
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/></svg>',
     'href'=>'/portal/weekly'],
    ['route'=>'program', 'label'=>'My program',     'section'=>'Plan',
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>',
     'href'=>'/portal/program'],
    ['route'=>'trends',  'label'=>'Trends',         'section'=>null,
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-5 4 4 8-9"/><path d="M14 7h7v7"/></svg>',
     'href'=>'/portal/trends'],
    ['route'=>'meals',   'label'=>'Meals',          'section'=>null,
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v7a4 4 0 008 0V4M8 4v17M16 4c-1.5 0-3 1-3 4v4h3v9"/></svg>',
     'href'=>'/portal/meals'],
    ['route'=>'coach',   'label'=>'Coach',          'section'=>'Support',
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>',
     'href'=>'/portal/coach',
     'badge'=> $unreadCoach > 0 ? (string)$unreadCoach : null],
    ['route'=>'account', 'label'=>'Account',        'section'=>null,
     'icon'=>'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>',
     'href'=>'/portal/account'],
];
?>
<aside class="sidebar">
  <div class="brand">
    <div class="brand-mark">d</div>
    <div>
      <div class="brand-name">diafitus<em>.</em></div>
      <div class="brand-tag">member portal</div>
    </div>
  </div>

  <nav class="nav">
    <?php
    $lastSection = null;
    foreach ($navItems as $item):
        if ($item['section'] !== null && $item['section'] !== $lastSection):
            $lastSection = $item['section'];
    ?>
      <div class="section-label"><?= e($item['section']) ?></div>
    <?php endif; ?>
      <a href="<?= e($item['href']) ?>" data-route="<?= e($item['route']) ?>"<?= ($activeView === $item['route']) ? ' class="active"' : '' ?>>
        <span class="ic"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
        <?php if (!empty($item['badge'])): ?>
          <span class="badge"><?= e($item['badge']) ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="side-foot">
    <div class="user-card">
      <div class="av"><?= e($initials) ?></div>
      <div class="grow">
        <div class="who"><?= e($me['first_name'] ?? 'Member') ?></div>
        <div class="sub">Week <?= $prog['current'] ?> &middot; <?= $prog['total'] ?>-week plan</div>
      </div>
    </div>
    <a href="/logout" class="logout">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Log out
    </a>
  </div>
</aside>

<!-- Mobile overlay backdrop -->
<div class="mob-overlay" id="mobOverlay" onclick="closeMobNav()"></div>

<nav class="mob-nav" role="navigation" aria-label="Main navigation">
  <?php foreach ([
    ['route'=>'today',   'label'=>'Today',   'href'=>'/portal/today',
     'icon'=>'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/></svg>'],
    ['route'=>'log',     'label'=>'Log',     'href'=>'/portal/log',
     'icon'=>'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>'],
    ['route'=>'program', 'label'=>'Program', 'href'=>'/portal/program',
     'icon'=>'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>'],
    ['route'=>'coach',   'label'=>'Coach',   'href'=>'/portal/coach',
     'icon'=>'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg>',
     'badge'=>$unreadCoach > 0 ? $unreadCoach : null],
  ] as $mn): ?>
    <a href="<?= e($mn['href']) ?>" class="mob-nav-item<?= $activeView === $mn['route'] ? ' active' : '' ?>">
      <span class="mob-nav-ic">
        <?= $mn['icon'] ?>
        <?php if (!empty($mn['badge'])): ?><span class="mob-badge"><?= (int)$mn['badge'] ?></span><?php endif; ?>
      </span>
      <?= e($mn['label']) ?>
    </a>
  <?php endforeach; ?>
  <!-- More → opens full sidebar drawer -->
  <button class="mob-nav-item" onclick="openMobNav()" aria-label="All navigation">
    <span class="mob-nav-ic">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </span>
    Menu
  </button>
</nav>

<script>
function openMobNav() {
  var s = document.querySelector('.sidebar');
  if (!s) return;
  var ov = document.getElementById('mobOverlay');
  // Force sidebar visible + off-screen, then animate in (bypasses any cached display:none)
  s.style.cssText = [
    'display:flex','flex-direction:column','position:fixed',
    'top:0','left:0','bottom:0','width:268px','z-index:198',
    'overflow-y:auto','padding:20px 16px','gap:18px',
    'background:var(--bg,#f7f5f0)','border-right:1px solid var(--line,#e3e0d6)',
    'box-shadow:4px 0 28px rgba(0,0,0,.15)',
    'transform:translateX(-100%)','transition:transform .22s ease'
  ].join(';');
  ov.style.display = 'block';
  document.body.style.overflow = 'hidden';
  // Double rAF so the off-screen position paints before we slide in
  requestAnimationFrame(function(){ requestAnimationFrame(function(){
    s.style.transform = 'translateX(0)';
  }); });
}
function closeMobNav() {
  var s = document.querySelector('.sidebar');
  if (!s) return;
  s.style.transform = 'translateX(-100%)';
  document.getElementById('mobOverlay').style.display = 'none';
  document.body.style.overflow = '';
  setTimeout(function(){ s.style.cssText = ''; }, 230);
}
</script>
