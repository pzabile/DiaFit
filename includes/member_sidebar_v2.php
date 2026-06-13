<?php
// Member portal sidebar v2. Requires $activeTab and $me to be set.
$tab = $activeTab ?? '';
$me  = $me ?? (function_exists('current_member') ? current_member() : null);
$initial = strtoupper(substr($me['first_name'] ?? 'M', 0, 1));
?>
<!-- Mobile top bar -->
<header class="mobile-top">
  <a href="/dashboard" class="brand">
    <div class="brand-mark">d</div>
    <div class="brand-name">Dia<em>fitus</em></div>
  </a>
  <a href="/logout" class="logout" style="font-size:12.5px;color:var(--muted);padding:6px 12px;border-radius:8px;border:1px solid var(--line);">Log out</a>
</header>

<aside class="sidebar">
  <a href="/dashboard" class="brand">
    <div class="brand-mark">d</div>
    <div>
      <div class="brand-name">Dia<em>fitus</em></div>
      <div class="brand-tag">member portal</div>
    </div>
  </a>

  <nav class="nav">
    <a href="/dashboard" <?= $tab === 'overview' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/></svg></span>
      Dashboard
    </a>
    <a href="/program" <?= $tab === 'program' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
      My program
    </a>
    <a href="/checkin" <?= $tab === 'checkin' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 16l2 2 4-4"/></svg></span>
      Weekly check-in
    </a>
    <a href="/logs" <?= $tab === 'logs' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></span>
      Daily check-ins
    </a>
    <a href="/meals" <?= $tab === 'meals' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></span>
      Meal photos
    </a>
    <a href="/progress" <?= $tab === 'progress' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
      Progress
    </a>
    <a href="/chat" <?= $tab === 'chat' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg></span>
      Chat with coach
    </a>
    <a href="/account" <?= $tab === 'account' ? 'class="active"' : '' ?>>
      <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0116 0"/></svg></span>
      Account
    </a>
  </nav>

  <div class="side-foot">
    <div class="user-card">
      <div class="av"><?= e($initial) ?></div>
      <div style="flex:1;min-width:0">
        <div class="who" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($me['first_name'] ?? 'Member') ?></div>
        <div class="sub" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($me['email'] ?? '') ?></div>
      </div>
    </div>
    <a href="/logout" class="logout">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Log out
    </a>
  </div>
</aside>

<!-- Mobile bottom navigation -->
<nav class="mobile-nav" aria-label="Member sections">
  <a href="/dashboard" <?= $tab === 'overview' ? 'class="active"' : '' ?>>
    <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10a1 1 0 001 1h4v-7h4v7h4a1 1 0 001-1V10"/></svg></span>
    <small>Home</small>
  </a>
  <a href="/program" <?= $tab === 'program' ? 'class="active"' : '' ?>>
    <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/></svg></span>
    <small>Program</small>
  </a>
  <a href="/checkin" <?= $tab === 'checkin' ? 'class="active"' : '' ?>>
    <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
    <small>Check-in</small>
  </a>
  <a href="/meals" <?= $tab === 'meals' ? 'class="active"' : '' ?>>
    <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8zM6 1v3M10 1v3M14 1v3"/></svg></span>
    <small>Meals</small>
  </a>
  <a href="/chat" <?= $tab === 'chat' ? 'class="active"' : '' ?>>
    <span class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-12.5 6.6L4 20l1.4-4.5A8 8 0 1121 12z"/></svg></span>
    <small>Chat</small>
  </a>
</nav>
