<?php
// Shared admin chrome. Include after auth + bootstrap.
?>
<header class="admin-top">
  <a href="/admin/" class="brand">
    <span class="logo-dot"></span>
    <span class="brand-name">DiaFitus · admin</span>
  </a>
  <nav class="admin-nav">
    <a href="/admin/" <?= ($activeTab ?? '') === 'home'    ? 'class="active"' : '' ?>>📊 Overview</a>
    <a href="/admin/leads"   <?= ($activeTab ?? '') === 'leads'   ? 'class="active"' : '' ?>>📥 Leads</a>
    <a href="/admin/members" <?= ($activeTab ?? '') === 'members' ? 'class="active"' : '' ?>>👥 Members</a>
  </nav>
  <a href="/admin/logout" class="btn btn-ghost">Log out</a>
</header>
