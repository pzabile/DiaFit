<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$stats = [
    'leads'           => (int) db_get('SELECT COUNT(*) c FROM leads')['c'],
    'unpaid'          => (int) db_get('SELECT COUNT(*) c FROM leads WHERE paid = 0')['c'],
    'members'         => (int) db_get('SELECT COUNT(*) c FROM leads WHERE paid = 1')['c'],
    'members_7d'      => (int) db_get('SELECT COUNT(*) c FROM leads WHERE paid = 1 AND created_at >= NOW() - INTERVAL 7 DAY')['c'],
    'leads_7d'        => (int) db_get('SELECT COUNT(*) c FROM leads WHERE created_at >= NOW() - INTERVAL 7 DAY')['c'],
    'logs_today'      => (int) db_get('SELECT COUNT(*) c FROM daily_logs WHERE log_date = CURDATE()')['c'],
    'weeklies_7d'     => (int) db_get('SELECT COUNT(*) c FROM weekly_notes WHERE created_at >= NOW() - INTERVAL 7 DAY')['c'],
    'meals_7d'        => (int) db_get('SELECT COUNT(*) c FROM meal_photos WHERE created_at >= NOW() - INTERVAL 7 DAY')['c'],
];
$conversionRate = $stats['leads'] > 0 ? round(100 * $stats['members'] / $stats['leads'], 1) : 0;
$mrr = $stats['members'] * (int) cfg('price_today');

$recentPaid    = db_all('SELECT id, first_name, email, created_at FROM leads WHERE paid = 1 ORDER BY id DESC LIMIT 8');
$recentLeads   = db_all('SELECT id, first_name, email, created_at FROM leads WHERE paid = 0 ORDER BY id DESC LIMIT 8');

$pageTitle = 'Admin — DiaFitus';
$bodyClass = 'admin-page';
$activeTab = 'home';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
?>
  <main class="admin-main">
    <h1>Overview</h1>
    <div class="kpi-grid">
      <div class="kpi"><span>Total leads</span><strong><?= $stats['leads'] ?></strong><small>+<?= $stats['leads_7d'] ?> in 7d</small></div>
      <div class="kpi"><span>Paid members</span><strong><?= $stats['members'] ?></strong><small>+<?= $stats['members_7d'] ?> in 7d</small></div>
      <div class="kpi"><span>Assessments only</span><strong><?= $stats['unpaid'] ?></strong><small><?= $conversionRate ?>% converted</small></div>
      <div class="kpi"><span>Estimated MRR</span><strong>$<?= number_format($mrr) ?></strong><small>at $<?= (int)cfg('price_today') ?>/mo</small></div>
      <div class="kpi"><span>Daily logs today</span><strong><?= $stats['logs_today'] ?></strong></div>
      <div class="kpi"><span>Weekly check-ins (7d)</span><strong><?= $stats['weeklies_7d'] ?></strong></div>
      <div class="kpi"><span>Meal photos (7d)</span><strong><?= $stats['meals_7d'] ?></strong></div>
    </div>

    <div class="grid-2">
      <section class="card big">
        <h2>Recent paid members</h2>
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentPaid as $r): ?>
              <tr>
                <td><?= e($r['first_name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                <td><a href="/admin/member?id=<?= (int)$r['id'] ?>" class="link">Open →</a></td>
              </tr>
            <?php endforeach; if (!$recentPaid): ?>
              <tr><td colspan="4" class="muted">No paid members yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section class="card big">
        <h2>Recent assessments (unpaid)</h2>
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>When</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($recentLeads as $r): ?>
              <tr>
                <td><?= e($r['first_name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                <td><a href="/admin/member?id=<?= (int)$r['id'] ?>" class="link">Open →</a></td>
              </tr>
            <?php endforeach; if (!$recentLeads): ?>
              <tr><td colspan="4" class="muted">No leads yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </div>
  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
