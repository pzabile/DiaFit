// ---------- Shared: timers across offer / signup pages ----------
(function () {
  const TIMER_KEY = 'diafitus_offer_deadline';
  const TIMER_LENGTH_MS = 15 * 60 * 1000;
  const timerEls = document.querySelectorAll('#timer, #timer2, #timer3');
  if (timerEls.length === 0) return;

  let deadline = parseInt(sessionStorage.getItem(TIMER_KEY) || '0', 10);
  if (!deadline || deadline < Date.now()) {
    deadline = Date.now() + TIMER_LENGTH_MS;
    sessionStorage.setItem(TIMER_KEY, String(deadline));
  }

  function tick() {
    const remaining = Math.max(0, deadline - Date.now());
    const m = Math.floor(remaining / 60000);
    const s = Math.floor((remaining % 60000) / 1000);
    const text = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    timerEls.forEach(el => { el.textContent = text; });
    if (remaining === 0) {
      sessionStorage.removeItem(TIMER_KEY);
    }
  }
  tick();
  setInterval(tick, 1000);
})();

// ---------- Quiz logic ----------
(function () {
  const main = document.getElementById('quizMain');
  if (!main) return;

  const SUBMIT_URL = window.QUIZ_SUBMIT_URL || 'submit_quiz.php';
  const NEXT_URL   = window.QUIZ_NEXT_URL   || 'offer.php';

  const steps = Array.from(main.querySelectorAll('.step'));
  const total = steps.filter(s => s.dataset.step !== 'loading').length;
  const bar = document.getElementById('progressBar');
  const txt = document.getElementById('progressText');
  let current = 0;
  const answers = {};

  function show(i) {
    steps.forEach(s => s.classList.remove('active'));
    steps[i].classList.add('active');
    const stepData = steps[i].dataset.step;
    if (stepData === 'loading') {
      bar.style.width = '100%';
      txt.textContent = 'Almost there…';
    } else {
      const num = parseInt(stepData, 10);
      bar.style.width = `${(num / total) * 100}%`;
      txt.textContent = `Step ${num} of ${total}`;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function next() {
    current = Math.min(current + 1, steps.length - 1);
    show(current);
    if (steps[current].dataset.step === 'loading') runLoading();
  }

  function runLoading() {
    const items = steps[current].querySelectorAll('.loader-list li');
    items.forEach(li => li.classList.remove('done'));
    items[0].classList.add('done');
    items[1].classList.add('done');

    // Submit answers to backend in parallel with the animation.
    const submission = fetch(SUBMIT_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(answers),
      credentials: 'same-origin',
    }).then(r => r.ok ? r.json() : Promise.reject(new Error('Submit failed')))
      .catch(err => { console.error(err); return { redirect: NEXT_URL }; });

    let i = 1;
    const t = setInterval(() => {
      i++;
      if (items[i]) items[i].classList.add('done');
      if (i >= items.length - 1) {
        clearInterval(t);
        submission.then(res => {
          setTimeout(() => { window.location.href = (res && res.redirect) || NEXT_URL; }, 500);
        });
      }
    }, 700);
  }

  steps.forEach(step => {
    const key = step.dataset.key;
    const type = step.dataset.type;
    if (!key) return;

    if (type === 'multi') {
      const opts = step.querySelectorAll('.option');
      opts.forEach(o => {
        o.addEventListener('click', () => o.classList.toggle('selected'));
      });
      const btn = step.querySelector('.multi-next');
      btn.addEventListener('click', () => {
        const sel = Array.from(step.querySelectorAll('.option.selected')).map(o => o.dataset.value);
        if (sel.length === 0) return;
        answers[key] = sel;
        next();
      });
    } else if (type === 'input') {
      const btn = step.querySelector('.next-btn');
      const input = step.querySelector('.text-input');
      btn.addEventListener('click', () => {
        const val = (input.value || '').trim();
        if (!val) { input.focus(); input.style.borderColor = 'var(--danger)'; return; }
        if (input.type === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(val)) {
          input.style.borderColor = 'var(--danger)'; return;
        }
        answers[key] = val;
        next();
      });
      input.addEventListener('keydown', e => { if (e.key === 'Enter') btn.click(); });
    } else {
      step.querySelectorAll('.option').forEach(opt => {
        opt.addEventListener('click', () => {
          step.querySelectorAll('.option').forEach(o => o.classList.remove('selected'));
          opt.classList.add('selected');
          answers[key] = opt.dataset.value;
          setTimeout(next, 220);
        });
      });
    }
  });

  show(0);
})();

// ---------- Dashboard logging (client-side) ----------
(function () {
  if (!document.body.classList.contains('dashboard')) return;

  const dateInput = document.querySelector('input[name="date"]');
  if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);

  const list = document.getElementById('logList');
  const form = document.getElementById('logForm');
  const STORAGE_KEY = 'diafitus_logs';

  function loadLogs() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
  }
  function saveLogs(logs) { localStorage.setItem(STORAGE_KEY, JSON.stringify(logs)); }

  function render() {
    const logs = loadLogs();
    list.innerHTML = logs.length === 0
      ? '<p class="muted">No logs yet — your entries will appear here.</p>'
      : logs.slice().reverse().map(l => `
        <div class="log-entry">
          <h4>${l.date} — felt ${l.feeling}, trained: ${l.trained} ${l.where ? '('+l.where+')' : ''}</h4>
          <small>Glucose: ${l.bsBefore || '—'} → ${l.bsAfter || '—'} (${l.bsTrend}). Soreness: ${l.soreness}/10.</small>
          ${l.workout ? `<p style="margin:.5rem 0 0">${l.workout}</p>` : ''}
          ${l.notes ? `<p class="muted" style="margin:.25rem 0 0;font-size:.9rem">${l.notes}</p>` : ''}
        </div>
      `).join('');

    document.getElementById('entries').textContent = logs.length;
    document.getElementById('streak').textContent = Math.min(logs.length, 30);
    const last = logs[logs.length - 1];
    if (last && last.bsAfter) document.getElementById('latestGlucose').textContent = last.bsAfter;
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form);
    const entry = Object.fromEntries(fd.entries());
    const logs = loadLogs();
    logs.push(entry);
    saveLogs(logs);
    form.reset();
    if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);
    render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  render();
})();
