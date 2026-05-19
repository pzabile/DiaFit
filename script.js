// ---------- Shared: timers across offer / signup pages ----------
(function () {
  const TIMER_KEY = 'diafit_offer_deadline';
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
    let i = 0;
    items.forEach(li => li.classList.remove('done'));
    items[0].classList.add('done');
    items[1].classList.add('done');
    const t = setInterval(() => {
      i++;
      if (items[i + 1]) items[i + 1].classList.add('done');
      if (i + 1 >= items.length - 1) {
        clearInterval(t);
        setTimeout(() => {
          try { localStorage.setItem('diafit_answers', JSON.stringify(answers)); } catch (e) {}
          window.location.href = 'offer.html';
        }, 700);
      }
    }, 700);
  }

  // single-choice options
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
        if (sel.length === 0) { btn.classList.add('shake'); return; }
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

// ---------- Offer page personalization ----------
(function () {
  if (!document.body.classList.contains('offer')) return;
  let a = {};
  try { a = JSON.parse(localStorage.getItem('diafit_answers') || '{}'); } catch (e) {}
  const loc = document.getElementById('planLocation');
  const days = document.getElementById('planDays');
  const locMap = { gym: 'at the gym', home: 'at home', both: 'gym + home', outdoors: 'outdoors' };
  if (loc && a.location) loc.textContent = locMap[a.location] || 'gym or home';
  if (days && a.days_per_week) days.textContent = `${a.days_per_week} days a week, ${a.minutes_per_day || 30} min/day`;
})();

// ---------- Signup form ----------
(function () {
  const form = document.getElementById('signupForm');
  if (!form) return;
  let a = {};
  try { a = JSON.parse(localStorage.getItem('diafit_answers') || '{}'); } catch (e) {}
  const emailField = document.getElementById('signupEmail');
  if (emailField && a.email) emailField.value = a.email;

  form.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form);
    const user = {
      firstName: fd.get('firstName'),
      email: fd.get('email'),
      phone: fd.get('phone'),
    };
    try { localStorage.setItem('diafit_user', JSON.stringify(user)); } catch (err) {}
    window.location.href = 'dashboard.html';
  });
})();

// ---------- Dashboard ----------
(function () {
  if (!document.body.classList.contains('dashboard')) return;

  let user = {};
  let answers = {};
  try { user = JSON.parse(localStorage.getItem('diafit_user') || '{}'); } catch (e) {}
  try { answers = JSON.parse(localStorage.getItem('diafit_answers') || '{}'); } catch (e) {}

  if (user.firstName) {
    document.getElementById('userName').textContent = user.firstName;
    document.getElementById('userNameH').textContent = user.firstName;
    document.querySelector('.avatar').textContent = user.firstName[0].toUpperCase();
  }
  if (user.email) document.getElementById('userEmail').textContent = user.email;

  // Today's workout from answers
  const todayEl = document.getElementById('todayWorkout');
  if (todayEl) {
    const days = answers.days_per_week || '3';
    const mins = answers.minutes_per_day || '30';
    const loc = answers.location || 'home';
    todayEl.textContent = `${mins}-min ${loc} session — ${days}x / week plan`;
  }

  // Pre-fill date
  const dateInput = document.querySelector('input[name="date"]');
  if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);

  const list = document.getElementById('logList');
  const form = document.getElementById('logForm');
  const STORAGE_KEY = 'diafit_logs';

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
