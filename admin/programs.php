<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/uploads.php';
require_admin();

$error   = '';
$success = '';

// Handle program upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Session expired.';
    } else {
        $memberId = (int)($_POST['member_id'] ?? 0);
        if (!$memberId) {
            $error = 'Please select a member.';
        } else {
            try {
                $path = save_admin_program_pdf($_FILES['program'] ?? [], $memberId);
                db_exec('UPDATE leads SET program_path = ?, updated_at = NOW() WHERE id = ?', [$path, $memberId]);
                header('Location: /admin/programs?published=' . $memberId); exit;
            } catch (Throwable $ex) {
                $error = 'Upload failed: ' . $ex->getMessage();
            }
        }
    }
}

$publishedId = (int)($_GET['published'] ?? 0);
$publishedMember = $publishedId ? db_get('SELECT first_name, email FROM leads WHERE id = ?', [$publishedId]) : null;

$recentRows  = db_all("SELECT l.id, l.first_name, l.email, l.program_path, l.updated_at FROM leads l WHERE l.paid=1 AND l.program_path IS NOT NULL AND l.program_path != '' ORDER BY l.updated_at DESC LIMIT 5");
$allMembers  = db_all('SELECT id, first_name, email FROM leads WHERE paid=1 ORDER BY first_name, email');
$membersCount = (int)db_get('SELECT COUNT(*) c FROM leads WHERE paid=1')['c'];
$programCount = (int)db_get("SELECT COUNT(*) c FROM leads WHERE paid=1 AND program_path IS NOT NULL AND program_path != ''"  )['c'];

$pageTitle = 'Program Builder — DiaFitus admin';
$bodyClass = 'admin-page';
$activeTab = 'programs';
require __DIR__ . '/../includes/header.php';
?>
<style>
/* ===== Program builder stage styles ===== */
.pb-stage{display:none}
.pb-stage.active{display:block}
body[data-stage="upload"] #stage-upload,
body[data-stage="parsing"] #stage-parsing,
body[data-stage="review"] #stage-review,
body[data-stage="done"] #stage-done { display:block }

