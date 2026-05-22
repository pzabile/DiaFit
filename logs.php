<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$dailyLogs = db_all('SELECT * FROM daily_logs WHERE lead_id = ? ORDER BY log_date DESC, created_at DESC LIMIT 90', [$me['id']]);

$ids = array_column($dailyLogs, 'id');
$commentsByLog   = [];
$repliesByParent = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $cs = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND target_type = "daily_log" AND target_id IN (' . $in . ') ORDER BY created_at ASC',
        array_merge([$me['id']], $ids));
    foreach ($cs as $c) {
        if ($c['parent_id']) $repliesByParent[(int)$c['parent_id']][] = $c;
        else $commentsByLog[(int)$c['target_id']][] = $c;
    }
}

$grouped = [];
foreach ($dailyLogs as $l) {
    $grouped[$l['log_date']][] = $l;
}

$pageTitle = 'Daily check-ins — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'logs';
require __DIR__ . '/includes/header.php';
$csrf = csrf_input();
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px">
      <div>
        <p class="eyebrow sage">Daily</p>
        <h1 class="h2 serif" style="margin-top:4px">How are you doing today?</h1>
        <p style="color:var(--muted);font-size:13.5px;margin-top:6px">Log as many times as you want, any day.</p>
      </div>
    </div>

    <!-- Log form -->
    <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px;margin-bottom:28px">
      <div style="font-weight:700;font-size:15px;margin-bottom:20px">+ New check-in</div>
      <form method="post" action="/save_log" class="log-grid">
        <?= $csrf ?>

        <div class="section">
          <div class="section-label" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600;margin-bottom:12px">Basics</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Date
              <input type="date" name="log_date" required value="<?= date('Y-m-d') ?>" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              How did you feel?
              <select name="feeling" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px">
                <option>Great</option><option>Good</option><option>Okay</option><option>Tired</option><option>Bad</option>
              </select>
            </label>
          </div>
        </div>

        <div class="section" style="margin-top:18px">
          <div class="section-label" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600;margin-bottom:12px">Workout</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Did you train?
              <select name="trained" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px"><option>Yes</option><option>No</option><option>Partial</option></select>
            </label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
              Where?
              <select name="train_where" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px"><option>Gym</option><option>Home</option><option>Outdoors</option></select>
            </label>
          </div>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500;margin-bottom:12px">
            What did you do?
            <textarea name="workout" rows="2" placeholder="e.g. Squats 4×8 @ 135 lbs, lat pulldown 3×10, 15 min incline walk" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Muscle soreness (0–10)
            <div style="display:flex;align-items:center;gap:12px">
              <input type="range" min="0" max="10" name="soreness" value="0" oninput="this.nextElementSibling.textContent=this.value" style="flex:1" />
              <output style="min-width:24px;text-align:center;font-weight:600;font-size:15px">0</output>
            </div>
          </label>
        </div>

        <div class="section" style="margin-top:18px">
          <div class="section-label" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600;margin-bottom:12px">Blood sugar</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">Before (mg/dL)<input type="number" name="bs_before" min="40" max="500" placeholder="120" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" /></label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">After (mg/dL)<input type="number" name="bs_after" min="40" max="500" placeholder="105" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" /></label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">Trend<select name="bs_trend" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px"><option>Stable</option><option>Increased</option><option>Decreased</option><option>Hypo event</option><option>Hyper event</option></select></label>
          </div>
        </div>

        <div class="section" style="margin-top:18px">
          <div class="section-label" style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);font-weight:600;margin-bottom:12px">Food</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">Before training<input type="text" name="food_before" placeholder="e.g. oatmeal + banana" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" /></label>
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">After training<input type="text" name="food_after" placeholder="e.g. chicken, rice, salad" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" /></label>
          </div>
        </div>

        <div style="margin-top:18px">
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Observations
            <textarea name="notes" rows="2" placeholder="Energy, mood, symptoms, sleep, medication changes…" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
          </label>
        </div>

        <div style="margin-top:20px">
          <button type="submit" style="background:var(--ink);color:#F4F1E9;padding:11px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer">Save check-in</button>
        </div>
      </form>
    </div>

    <!-- History -->
    <?php if (!$dailyLogs): ?>
      <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:48px 24px;text-align:center">
        <div style="font-size:32px;margin-bottom:12px">📓</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:6px">No check-ins yet</div>
        <p style="color:var(--muted);font-size:13.5px;margin:0">Add your first one above and we'll start building your story.</p>
      </div>
    <?php else: ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="eyebrow">History</div>
        <span style="font-size:12px;color:var(--muted)"><?= count($dailyLogs) ?> entries</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:20px">
        <?php foreach ($grouped as $date => $items): ?>
          <div>
            <div style="font-size:12.5px;font-weight:600;color:var(--muted);margin-bottom:8px;letter-spacing:.02em"><?= e(date('l, M j, Y', strtotime($date))) ?></div>
            <div style="display:flex;flex-direction:column;gap:10px">
              <?php foreach ($items as $l): ?>
                <article style="background:var(--card);border-radius:var(--r);border:1px solid var(--line);padding:16px 18px">
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
                    <span style="font-size:12px;color:var(--muted)"><?= e(date('g:ia', strtotime($l['created_at']))) ?></span>
                    <span class="chip" style="background:var(--sage-tint);color:var(--sage-2)"><?= e($l['feeling']) ?></span>
                    <?php if ($l['trained'] === 'Yes'): ?>
                      <span class="chip" style="background:var(--sky-tint);color:var(--sky)">Trained<?= $l['train_where'] ? ' · ' . e($l['train_where']) : '' ?></span>
                    <?php elseif ($l['trained'] === 'Partial'): ?>
                      <span class="chip" style="background:var(--amber-tint);color:var(--amber)">Partial workout</span>
                    <?php endif; ?>
                  </div>
                  <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:8px">
                    <?php if ($l['bs_before'] || $l['bs_after']): ?>
                      <div style="font-size:13px"><span style="color:var(--muted)">Glucose </span><strong><?= e($l['bs_before'] ?: '—') ?> → <?= e($l['bs_after'] ?: '—') ?></strong> <span style="font-size:11.5px;color:var(--muted)"><?= e($l['bs_trend']) ?></span></div>
                    <?php endif; ?>
                    <div style="font-size:13px"><span style="color:var(--muted)">Soreness </span><strong><?= (int)$l['soreness'] ?>/10</strong></div>
                  </div>
                  <?php if ($l['workout']):     ?><p style="font-size:13px;margin:4px 0"><strong>Workout.</strong> <?= nl2br(e($l['workout'])) ?></p><?php endif; ?>
                  <?php if ($l['food_before']): ?><p style="font-size:13px;margin:4px 0"><strong>Before.</strong> <?= e($l['food_before']) ?></p><?php endif; ?>
                  <?php if ($l['food_after']):  ?><p style="font-size:13px;margin:4px 0"><strong>After.</strong> <?= e($l['food_after']) ?></p><?php endif; ?>
                  <?php if ($l['notes']):       ?><p style="font-size:13px;color:var(--muted);margin:4px 0"><?= nl2br(e($l['notes'])) ?></p><?php endif; ?>

                  <?php $comments = $commentsByLog[$l['id']] ?? []; ?>
                  <?php if ($comments): ?>
                    <div style="margin-top:12px;border-top:1px solid var(--line);padding-top:12px;display:flex;flex-direction:column;gap:10px">
                      <?php foreach ($comments as $c):
                        $replies = $repliesByParent[$c['id']] ?? [];
                      ?>
                        <div style="background:var(--sage-tint);border-radius:9px;padding:12px 14px">
                          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            <span style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sage-2)">Coach</span>
                            <span style="font-size:11.5px;color:var(--muted)"><?= e(date('M j, g:ia', strtotime($c['created_at']))) ?></span>
                          </div>
                          <p style="font-size:13px;margin:0;color:var(--ink-2)"><?= nl2br(e($c['body'])) ?></p>
                          <?php foreach ($replies as $r): ?>
                            <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--line-2)">
                              <div style="font-size:11.5px;font-weight:600;margin-bottom:2px"><?= $r['from_member'] ? 'You' : 'Coach' ?> <span style="color:var(--muted);font-weight:400"><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></span></div>
                              <p style="font-size:13px;margin:0"><?= nl2br(e($r['body'])) ?></p>
                            </div>
                          <?php endforeach; ?>
                          <form method="post" action="/reply_note" style="display:flex;gap:8px;margin-top:10px">
                            <?= $csrf ?>
                            <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>" />
                            <input type="hidden" name="redirect" value="/logs" />
                            <input type="text" name="body" placeholder="Reply…" maxlength="2000" required style="flex:1;padding:7px 11px;border-radius:8px;border:1px solid var(--line-2);background:var(--card);font-size:13px" />
                            <button type="submit" style="padding:7px 14px;border-radius:8px;border:1px solid var(--line-2);background:var(--card);font-size:13px;font-weight:600;cursor:pointer">Reply</button>
                          </form>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
