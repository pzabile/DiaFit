<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$params = [];
$where = 'paid = 1';
if ($q !== '') {
    $where .= ' AND (email LIKE ? OR first_name LIKE ? OR phone LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$rows = db_all("SELECT id, first_name, email, phone, started_at, plan_days, program_path, last_login_at, created_at
                FROM leads WHERE {$where} ORDER BY id DESC LIMIT 500", $params);

$pageTitle = 'Members — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'members';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
?>
  <main class="admin-main">
    <header class="admin-page-head">
      <div>
        <h1>Paid members</h1>
        <p class="muted">Everyone with an active subscription.</p>
      </div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
        <form method="get" class="search-form">
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by email, name or phone…" />
          <button class="btn btn-ghost">Search</button>
        </form>
        <a href="/admin/new_member" class="btn btn-primary">+ Create member</a>
      </div>
    </header>

    <section class="card big">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Email</th><th>Phone</th><th>Plan</th><th>Started</th><th>Ends</th><th>Program</th><th>Last login</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $planDays = (int) ($r['plan_days'] ?? 84);
            if ($planDays <= 7)       { $planLabel = '7-day'; }
            elseif ($planDays <= 28)  { $planLabel = '4-week'; }
            else                      { $planLabel = '12-week'; }
            $endDate = '—';
            if (!empty($r['started_at'])) {
                try {
                    $d = new DateTime($r['started_at']);
                    $d->modify('+' . $planDays . ' days');
                    $endDate = $d->format('Y-m-d');
                } catch (Exception $ignored) {}
            }
          ?>
            <tr>
              <td><?= e($r['first_name']) ?></td>
              <td><?= e($r['email']) ?></td>
              <td><?= e($r['phone']) ?></td>
              <td><span class="chip"><?= e($planLabel) ?></span></td>
              <td><?= e($r['started_at'] ?: '—') ?></td>
              <td><?= e($endDate) ?></td>
              <td><?= $r['program_path'] ? '<span class="chip green">uploaded</span>' : '<span class="chip">pending</span>' ?></td>
              <td><?= e($r['last_login_at'] ?: '—') ?></td>
              <td><a class="link" href="/admin/member?id=<?= (int)$r['id'] ?>">Open →</a></td>
            </tr>
          <?php endforeach; if (!$rows): ?>
            <tr><td colspan="9" class="muted">No matching members.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