/* Drop zone */
.pb-drop{
  border:1.5px dashed var(--line-2);border-radius:22px;
  background:linear-gradient(180deg,#FFFDF7,#FAF7EE);
  padding:36px 32px;text-align:center;transition:.2s;
  display:flex;flex-direction:column;align-items:center;gap:14px;cursor:pointer;
}
.pb-drop:hover,.pb-drop.hover{border-color:var(--sage);background:linear-gradient(180deg,#FFFDF7,var(--sage-tint))}
.pb-drop-ic{width:64px;height:64px;border-radius:18px;background:var(--card);display:grid;place-items:center;border:1px solid var(--line);color:var(--ink-2);box-shadow:0 8px 20px -10px rgba(27,32,28,.18)}
.pb-drop-ttl{font-family:"Instrument Serif",serif;font-size:28px;line-height:1.1;letter-spacing:-.015em}
.pb-drop-sub{color:var(--muted);max-width:46ch;font-size:13.5px}
.pb-drop-or{font-size:11px;color:var(--muted);letter-spacing:.16em;text-transform:uppercase;font-weight:600}

/* Recent table */
.pb-recent{background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow)}
.pb-recent h3{margin:0;font-size:15px;font-weight:600;padding:18px 20px 6px}
.pb-recent .sub{padding:0 20px 14px;color:var(--muted);font-size:12.5px}
.pb-recent .row{display:flex;align-items:center;gap:12px;padding:12px 20px;border-top:1px solid var(--line);font-size:13px}
.pb-recent .row:hover{background:var(--bg-2)}
.pb-recent .file-ic{width:32px;height:40px;border-radius:6px;background:var(--bg-2);border:1px solid var(--line);display:grid;place-items:center;font-family:"JetBrains Mono",monospace;font-size:9px;color:var(--muted);font-weight:600;flex-shrink:0}
.pb-recent .nm{font-weight:600;font-size:13px;white-space:nowrap;text-overflow:ellipsis;overflow:hidden}
.pb-recent .meta{font-size:11.5px;color:var(--muted);font-family:"JetBrains Mono",monospace}

/* Signals */
.pb-signals{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}
@media(max-width:900px){.pb-signals{grid-template-columns:1fr}}
.pb-signal{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 20px}
.pb-signal .lbl{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);font-weight:600}
.pb-signal .num{font-family:"Instrument Serif",serif;font-size:36px;letter-spacing:-.02em;margin-top:6px;line-height:1}
.pb-signal .sub{color:var(--muted);font-size:12px;margin-top:4px}

/* Parse steps */
.pb-parse-wrap{display:grid;grid-template-columns:380px 1fr;gap:24px;align-items:start;max-width:1000px}
@media(max-width:900px){.pb-parse-wrap{grid-template-columns:1fr}}
.pb-parse-card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:22px;box-shadow:var(--shadow)}
.pb-parse-step{display:flex;align-items:center;gap:14px;padding:14px 4px;border-bottom:1px dashed var(--line)}
.pb-parse-step:last-child{border-bottom:0}
.pb-parse-step .ic{width:30px;height:30px;border-radius:50%;background:var(--bg-2);display:grid;place-items:center;color:var(--muted);flex-shrink:0}
.pb-parse-step.done .ic{background:var(--sage-tint);color:var(--sage-2)}
.pb-parse-step.active .ic{background:var(--sage);color:#fff;animation:pb-pulse 1.2s ease-in-out infinite}
@keyframes pb-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.08);opacity:.85}}
.pb-parse-step .ttl{font-weight:600;font-size:14px}
.pb-parse-step .sub{font-size:12px;color:var(--muted);margin-top:2px}
.pb-parse-step .pct{margin-left:auto;font-family:"JetBrains Mono",monospace;font-size:11px;color:var(--muted)}
.pb-parse-step.done .pct{color:var(--sage-2);font-weight:600}

