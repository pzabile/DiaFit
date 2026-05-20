<?php
// Shared admin chrome. Include after auth + bootstrap.
?>
<aside class="admin-side">
  <a href="/admin/" class="brand">
    <span class="logo-dot"></span>
    <div>
      <span class="brand-name">DiaFitus</span>
      <span class="sub-tag">admin panel</span>
    </div>
  </a>
  <nav class="admin-nav">
    <a href="/admin/"        <?= ($activeTab ?? '') === 'home'    ? 'class="active"' : '' ?>><span>📊</span> Overview</a>
    <a href="/admin/leads"   <?= ($activeTab ?? '') === 'leads'   ? 'class="active"' : '' ?>><span>📥</span> Leads (unpaid)</a>
    <a href="/admin/members" <?= ($activeTab ?? '') === 'members' ? 'class="active"' : '' ?>><span>👥</span> Paid members</a>
  </nav>
  <div class="admin-side-foot">
    <p class="muted">Protected by your web server (Hostinger directory password / IP whitelist).</p>
    <a href="/" class="link-back">← Back to site</a>
  </div>
</aside>
