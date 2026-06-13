<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$admins = db_all('SELECT id, username, created_at FROM admins ORDER BY id ASC');

$waitingCount = (int)db_get("
    SELECT COUNT(*) c
    FROM leads l
    JOIN coach_notes cn ON cn.id = (SELECT MAX(id) FROM coach_notes WHERE lead_id = l.id)
    WHERE l.paid = 1
      AND cn.from_member = 1
      AND NOT EXISTS (
          SELECT 1 FROM coach_notes cn2
          WHERE cn2.lead_id = l.id AND cn2.from_member = 0 AND cn2.created_at > cn.created_at
      )
")['c'];
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$leadsCount   = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=0')['c'];

$pageTitle = 'Settings — DiaFit Admin';
$bodyClass = 'admin-page';
$activeTab = 'settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
  <main class="main">
    <header class="topbar">
      <div class="crumb">
        <span class="dot"></span>
        <span>Admin</span>
        <span style="color:var(--line-2)">›</span>
        <b>Settings</b>
      </div>
      <div class="top-actions">
        <button class="icon-btn" title="Notifications">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16v-5a6 6 0 10-12 0v5l-2 3h16l-2-3z"/><path d="M10 21a2 2 0 004 0"/></svg>
        </button>
      </div>
    </header>

    <div class="view">
      <div class="eyebrow">Admin · settings</div>
      <h1 class="h1">Your <em>workspace</em>.</h1>
      <p class="muted" style="margin:0 0 18px;max-width:60ch">Team access, response targets, billing and integrations.</p>

      <div class="set-grid">

        <!-- Team & access -->
        <div class="set-card">
          <h4>Team &amp; access</h4>
          <p class="s">Who can log in to the admin console</p>
          <?php if ($admins): ?>
            <?php foreach ($admins as $i => $adm): ?>
              <div class="set-row">
                <span>
                  <b><?= e($adm['username']) ?></b>
                  <?php if ($i === 0): ?>
                    &nbsp;<span class="chip">Owner</span>
                  <?php else: ?>
                    &nbsp;<span class="chip">Admin</span>
                  <?php endif; ?>
                </span>
                <?php if ($i > 0): ?>
                  <button class="btn sm">Edit</button>
                <?php else: ?>
                  <span class="v">·</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="set-row"><span class="muted">No admin accounts found.</span></div>
          <?php endif; ?>
          <button class="btn sm" style="margin-top:10px">+ Invite teammate</button>
        </div>

        <!-- Response targets -->
        <div class="set-card">
          <h4>Response targets</h4>
          <p class="s">SLA you commit to in member messaging</p>
          <div class="set-row"><span>Standard reply</span><span class="v">≤ 4 hours</span></div>
          <div class="set-row"><span>Marked URGENT</span><span class="v">≤ 30 minutes</span></div>
          <div class="set-row"><span>Weekly review digest</span><span class="v">Sundays by 11 PM</span></div>
          <div class="set-row">
            <span>After-hours auto-reply</span>
            <span><div class="toggle-sw on" onclick="this.classList.toggle('on')"></div></span>
          </div>
        </div>

        <!-- Notifications -->
        <div class="set-card">
          <h4>Notifications</h4>
          <p class="s">When the console pings you</p>
          <div class="set-row">
            <span>New message · push</span>
            <span><div class="toggle-sw on" onclick="this.classList.toggle('on')"></div></span>
          </div>
          <div class="set-row">
            <span>URGENT tag · SMS</span>
            <span><div class="toggle-sw on" onclick="this.classList.toggle('on')"></div></span>
          </div>
          <div class="set-row">
            <span>New lead</span>
            <span><div class="toggle-sw on" onclick="this.classList.toggle('on')"></div></span>
          </div>
          <div class="set-row">
            <span>Daily digest · 9 AM</span>
            <span><div class="toggle-sw on" onclick="this.classList.toggle('on')"></div></span>
          </div>
        </div>

        <!-- Integrations -->
        <div class="set-card">
          <h4>Integrations</h4>
          <p class="s">Where data lives</p>
          <div class="set-row">
            <span>Hostinger · directory password</span>
            <span class="chip sage">connected</span>
          </div>
          <div class="set-row">
            <span>Stripe · billing</span>
            <span class="chip sage">connected</span>
          </div>
          <div class="set-row">
            <span>Postmark · transactional mail</span>
            <span class="chip sage">connected</span>
          </div>
          <div class="set-row">
            <span>Telegram bot · support</span>
            <span class="chip">not connected</span>
          </div>
        </div>

      </div><!-- /set-grid -->
    </div><!-- /view -->
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