/* Review */
.pb-review-top{
  display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
  padding:18px 22px;background:var(--card);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);margin-bottom:18px;
}
.pb-review-top .left{display:flex;align-items:center;gap:14px}
.pb-review-top .file-ic{width:42px;height:42px;border-radius:11px;background:var(--sage-tint);color:var(--sage-2);display:grid;place-items:center;flex-shrink:0}
.pb-review-top h2{margin:0;font-size:16px;font-weight:600}
.pb-review-top .meta{font-size:12px;color:var(--muted);margin-top:2px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.pb-assign{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:10px;padding:6px 8px 6px 10px;background:var(--bg);font-size:13px}
.pb-assign .lbl{color:var(--muted);font-size:12px;white-space:nowrap}
.pb-assign .av{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#D6C9A8,#A77F4C);color:#fff;display:grid;place-items:center;font-size:10.5px;font-weight:700;flex-shrink:0}
.pb-assign select{border:0;background:transparent;font-weight:600;outline:none;padding-right:4px;font-size:13px;max-width:180px}

/* Done card */
.pb-done-card{
  max-width:680px;margin:24px auto;text-align:center;
  background:linear-gradient(180deg,#FFFDF7,var(--sage-tint));
  border:1px solid var(--sage-tint-2, #D8E6D9);border-radius:24px;padding:50px 36px;box-shadow:var(--shadow);
}
.pb-done-card .pulse{width:80px;height:80px;border-radius:50%;background:var(--sage);color:#fff;display:grid;place-items:center;margin:0 auto 18px;animation:pb-burst .6s cubic-bezier(.34,1.56,.64,1)}
@keyframes pb-burst{0%{transform:scale(.3);opacity:0}100%{transform:scale(1);opacity:1}}
.pb-done-card h2{font-family:"Instrument Serif",serif;font-size:42px;letter-spacing:-.02em;margin:0;font-weight:400}
.pb-done-card h2 em{font-style:italic;color:var(--sage-2)}
.pb-done-card p{color:var(--muted);margin:8px auto 24px;max-width:48ch}

/* Upload form inside review */
.pb-upload-form{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px;box-shadow:var(--shadow)}
.pb-field-lbl{display:block;margin-bottom:5px;font-size:12px;font-weight:600;color:var(--ink-2)}
.pb-inp,.pb-sel{width:100%;border:1px solid var(--line);background:var(--bg);border-radius:9px;padding:9px 12px;font-size:13.5px;color:var(--ink);outline:none;transition:.15s;font-family:inherit}
.pb-inp:focus,.pb-sel:focus{background:#fff;border-color:var(--sage);box-shadow:0 0 0 3px var(--sage-tint)}
.pb-file-input{padding:8px 12px;border:1px dashed var(--line-2);border-radius:9px;background:var(--bg);width:100%;font-size:13px;color:var(--muted);cursor:pointer}
.pb-file-input:hover{border-color:var(--sage)}
.pb-hint-banner{
  display:flex;gap:12px;align-items:flex-start;background:var(--sage-tint);border:1px solid #D8E6D9;
  border-radius:12px;padding:12px 14px;font-size:12.5px;margin-top:14px;color:#264033;
}

/* Upload hero grid */
.pb-upload-hero{display:grid;grid-template-columns:1.3fr 1fr;gap:24px;align-items:start}
@media(max-width:1100px){.pb-upload-hero{grid-template-columns:1fr}}
</style>

<div class="app">
<?php require __DIR__ . '/_layout-v2.php'; ?>
  <main class="main" id="pb-main">
    <header class="topbar">
      <div class="crumb">
        <span class="dot"></span>
        <span>Programs</span>
        <span style="color:var(--line-2)">›</span>
        <b id="pb-crumb">New program from PDF</b>
      </div>
      <div class="top-actions">
        <a href="/admin/members" class="btn">View members →</a>
      </div>
    </header>

    <?php if ($error): ?>
    <div class="view" style="padding-bottom:0">
      <div style="background:#FDE8E6;border:1px solid #F5C0BA;border-radius:12px;padding:12px 16px;font-size:13.5px;color:#7D2018;display:flex;gap:10px">
        <span>⚠</span><span><?= e($error) ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- ===================== STAGE: DONE (when ?published=X) ===================== -->
    <?php if ($publishedMember): ?>
    <div class="view" id="stage-done-php">
      <div class="pb-done-card">
        <div class="pulse">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
        </div>
        <h2>Program <em>live.</em></h2>
        <p>
          <?= e($publishedMember['first_name'] ?: $publishedMember['email']) ?> can now view their program from the member portal.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <a href="/admin/member?id=<?= $publishedId ?>" class="btn pri">Open member profile →</a>
          <a href="/admin/programs" class="btn">Upload another →</a>
        </div>
      </div>
    </div>
    <?php return; endif; ?>

    <!-- ===================== STAGE 1: UPLOAD ===================== -->
    <div class="view pb-stage active" id="stage-upload">

      <div class="eyebrow" style="color:var(--plum,#7D5A8A)">Programs · ingest</div>
      <h2 style="font-family:'Instrument Serif',serif;font-size:42px;line-height:1.02;letter-spacing:-.02em;font-weight:400;margin:6px 0 6px">Turn a <em style="font-style:italic;color:var(--sage-2)">PDF plan</em> into a member program.</h2>
      <p class="muted" style="max-width:62ch;margin:0 0 24px;font-size:13.5px">Drop the generated plan PDF below. Select the member to assign it to, review, and publish straight to the member's app.</p>

      <div class="pb-upload-hero">
        <div>
          <div class="pb-drop" id="pb-drop" onclick="document.getElementById('pb-quick-file').click()" ondragover="event.preventDefault();this.classList.add('hover')" ondragleave="this.classList.remove('hover')" ondrop="pb_handleDrop(event)">
            <input type="file" id="pb-quick-file" accept="application/pdf" hidden onchange="pb_fileSelected(this.files[0])" />
            <div class="pb-drop-ic">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <path d="M14 2v6h6"/><path d="M12 12v6m-3-3l3-3 3 3"/>
              </svg>
            </div>
            <div>
              <div class="pb-drop-ttl">Drop the plan PDF here</div>
              <div class="pb-drop-sub" style="margin-top:4px">Supports Diafitus-generated plans (4-week, 8-week, 12-week). The PDF will be assigned to the selected member.</div>
            </div>
            <button class="btn pri lg" type="button" onclick="event.stopPropagation();document.getElementById('pb-quick-file').click()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              Choose a PDF
            </button>
            <div class="pb-drop-or">or drag &amp; drop here</div>
          </div>

          <div class="pb-hint-banner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
            <div><strong>Tip · </strong>Diafitus-generated PDFs publish directly. For manually written plans, use the member profile page to upload and set the plan duration separately.</div>
          </div>
        </div>

        <!-- Recently ingested -->
        <div class="pb-recent">
          <h3>Recently assigned programs</h3>
          <div class="sub">Latest <?= min(5, count($recentRows)) ?> uploads — click to open member</div>
          <?php if ($recentRows): ?>
            <?php foreach ($recentRows as $r): ?>
              <div class="row" onclick="location.href='/admin/member?id=<?= (int)$r['id'] ?>'" style="cursor:pointer">
                <div class="file-ic">PDF</div>
                <div style="flex:1;min-width:0">
                  <div class="nm"><?= e(basename($r['program_path'])) ?></div>
                  <div class="meta"><?= e($r['first_name'] ?: $r['email']) ?> · <?= e(date('M j, Y', strtotime($r['updated_at']))) ?></div>
                </div>
                <span class="chip sage">Live</span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="row" style="color:var(--muted);font-size:13px">No programs uploaded yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="pb-signals">
        <div class="pb-signal">
          <div class="lbl">Total members</div>
          <div class="num"><?= $membersCount ?></div>
          <div class="sub">Active paid members</div>
        </div>
        <div class="pb-signal">
          <div class="lbl">Programs assigned</div>
          <div class="num"><?= $programCount ?></div>
          <div class="sub"><?= $membersCount > 0 ? round($programCount / $membersCount * 100) : 0 ?>% of members have a program</div>
        </div>
        <div class="pb-signal">
          <div class="lbl">Without program</div>
          <div class="num"><?= $membersCount - $programCount ?></div>
          <div class="sub">Members awaiting their plan</div>
        </div>
      </div>
    </div>

    <!-- ===================== STAGE 2: PARSING ===================== -->
    <div class="view pb-stage" id="stage-parsing">
      <div class="pb-parse-wrap">

        <!-- PDF preview -->
        <div class="pb-parse-card">
          <div style="border:1px solid var(--line);border-radius:12px;background:#fff;padding:20px 18px;font-size:8px;line-height:1.3;color:var(--ink-2);min-height:200px;position:relative;overflow:hidden">
            <div style="position:absolute;inset:0;background:linear-gradient(transparent 60%,rgba(255,253,247,.95));pointer-events:none;z-index:1"></div>
            <div style="font-size:9px;font-weight:700;margin:0 0 4px;letter-spacing:.04em">DiaFitus — Personalized plan</div>
            <hr style="border:0;border-top:0.5px solid var(--line);margin:6px 0"/>
            <div id="pb-pdf-name" style="font-size:11px;font-weight:600;margin:4px 0 8px;color:var(--ink-2)">Reading PDF…</div>
            <div style="font-size:7px;line-height:1.4;color:var(--muted)">Extracting schedule, workouts, glucose rules and progression notes.</div>
          </div>
          <div style="text-align:center;margin-top:10px;font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)" id="pb-pdf-meta">Preparing…</div>
        </div>

        <!-- Steps -->
        <div class="pb-parse-card">
          <div class="eyebrow" style="color:var(--plum,#7D5A8A)">Working…</div>
          <h2 style="font-family:'Instrument Serif',serif;font-size:28px;letter-spacing:-.015em;margin:6px 0 16px;font-weight:400">Reading <em style="font-style:italic;color:var(--sage-2)" id="pb-reading-name">your PDF</em></h2>

          <div id="pb-parse-steps">
            <div class="pb-parse-step" data-step="0">
              <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
              <div style="flex:1"><div class="ttl">PDF received</div><div class="sub">Validating file format…</div></div>
              <div class="pct" id="pb-pct-0">—</div>
            </div>
            <div class="pb-parse-step" data-step="1">
              <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
              <div style="flex:1"><div class="ttl">Text extracted</div><div class="sub">Scanning document content…</div></div>
              <div class="pct" id="pb-pct-1">—</div>
            </div>
            <div class="pb-parse-step" data-step="2">
              <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
              <div style="flex:1"><div class="ttl">Identifying sections</div><div class="sub">Welcome · Schedule · Workouts · Glucose rules</div></div>
              <div class="pct" id="pb-pct-2">—</div>
            </div>
            <div class="pb-parse-step" data-step="3">
              <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
              <div style="flex:1"><div class="ttl">Mapping to member schema</div><div class="sub">Days, exercises, progression…</div></div>
              <div class="pct" id="pb-pct-3">—</div>
            </div>
            <div class="pb-parse-step" data-step="4">
              <div class="ic"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
              <div style="flex:1"><div class="ttl">Ready for review</div><div class="sub">Assign to member and publish</div></div>
              <div class="pct" id="pb-pct-4">—</div>
            </div>
          </div>

          <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:12px">
            <span id="pb-parse-eta">Preparing…</span>
            <button class="btn sm" type="button" onclick="pb_goStage('upload')">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== STAGE 3: REVIEW & UPLOAD ===================== -->
    <div class="view pb-stage" id="stage-review">

      <div class="pb-review-top">
        <div class="left">
          <div class="file-ic">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>
          </div>
          <div>
            <h2 id="pb-review-filename">Selected PDF</h2>
            <div class="meta">
              <span id="pb-review-size">—</span>
              <span>·</span>
              <span class="chip sage" style="font-size:11.5px;padding:3px 8px">Ready to assign</span>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <button class="btn" type="button" onclick="pb_goStage('upload')">← Change file</button>
          <button class="btn pri" type="button" onclick="document.getElementById('pb-real-form').requestSubmit ? document.getElementById('pb-real-form').requestSubmit() : document.getElementById('pb-submit-btn').click()">
            Publish to member
          </button>
        </div>
      </div>

      <!-- Real upload form -->
      <form method="post" enctype="multipart/form-data" id="pb-real-form" action="/admin/programs">
        <?= csrf_input() ?>
        <input type="file" id="pb-real-file" name="program" accept="application/pdf" hidden required />

        <div class="pb-upload-form">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
            <div>
              <label class="pb-field-lbl">Assign to member</label>
              <div class="pb-assign" style="border-radius:9px;background:#fff;border:1px solid var(--line);padding:0">
                <select name="member_id" id="pb-member-sel" class="pb-sel" style="border-radius:9px" required>
                  <option value="">— Select member —</option>
                  <?php foreach ($allMembers as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['first_name'] ?: $m['email']) ?> (<?= e($m['email']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="pb-hint-banner" style="margin-top:12px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>The PDF will replace any existing program for this member. They'll see it immediately in their portal.</div>
              </div>
            </div>

            <div>
              <label class="pb-field-lbl">PDF file</label>
              <div id="pb-file-display" style="border:1px solid var(--sage);border-radius:9px;padding:12px 14px;background:var(--sage-tint);display:flex;align-items:center;gap:10px;font-size:13px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--sage-2)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>
                <span id="pb-file-display-name" style="font-weight:600;color:var(--sage-2)">Loaded</span>
                <button type="button" onclick="pb_goStage('upload')" style="margin-left:auto;color:var(--muted);font-size:12px">Change</button>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" id="pb-submit-btn" style="display:none"></button>
      </form>

      <!-- Members without program callout -->
      <?php if ($membersCount - $programCount > 0): ?>
      <div style="margin-top:20px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;font-size:13.5px">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
        <div>
          <strong><?= $membersCount - $programCount ?></strong> member<?= $membersCount - $programCount !== 1 ? 's' : '' ?> still waiting for a program.
          <a href="/admin/members" style="color:var(--sage-2);font-weight:600;margin-left:6px">View all members →</a>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
(function(){
  var currentStage = 'upload';
  var selectedFile = null;

  window.pb_goStage = function(stage){
    currentStage = stage;
    var crumbMap = {upload:'New program from PDF', parsing:'Parsing PDF…', review:'Review & assign', done:'Program published'};
    document.getElementById('pb-crumb').textContent = crumbMap[stage] || stage;
    document.querySelectorAll('.pb-stage').forEach(function(s){ s.classList.remove('active'); });
    var target = document.getElementById('stage-' + stage);
    if (target) target.classList.add('active');
    window.scrollTo(0,0);
  };

  function formatBytes(b){
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(0) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
  }

  window.pb_fileSelected = function(file){
    if (!file) return;
    selectedFile = file;

    // Update review stage UI
    document.getElementById('pb-review-filename').textContent = file.name;
    document.getElementById('pb-review-size').textContent = formatBytes(file.size);
    document.getElementById('pb-file-display-name').textContent = file.name;
    document.getElementById('pb-reading-name').textContent = file.name;
    document.getElementById('pb-pdf-name').textContent = file.name;
    document.getElementById('pb-pdf-meta').textContent = formatBytes(file.size);

    // Transfer file to real hidden input via DataTransfer
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      document.getElementById('pb-real-file').files = dt.files;
    } catch(e){}

    // Go to parsing animation
    pb_goStage('parsing');
    pb_animateParse();
  };

  window.pb_handleDrop = function(e){
    e.preventDefault();
    document.getElementById('pb-drop').classList.remove('hover');
    var f = e.dataTransfer.files[0];
    if (f && f.type === 'application/pdf') {
      pb_fileSelected(f);
    } else {
      alert('Please drop a PDF file.');
    }
  };

  function pb_animateParse(){
    var steps = [0, 1, 2, 3, 4];
    var delays = [400, 700, 1100, 1500, 1900];
    var stepEls = document.querySelectorAll('.pb-parse-step');
    var eta = document.getElementById('pb-parse-eta');

    // Reset all steps
    stepEls.forEach(function(s){ s.className='pb-parse-step'; });
    steps.forEach(function(i){
      var pctEl = document.getElementById('pb-pct-'+i);
      if (pctEl) pctEl.textContent = 'queued';
    });

    var total = delays[delays.length-1] + 400;
    var start = Date.now();

    function tick(){
      var elapsed = Date.now() - start;
      var rem = Math.max(0, Math.ceil((total - elapsed) / 1000));
      eta.textContent = rem > 0 ? 'About ' + rem + 's remaining…' : 'Finishing up…';
    }
    var etaTimer = setInterval(tick, 300);

    delays.forEach(function(delay, idx){
      setTimeout(function(){
        // Mark previous as done
        if (idx > 0) {
          stepEls[idx-1].className='pb-parse-step done';
          var prev = document.getElementById('pb-pct-'+(idx-1));
          if (prev) prev.textContent='100%';
        }
        // Mark current as active
        stepEls[idx].className='pb-parse-step active';
        var cur = document.getElementById('pb-pct-'+idx);
        if (cur) cur.textContent = idx < 4 ? '…' : '100%';
      }, delay);
    });

    // Go to review
    setTimeout(function(){
      clearInterval(etaTimer);
      stepEls[4].className='pb-parse-step done';
      var last = document.getElementById('pb-pct-4');
      if (last) last.textContent='100%';
      pb_goStage('review');
    }, total);
  }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
