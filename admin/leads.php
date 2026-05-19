<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$params = [];
$where = 'paid = 0';
if ($q !== '') {
    $where .= ' AND (email LIKE ? OR first_name LIKE ? OR phone LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$rows = db_all("SELECT id, first_name, email, phone, answers_json, created_at FROM leads WHERE {$where} ORDER BY id DESC LIMIT 500", $params);

$pageTitle = 'Leads (unpaid) — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'leads';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_layout.php';
?>
  <main class="admin-main">
    <header class="admin-page-head">
      <div>
        <h1>Assessments without payment</h1>
        <p class="muted">People who completed the questionnaire but did not (yet) subscribe.</p>
      </div>
      <form method="get" class="search-form">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by email, name or phone…" />
        <button class="btn btn-ghost">Search</button>
      </form>
    </header>

    <section class="card big">
      <table class="data-table">
        <thead>
          <tr><th>Name</th><th>Email</th><th>Phone</th><th>Diabetes</th><th>Goal</th><th>When</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $a = json_decode($r['answers_json'] ?? '{}', true) ?: [];
          ?>
            <tr>
              <td><?= e($r['first_name']) ?></td>
              <td><?= e($r['email']) ?></td>
              <td><?= e($r['phone']) ?></td>
              <td><?= e($a['diabetes_type'] ?? '—') ?></td>
              <td><?= e(is_array($a['goals'] ?? null) ? implode(', ', $a['goals']) : '—') ?></td>
              <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
              <td><a class="link" href="/admin/member?id=<?= (int)$r['id'] ?>">Open →</a></td>
            </tr>
          <?php endforeach; if (!$rows): ?>
            <tr><td colspan="7" class="muted">No matching leads.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
