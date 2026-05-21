<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$dbError = null;
$stats = [
    'leads' => 0, 'unpaid' => 0, 'members' => 0,
    'members_7d' => 0, 'leads_7d' => 0, 'logs_today' => 0,
    'weeklies_7d' => 0, 'meals_7d' => 0,
];
$recentPaid = $recentLeads = [];
$conversionRate = 0;
$mrr = 0;

try {
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
    $recentPaid  = db_all('SELECT id, first_name, email, created_at FROM leads WHERE paid = 1 ORDER BY id DESC LIMIT 8');
    $recentLeads = db_all('SELECT id, first_name, email, created_at FROM leads WHERE paid = 0 ORDER BY id DESC LIMIT 8');

    // Aggregate recent activity from members across all sources.
    $rWeekly = db_all('SELECT wn.id, wn.lead_id, wn.week_number, wn.created_at, l.first_name, l.email FROM weekly_notes wn JOIN leads l ON l.id = wn.lead_id WHERE wn.created_at >= NOW() - INTERVAL 14 DAY ORDER BY wn.created_at DESC LIMIT 12');
    $rDaily  = db_all('SELECT dl.id, dl.lead_id, dl.log_date, dl.created_at, l.first_name, l.email FROM daily_logs dl JOIN leads l ON l.id = dl.lead_id WHERE dl.created_at >= NOW() - INTERVAL 14 DAY ORDER BY dl.created_at DESC LIMIT 12');
    $rMeal   = db_all('SELECT mp.id, mp.lead_id, mp.meal_type, mp.created_at, l.first_name, l.email FROM meal_photos mp JOIN leads l ON l.id = mp.lead_id WHERE mp.created_at >= NOW() - INTERVAL 14 DAY ORDER BY mp.created_at DESC LIMIT 12');
    $rMsg    = db_all('SELECT cn.id, cn.lead_id, cn.body, cn.created_at, l.first_name, l.email FROM coach_notes cn JOIN leads l ON l.id = cn.lead_id WHERE cn.from_member = 1 AND cn.created_at >= NOW() - INTERVAL 14 DAY ORDER BY cn.created_at DESC LIMIT 12');

    $activity = [];
    foreach ($rWeekly as $r) $activity[] = ['t' => $r['created_at'], 'icon' => '🗓️', 'lead_id' => $r['lead_id'], 'who' => $r['first_name'] ?: $r['email'], 'text' => 'submitted week ' . (int) $r['week_number'] . ' check-in'];
    foreach ($rDaily  as $r) $activity[] = ['t' => $r['created_at'], 'icon' => '📓', 'lead_id' => $r['lead_id'], 'who' => $r['first_name'] ?: $r['email'], 'text' => 'logged a daily check-in (' . $r['log_date'] . ')'];
    foreach ($rMeal   as $r) $activity[] = ['t' => $r['created_at'], 'icon' => '🍽️', 'lead_id' => $r['lead_id'], 'who' => $r['first_name'] ?: $r['email'], 'text' => 'uploaded a ' . ($r['meal_type'] ?: 'meal') . ' photo'];
    foreach ($rMsg    as $r) $activity[] = ['t' => $r['created_at'], 'icon' => '💬', 'lead_id' => $r['lead_id'], 'who' => $r['first_name'] ?: $r['email'], 'text' => 'sent a message: ' . mb_strimwidth($r['body'], 0, 80, '…')];

    usort($activity, fn($a, $b) => strcmp($b['t'], $a['t']));
    $activity = array_slice($activity, 0, 15);
} catch (Throwable $ex) {
    $dbError = $ex->getMessage();
}

$pageTitle = 'Admin — DiaFitus';
$bodyClass = 'admin-page';
$activeTab = 'home';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
?>
  <main class="admin-main">
    <h1>Overview</h1>

    <?php if ($dbError): ?>
      <div class="db-warn">
        <strong>The database isn't ready yet.</strong>
        Make sure you've created the MySQL database in Hostinger, updated <code>config.php</code> with the credentials, and run <code>schema.sql</code> in phpMyAdmin. Detail: <em><?= e($dbError) ?></em>
      </div>
    <?php endif; ?>

    <div class="kpi-grid">
      <div class="kpi"><span>Total leads</span><strong><?= $stats['leads'] ?></strong><small>+<?= $stats['leads_7d'] ?> in 7d</small></div>
      <div class="kpi"><span>Paid members</span><strong><?= $stats['members'] ?></strong><small>+<?= $stats['members_7d'] ?> in 7d</small></div>
      <div class="kpi"><span>Assessments only</span><strong><?= $stats['unpaid'] ?></strong><small><?= $conversionRate ?>% converted</small></div>
      <div class="kpi"><span>Estimated MRR</span><strong>$<?= number_format($mrr) ?></strong><small>at $<?= (int)cfg('price_today') ?>/mo</small></div>
      <div class="kpi"><span>Daily logs today</span><strong><?= $stats['logs_today'] ?></strong></div>
      <div class="kpi"><span>Weekly check-ins (7d)</span><strong><?= $stats['weeklies_7d'] ?></strong></div>
      <div class="kpi"><span>Meal photos (7d)</span><strong><?= $stats['meals_7d'] ?></strong></div>
    </div>

    <section class="card big">
      <h2>📡 Recent activity (last 14 days)</h2>
      <p class="muted">Every time a member logs a check-in, uploads a meal or messages you, it lands here.</p>
      <?php if (!$activity): ?>
        <div class="empty-state"><div class="empty-icon">🌱</div><strong>Quiet for now.</strong><p>Activity from members will show up here.</p></div>
      <?php else: ?>
        <div class="activity-feed">
          <?php foreach ($activity as $a): ?>
            <a class="activity-row" href="/admin/member?id=<?= (int) $a['lead_id'] ?>">
              <span class="a-icon"><?= $a['icon'] ?></span>
              <div class="a-body">
                <strong><?= e($a['who']) ?></strong>
                <span><?= e($a['text']) ?></span>
              </div>
              <small><?= e(date('M j, g:ia', strtotime($a['t']))) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

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
