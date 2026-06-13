<?php
// New admin sidebar v2. Requires $activeTab, $waitingCount, $membersCount, $leadsCount to be set.
$waitingCount = $waitingCount ?? 0;
$membersCount = $membersCount ?? 0;
$leadsCount   = $leadsCount   ?? 0;
?>
<aside class="sidebar">
  <div class="brand">
    <div class="brand-mark">d</div>
    <div>
      <div class="brand-name">Dia<em>fitus</em></div>
      <div class="role-pill">Admin</div>
    </div>
  </div>

  <nav class="nav">
    <div class="section-label">Operations</div>
    <a href="/admin/" <?= ($activeTab ?? '') === 'home' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/></svg></span>
      Overview
    </a>
    <a href="/admin/inbox" <?= ($activeTab ?? '') === 'inbox' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg></span>
      Inbox
      <?php if ($waitingCount > 0): ?>
        <span class="badge <?= ($activeTab ?? '') === 'inbox' ? '' : '' ?>"><?= $waitingCount ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/members" <?= ($activeTab ?? '') === 'members' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0114 0M17 11l2 2 4-4"/></svg></span>
      Paid members
      <?php if ($membersCount > 0): ?>
        <span class="badge sage"><?= $membersCount ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/leads" <?= ($activeTab ?? '') === 'leads' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h13l3 3v13H4z"/><path d="M4 9h16"/></svg></span>
      Leads (unpaid)
      <?php if ($leadsCount > 0): ?>
        <span class="badge sage"><?= $leadsCount ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/new_member" <?= ($activeTab ?? '') === 'new_member' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
      Add member
    </a>

    <div class="section-label">Content</div>
    <a href="/admin/programs" <?= ($activeTab ?? '') === 'programs' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
      Programs
    </a>

    <div class="section-label">Insights</div>
    <a href="/admin/stats" <?= ($activeTab ?? '') === 'stats' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-5 4 4 8-9"/></svg></span>
      Stats &amp; cohorts
    </a>
    <a href="/admin/settings" <?= ($activeTab ?? '') === 'settings' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1.65 1.5v.09a2 2 0 11-4 0v-.09A1.65 1.65 0 008 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.5-1.65H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001.65-1.5V3a2 2 0 114 0v.09A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.5 1.65H21a2 2 0 110 4h-.09A1.65 1.65 0 0019.4 15z"/></svg></span>
      Settings
    </a>
  </nav>

  <div class="side-foot">
    <div class="user-card">
      <div class="av"><?= strtoupper(substr(cfg('company_email') ?: 'A', 0, 1)) ?></div>
      <div style="flex:1;min-width:0">
        <div class="who" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(cfg('brand_name') ?: 'Admin') ?></div>
        <div class="sub">Coach · Admin</div>
      </div>
    </div>
    <a href="/admin/logout" class="logout">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Log out
    </a>
  </div>
</aside>
