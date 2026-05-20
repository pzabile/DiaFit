<?php
// Shared member-portal sidebar + mobile bottom nav.
// Each member tab page sets $activeTab before including this file.
$tab = $activeTab ?? '';
$me  = $me ?? (function_exists('current_member') ? current_member() : null);
$initial = strtoupper(substr($me['first_name'] ?? 'M', 0, 1));
$tgLink = cfg('telegram.member_link', '#');
?>
<aside class="side">
  <a class="brand" href="/dashboard">
    <span class="logo-dot"></span>
    <div>
      <span class="brand-name">DiaFitus</span>
      <span class="sub-tag">member portal</span>
    </div>
  </a>
  <nav class="side-nav">
    <a href="/dashboard" <?= $tab === 'overview' ? 'class="active"' : '' ?>><span>🏠</span>Overview</a>
    <a href="/program"   <?= $tab === 'program'  ? 'class="active"' : '' ?>><span>📄</span>My program</a>
    <a href="/checkin"   <?= $tab === 'checkin'  ? 'class="active"' : '' ?>><span>🗓️</span>Weekly check-in</a>
    <a href="/logs"      <?= $tab === 'logs'     ? 'class="active"' : '' ?>><span>📓</span>Daily check-ins</a>
    <a href="/meals"     <?= $tab === 'meals'    ? 'class="active"' : '' ?>><span>🍽️</span>Meal photos</a>
    <a href="/progress"  <?= $tab === 'progress' ? 'class="active"' : '' ?>><span>📈</span>Progress</a>
    <a href="/chat"      <?= $tab === 'chat'     ? 'class="active"' : '' ?>><span>💬</span>Chat with us</a>
    <a href="/account"   <?= $tab === 'account'  ? 'class="active"' : '' ?>><span>⚙️</span>Account</a>
  </nav>
  <div class="side-foot">
    <div class="side-user">
      <div class="avatar"><?= e($initial) ?></div>
      <div>
        <strong><?= e($me['first_name'] ?? 'Member') ?></strong>
        <small><?= e($me['email'] ?? '') ?></small>
      </div>
    </div>
    <a class="logout-link" href="/logout">Log out →</a>
  </div>
</aside>

<!-- Mobile top bar (replaces sidebar on small screens) -->
<header class="mobile-top">
  <a href="/dashboard" class="brand">
    <span class="logo-dot"></span>
    <span class="brand-name">DiaFitus</span>
  </a>
  <a href="/logout" class="logout-link">Log out</a>
</header>

<!-- Mobile bottom navigation -->
<nav class="mobile-nav" aria-label="Member sections">
  <a href="/dashboard" <?= $tab === 'overview' ? 'class="active"' : '' ?>>
    <span>🏠</span><small>Home</small>
  </a>
  <a href="/program" <?= $tab === 'program' ? 'class="active"' : '' ?>>
    <span>📄</span><small>Program</small>
  </a>
  <a href="/checkin" <?= $tab === 'checkin' ? 'class="active"' : '' ?>>
    <span>🗓️</span><small>Check-in</small>
  </a>
  <a href="/meals" <?= $tab === 'meals' ? 'class="active"' : '' ?>>
    <span>🍽️</span><small>Meals</small>
  </a>
  <a href="/chat" <?= $tab === 'chat' ? 'class="active"' : '' ?>>
    <span>💬</span><small>Chat</small>
  </a>
</nav>
