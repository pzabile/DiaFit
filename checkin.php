<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
$me = require_member();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$weekNumber  = member_week_number($me);
$weeklyNotes = db_all('SELECT * FROM weekly_notes WHERE lead_id = ? ORDER BY week_number DESC, created_at DESC', [$me['id']]);

$ids = array_column($weeklyNotes, 'id');
$commentsByWeek  = [];
$repliesByParent = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $cs = db_all('SELECT * FROM coach_notes WHERE lead_id = ? AND is_private = 0 AND target_type = "weekly_note" AND target_id IN (' . $in . ') ORDER BY created_at ASC',
        array_merge([$me['id']], $ids));
    foreach ($cs as $c) {
        if ($c['parent_id']) $repliesByParent[(int)$c['parent_id']][] = $c;
        else $commentsByWeek[(int)$c['target_id']][] = $c;
    }
}
$csrf = csrf_input();

$pageTitle = 'Weekly check-in — DiaFitus';
$bodyClass = 'portal-page';
$activeTab = 'checkin';
require __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/member_sidebar_v2.php'; ?>
<main class="main">
  <div class="view">
    <?php if ($flash): ?><div class="alert success" style="margin-bottom:20px"><?= e($flash) ?></div><?php endif; ?>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:28px">
      <div>
        <p class="eyebrow sage">Week <?= (int) $weekNumber ?></p>
        <h1 class="h2 serif" style="margin-top:4px">How was your week?</h1>
        <p style="color:var(--muted);font-size:13.5px;margin-top:6px">Leave a check-in so your coach can adjust your program. About 2 minutes.</p>
      </div>
      <span style="background:var(--sage-tint);color:var(--sage-2);font-size:12px;font-weight:600;padding:5px 12px;border-radius:99px;white-space:nowrap;align-self:flex-start">~2 min</span>
    </div>

    <!-- Weekly form -->
    <div style="background:var(--card);border-radius:var(--r-lg);border:1px solid var(--line);padding:24px;margin-bottom:28px">
      <p style="color:var(--muted);font-size:13.5px;margin:0 0 20px">The more you share, the better we can help. All fields are optional except the week number.</p>
      <form method="post" action="/save_weekly" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <input type="hidden" name="week_number" value="<?= (int) $weekNumber ?>" />

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px">
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Avg blood glucose
            <input type="number" name="avg_glucose" min="40" max="500" placeholder="e.g. 118 mg/dL" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Weight (lbs)
            <input type="number" step="0.1" name="weight_lbs" min="60" max="600" placeholder="e.g. 187" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
            <span style="font-size:11px;color:var(--muted)">1 lb ≈ 0.45 kg</span>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Energy (0–10)
            <input type="number" name="energy_rating" min="0" max="10" placeholder="e.g. 7" style="padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px" />
          </label>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:18px">
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Wins this week
            <textarea name="wins" rows="3" placeholder="What went well? Workouts you completed, hypos avoided, meals on track…" style="padding:10px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Struggles
            <textarea name="struggles" rows="3" placeholder="What was hard? Sugar spikes, soreness, time, motivation…" style="padding:10px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
          </label>
          <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500">
            Anything else
            <textarea name="content" rows="3" placeholder="Symptoms, medication changes, life events, questions for your coach…" style="padding:10px 12px;border-radius:9px;border:1px solid var(--line);background:var(--bg);font-size:13.5px;resize:vertical"></textarea>
          </label>
        </div>

        <button type="submit" style="background:var(--ink);color:#F4F1E9;padding:11px 24px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer">Save week <?= (int) $weekNumber ?> check-in</button>
      </form>
    </div>

    <!-- History -->
    <?php if ($weeklyNotes): ?>
      <div class="eyebrow" style="margin-bottom:16px">Past weeks</div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ($weeklyNotes as $w):
          $wLb = $w['weight_kg'] ? round($w['weight_kg'] * 2.20462, 1) : null;
          $cmts = $commentsByWeek[$w['id']] ?? [];
        ?>
          <div style="background:var(--card);border-radius:var(--r);border:1px solid var(--line);padding:16px 18px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
              <div style="width:36px;height:36px;border-radius:50%;background:var(--sage-tint);color:var(--sage-2);display:grid;place-items:center;font-weight:700;font-size:12px;flex-shrink:0">W<?= (int)$w['week_number'] ?></div>
              <div style="font-weight:600;font-size:14px">Week <?= (int)$w['week_number'] ?></div>
              <div style="font-size:12px;color:var(--muted)"><?= e(date('M j, Y', strtotime($w['created_at']))) ?></div>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-left:auto">
                <?php if ($w['avg_glucose']):    ?><span class="chip" style="background:var(--sage-tint);color:var(--sage-2)"><?= (int)$w['avg_glucose'] ?> mg/dL</span><?php endif; ?>
                <?php if ($wLb):                 ?><span class="chip" style="background:var(--sky-tint);color:var(--sky)"><?= e($wLb) ?> lbs</span><?php endif; ?>
                <?php if ($w['energy_rating'] !== null): ?><span class="chip" style="background:var(--amber-tint);color:var(--amber)"><?= (int)$w['energy_rating'] ?>/10 energy</span><?php endif; ?>
              </div>
            </div>
            <?php if ($w['wins']):      ?><p style="font-size:13px;margin:4px 0"><strong>Wins.</strong> <?= nl2br(e($w['wins'])) ?></p><?php endif; ?>
            <?php if ($w['struggles']): ?><p style="font-size:13px;margin:4px 0"><strong>Struggles.</strong> <?= nl2br(e($w['struggles'])) ?></p><?php endif; ?>
            <?php if ($w['content']):   ?><p style="font-size:13px;color:var(--muted);margin:4px 0"><?= nl2br(e($w['content'])) ?></p><?php endif; ?>

            <?php if ($cmts): ?>
              <div style="margin-top:12px;border-top:1px solid var(--line);padding-top:12px;display:flex;flex-direction:column;gap:8px">
                <?php foreach ($cmts as $c):
                  $replies = $repliesByParent[$c['id']] ?? [];
                ?>
                  <div style="background:var(--sage-tint);border-radius:9px;padding:12px 14px">
                    <div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sage-2);margin-bottom:4px">Coach <span style="font-weight:400;letter-spacing:0;text-transform:none;font-size:11.5px"><?= e(date('M j, g:ia', strtotime($c['created_at']))) ?></span></div>
                    <p style="font-size:13px;margin:0"><?= nl2br(e($c['body'])) ?></p>
                    <?php foreach ($replies as $r): ?>
                      <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--line-2)">
                        <div style="font-size:11.5px;font-weight:600;margin-bottom:2px"><?= $r['from_member'] ? 'You' : 'Coach' ?></div>
                        <p style="font-size:13px;margin:0"><?= nl2br(e($r['body'])) ?></p>
                      </div>
                    <?php endforeach; ?>
                    <form method="post" action="/reply_note" style="display:flex;gap:8px;margin-top:10px">
                      <?= $csrf ?>
                      <input type="hidden" name="parent_id" value="<?= (int)$c['id'] ?>" />
                      <input type="hidden" name="redirect" value="/checkin" />
                      <input type="text" name="body" placeholder="Reply…" maxlength="2000" required style="flex:1;padding:7px 11px;border-radius:8px;border:1px solid var(--line-2);background:var(--card);font-size:13px" />
                      <button type="submit" style="padding:7px 14px;border-radius:8px;border:1px solid var(--line-2);background:var(--card);font-size:13px;font-weight:600;cursor:pointer">Reply</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
