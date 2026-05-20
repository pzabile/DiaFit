<?php
$pageTitle = 'Your DiaFitus Assessment';
$bodyClass = 'quiz';
require __DIR__ . '/includes/header.php';
$preGender = $_GET['gender'] ?? '';
?>
  <header class="quiz-header">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </a>
    <div class="progress-wrap">
      <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
      <span class="progress-text" id="progressText">Step 1</span>
    </div>
  </header>

  <main class="quiz-main" id="quizMain">

    <!-- Diabetes type -->
    <section class="step active" data-step="1" data-key="diabetes_type">
      <p class="kicker">A few questions to build your plan</p>
      <h1>Which type of diabetes do you have?</h1>
      <div class="options">
        <button class="option" data-value="type_1"><span class="opt-emoji">🩸</span><span>Type 1</span></button>
        <button class="option" data-value="type_2"><span class="opt-emoji">🧬</span><span>Type 2</span></button>
        <button class="option" data-value="pre_diabetes"><span class="opt-emoji">⚠️</span><span>Pre-diabetes</span></button>
        <button class="option" data-value="gestational"><span class="opt-emoji">🤰</span><span>Gestational</span></button>
        <button class="option" data-value="lada"><span class="opt-emoji">🔬</span><span>LADA / other</span></button>
        <button class="option" data-value="not_sure"><span class="opt-emoji">❓</span><span>I'm not sure</span></button>
      </div>
    </section>

    <!-- Years since diagnosis -->
    <section class="step" data-step="2" data-key="years_diagnosed">
      <h1>How long ago were you diagnosed?</h1>
      <p class="sub">Newer diagnoses tend to have less stable control — we adjust intensity accordingly.</p>
      <div class="options">
        <button class="option" data-value="<1"><span class="opt-emoji">🆕</span><span>Less than a year ago</span></button>
        <button class="option" data-value="1-3"><span class="opt-emoji">📅</span><span>1–3 years</span></button>
        <button class="option" data-value="3-10"><span class="opt-emoji">📆</span><span>3–10 years</span></button>
        <button class="option" data-value="10+"><span class="opt-emoji">🗓️</span><span>10+ years</span></button>
      </div>
    </section>

    <?php if (!in_array($preGender, ['male', 'female'], true)): ?>
    <section class="step" data-step="3" data-key="gender">
      <h1>What is your gender?</h1>
      <p class="sub">We use this to calibrate calorie and intensity targets.</p>
      <div class="options two">
        <button class="option big" data-value="male"><span class="opt-emoji">👨</span><span>Male</span></button>
        <button class="option big" data-value="female"><span class="opt-emoji">👩</span><span>Female</span></button>
        <button class="option big" data-value="other"><span class="opt-emoji">🧑</span><span>Other / Prefer not to say</span></button>
      </div>
    </section>
    <?php endif; ?>

    <!-- Age -->
    <section class="step" data-step="4" data-key="age" data-type="input">
      <h1>How old are you?</h1>
      <p class="sub">Age helps us set safe heart-rate zones.</p>
      <input type="number" min="10" max="100" class="text-input" id="ageInput" placeholder="e.g. 42" />
      <button class="btn btn-primary btn-lg next-btn" data-target="ageInput">Continue →</button>
    </section>

    <!-- Height -->
    <section class="step" data-step="5" data-key="height_cm" data-type="input">
      <h1>What is your height?</h1>
      <p class="sub">In centimeters. Used together with weight to calibrate your plan.</p>
      <input type="number" min="120" max="230" class="text-input" id="heightInput" placeholder="e.g. 175 cm" />
      <button class="btn btn-primary btn-lg next-btn" data-target="heightInput">Continue →</button>
    </section>

    <!-- Weight -->
    <section class="step" data-step="6" data-key="weight" data-type="input">
      <h1>What is your weight?</h1>
      <p class="sub">In kilograms.</p>
      <input type="number" min="30" max="300" class="text-input" id="weightInput" placeholder="e.g. 85 kg" />
      <button class="btn btn-primary btn-lg next-btn" data-target="weightInput">Continue →</button>
    </section>

    <!-- Typical fasting glucose -->
    <section class="step" data-step="7" data-key="fasting_glucose">
      <h1>What is your typical fasting blood glucose?</h1>
      <p class="sub">If you don't measure, pick the closest range. Use the bottom option if you have a recent HbA1c instead.</p>
      <div class="options">
        <button class="option" data-value="<100"><span class="opt-emoji">🟢</span><span>Under 100 mg/dL (5.5 mmol/L)</span></button>
        <button class="option" data-value="100-140"><span class="opt-emoji">🟡</span><span>100–140 mg/dL (5.5–7.8)</span></button>
        <button class="option" data-value="140-200"><span class="opt-emoji">🟠</span><span>140–200 mg/dL (7.8–11.1)</span></button>
        <button class="option" data-value=">200"><span class="opt-emoji">🔴</span><span>Above 200 mg/dL (over 11.1)</span></button>
        <button class="option" data-value="unknown"><span class="opt-emoji">❓</span><span>I don't know</span></button>
      </div>
    </section>

    <!-- On insulin? -->
    <section class="step" data-step="8" data-key="on_insulin">
      <h1>Are you on insulin?</h1>
      <p class="sub">This is the single biggest factor in keeping your workouts safe.</p>
      <div class="options two">
        <button class="option big" data-value="basal_bolus"><span class="opt-emoji">💉</span><span>Yes — basal &amp; bolus</span></button>
        <button class="option big" data-value="basal_only"><span class="opt-emoji">💉</span><span>Yes — basal only</span></button>
        <button class="option big" data-value="pump"><span class="opt-emoji">⚙️</span><span>Yes — pump</span></button>
        <button class="option big" data-value="no"><span class="opt-emoji">🚫</span><span>No</span></button>
      </div>
    </section>

    <!-- Other diabetes medications -->
    <section class="step" data-step="9" data-key="meds" data-type="multi">
      <h1>Other diabetes medications?</h1>
      <p class="sub">Select all that apply.</p>
      <div class="options multi">
        <button class="option" data-value="metformin"><span>Metformin</span></button>
        <button class="option" data-value="sulfonylurea"><span>Sulfonylurea (glipizide, glyburide…)</span></button>
        <button class="option" data-value="sglt2"><span>SGLT2 inhibitor</span></button>
        <button class="option" data-value="glp1"><span>GLP-1 (Ozempic, Mounjaro…)</span></button>
        <button class="option" data-value="dpp4"><span>DPP-4 inhibitor</span></button>
        <button class="option" data-value="beta_blocker"><span>Beta-blocker</span></button>
        <button class="option" data-value="bp_meds"><span>Blood-pressure meds</span></button>
        <button class="option" data-value="statin"><span>Statin / cholesterol meds</span></button>
        <button class="option" data-value="none"><span>None of the above</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Foot condition -->
    <section class="step" data-step="10" data-key="foot_condition" data-type="multi">
      <h1>How are your feet?</h1>
      <p class="sub">Select all that apply. This determines whether running and high-impact cardio are safe.</p>
      <div class="options multi">
        <button class="option" data-value="no_issues"><span>No issues</span></button>
        <button class="option" data-value="calluses"><span>Calluses</span></button>
        <button class="option" data-value="numbness"><span>Numbness / tingling</span></button>
        <button class="option" data-value="past_ulcer"><span>Past ulcer (healed)</span></button>
        <button class="option" data-value="current_ulcer"><span>Current ulcer or open wound</span></button>
        <button class="option" data-value="infection"><span>Recent infection</span></button>
        <button class="option" data-value="amputation"><span>Past amputation</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Hypoglycemia history -->
    <section class="step" data-step="11" data-key="hypo_history">
      <h1>Have you had hypoglycemia (low blood sugar)?</h1>
      <div class="options">
        <button class="option" data-value="never"><span class="opt-emoji">🟢</span><span>Never</span></button>
        <button class="option" data-value="mild"><span class="opt-emoji">🟡</span><span>Yes — mild, handled myself</span></button>
        <button class="option" data-value="help"><span class="opt-emoji">🟠</span><span>Yes — needed help from someone</span></button>
        <button class="option" data-value="hospital"><span class="opt-emoji">🔴</span><span>Yes — required ER / hospital</span></button>
      </div>
    </section>

    <!-- Side effects -->
    <section class="step" data-step="12" data-key="side_effects" data-type="multi">
      <h1>Any of these symptoms right now?</h1>
      <p class="sub">Select all that apply.</p>
      <div class="options multi">
        <button class="option" data-value="fatigue"><span>Fatigue</span></button>
        <button class="option" data-value="vision"><span>Blurred vision</span></button>
        <button class="option" data-value="slow_healing"><span>Slow wound healing</span></button>
        <button class="option" data-value="frequent_thirst"><span>Frequent thirst</span></button>
        <button class="option" data-value="frequent_urination"><span>Frequent urination</span></button>
        <button class="option" data-value="weight_gain"><span>Unwanted weight gain</span></button>
        <button class="option" data-value="weight_loss"><span>Unexpected weight loss</span></button>
        <button class="option" data-value="none"><span>None of the above</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Other complications -->
    <section class="step" data-step="13" data-key="complications" data-type="multi">
      <h1>Any of these diagnoses?</h1>
      <p class="sub">Select all that apply — we adjust the plan to keep you safe.</p>
      <div class="options multi">
        <button class="option" data-value="hypertension"><span>High blood pressure</span></button>
        <button class="option" data-value="cv_disease"><span>Heart disease / past stent / MI</span></button>
        <button class="option" data-value="heart_failure"><span>Heart failure</span></button>
        <button class="option" data-value="arrhythmia"><span>Arrhythmia</span></button>
        <button class="option" data-value="retinopathy"><span>Retinopathy</span></button>
        <button class="option" data-value="kidney"><span>Kidney disease</span></button>
        <button class="option" data-value="autonomic"><span>Autonomic neuropathy</span></button>
        <button class="option" data-value="none"><span>None of the above</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Exercise history -->
    <section class="step" data-step="14" data-key="exercise_history">
      <h1>Have you exercised regularly in the past?</h1>
      <div class="options">
        <button class="option" data-value="never"><span class="opt-emoji">🛋️</span><span>No, I'm a complete beginner</span></button>
        <button class="option" data-value="long_ago"><span class="opt-emoji">🕰️</span><span>Yes, but a long time ago</span></button>
        <button class="option" data-value="on_and_off"><span class="opt-emoji">🔁</span><span>On and off over the years</span></button>
        <button class="option" data-value="currently"><span class="opt-emoji">🏃</span><span>I currently train regularly</span></button>
      </div>
    </section>

    <!-- Motivation -->
    <section class="step" data-step="15" data-key="motivation">
      <h1>Why did you decide to start now?</h1>
      <div class="options">
        <button class="option" data-value="control_glucose"><span class="opt-emoji">📉</span><span>Improve blood sugar control</span></button>
        <button class="option" data-value="lose_weight"><span class="opt-emoji">⚖️</span><span>Lose weight</span></button>
        <button class="option" data-value="more_energy"><span class="opt-emoji">⚡</span><span>Have more energy</span></button>
        <button class="option" data-value="build_muscle"><span class="opt-emoji">💪</span><span>Build muscle &amp; strength</span></button>
        <button class="option" data-value="reduce_meds"><span class="opt-emoji">💊</span><span>Reduce medication dependency</span></button>
        <button class="option" data-value="feel_better"><span class="opt-emoji">😊</span><span>Just feel better day-to-day</span></button>
      </div>
    </section>

    <!-- Goals -->
    <section class="step" data-step="16" data-key="goals" data-type="multi">
      <h1>What are your main goals?</h1>
      <p class="sub">Pick up to 3.</p>
      <div class="options multi">
        <button class="option" data-value="lower_a1c"><span>Lower A1C</span></button>
        <button class="option" data-value="fat_loss"><span>Fat loss</span></button>
        <button class="option" data-value="muscle_gain"><span>Build muscle</span></button>
        <button class="option" data-value="cardio"><span>Better cardio</span></button>
        <button class="option" data-value="flexibility"><span>Mobility &amp; flexibility</span></button>
        <button class="option" data-value="mental"><span>Mental health</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Location -->
    <section class="step" data-step="17" data-key="location">
      <h1>Where do you want to train?</h1>
      <div class="options two">
        <button class="option big" data-value="gym"><span class="opt-emoji">🏋️</span><span>Gym</span></button>
        <button class="option big" data-value="home"><span class="opt-emoji">🏠</span><span>At home</span></button>
        <button class="option big" data-value="both"><span class="opt-emoji">🔀</span><span>A mix of both</span></button>
        <button class="option big" data-value="outdoors"><span class="opt-emoji">🌳</span><span>Outdoors</span></button>
      </div>
    </section>

    <!-- Equipment -->
    <section class="step" data-step="18" data-key="equipment" data-type="multi">
      <h1>What equipment do you have access to?</h1>
      <p class="sub">Select all that apply. Helps us choose exercises that actually fit your setup.</p>
      <div class="options multi">
        <button class="option" data-value="none"><span>None / bodyweight</span></button>
        <button class="option" data-value="dumbbells"><span>Dumbbells</span></button>
        <button class="option" data-value="kettlebell"><span>Kettlebell(s)</span></button>
        <button class="option" data-value="bands"><span>Resistance bands</span></button>
        <button class="option" data-value="pullup"><span>Pull-up bar</span></button>
        <button class="option" data-value="bench"><span>Bench</span></button>
        <button class="option" data-value="treadmill"><span>Treadmill</span></button>
        <button class="option" data-value="bike"><span>Stationary bike / spin</span></button>
        <button class="option" data-value="rower"><span>Rower</span></button>
        <button class="option" data-value="full_gym"><span>Full gym access</span></button>
      </div>
      <button class="btn btn-primary btn-lg next-btn multi-next">Continue →</button>
    </section>

    <!-- Days per week -->
    <section class="step" data-step="19" data-key="days_per_week">
      <h1>How many days per week do you want to train?</h1>
      <div class="options">
        <button class="option" data-value="2"><span class="opt-emoji">2️⃣</span><span>2 days</span></button>
        <button class="option" data-value="3"><span class="opt-emoji">3️⃣</span><span>3 days</span></button>
        <button class="option" data-value="4"><span class="opt-emoji">4️⃣</span><span>4 days</span></button>
        <button class="option" data-value="5"><span class="opt-emoji">5️⃣</span><span>5 days</span></button>
        <button class="option" data-value="6+"><span class="opt-emoji">🔥</span><span>6 or more</span></button>
      </div>
    </section>

    <!-- Minutes per day -->
    <section class="step" data-step="20" data-key="minutes_per_day">
      <h1>How many minutes per day can you commit?</h1>
      <div class="options">
        <button class="option" data-value="15"><span class="opt-emoji">⏱️</span><span>15 minutes</span></button>
        <button class="option" data-value="30"><span class="opt-emoji">⏱️</span><span>30 minutes</span></button>
        <button class="option" data-value="45"><span class="opt-emoji">⏱️</span><span>45 minutes</span></button>
        <button class="option" data-value="60"><span class="opt-emoji">⏱️</span><span>60 minutes</span></button>
        <button class="option" data-value="90+"><span class="opt-emoji">⏱️</span><span>90+ minutes</span></button>
      </div>
    </section>

    <!-- Sleep -->
    <section class="step" data-step="21" data-key="sleep_hours">
      <h1>How many hours do you usually sleep?</h1>
      <p class="sub">Sleep is closely tied to glucose control. We use it to scale workout volume.</p>
      <div class="options">
        <button class="option" data-value="<5"><span class="opt-emoji">😴</span><span>Less than 5 hours</span></button>
        <button class="option" data-value="5-6"><span class="opt-emoji">😪</span><span>5–6 hours</span></button>
        <button class="option" data-value="6-7"><span class="opt-emoji">🙂</span><span>6–7 hours</span></button>
        <button class="option" data-value="7-8"><span class="opt-emoji">😌</span><span>7–8 hours</span></button>
        <button class="option" data-value="8+"><span class="opt-emoji">💤</span><span>8+ hours</span></button>
      </div>
    </section>

    <!-- Diet -->
    <section class="step" data-step="22" data-key="diet_style">
      <h1>How do you usually eat?</h1>
      <p class="sub">We use this to choose foods you'll actually eat — not to push a diet on you.</p>
      <div class="options">
        <button class="option" data-value="omnivore"><span class="opt-emoji">🍽️</span><span>Omnivore (anything)</span></button>
        <button class="option" data-value="mediterranean"><span class="opt-emoji">🥗</span><span>Mediterranean</span></button>
        <button class="option" data-value="low_carb"><span class="opt-emoji">🥩</span><span>Low-carb</span></button>
        <button class="option" data-value="keto"><span class="opt-emoji">🥑</span><span>Keto</span></button>
        <button class="option" data-value="vegetarian"><span class="opt-emoji">🥦</span><span>Vegetarian</span></button>
        <button class="option" data-value="vegan"><span class="opt-emoji">🌱</span><span>Vegan</span></button>
        <button class="option" data-value="other"><span class="opt-emoji">❓</span><span>Other / I don't follow a style</span></button>
      </div>
    </section>

    <!-- Doctor recommended -->
    <section class="step" data-step="23" data-key="doctor_recommended">
      <h1>Did your doctor recommend exercise?</h1>
      <div class="options two">
        <button class="option big" data-value="yes"><span class="opt-emoji">✅</span><span>Yes</span></button>
        <button class="option big" data-value="no"><span class="opt-emoji">❌</span><span>No</span></button>
        <button class="option big" data-value="havent_asked"><span class="opt-emoji">🤷</span><span>Haven't asked</span></button>
      </div>
    </section>

    <!-- Email -->
    <section class="step" data-step="24" data-key="email" data-type="input">
      <h1>Where should we send your plan?</h1>
      <p class="sub">Enter your email — we'll prepare your personalized program.</p>
      <input type="email" class="text-input" id="emailInput" placeholder="you@example.com" />
      <button class="btn btn-primary btn-lg next-btn" data-target="emailInput">Continue →</button>
    </section>

    <!-- Disclaimer / consent -->
    <section class="step" data-step="25" data-key="consent" data-type="consent">
      <h1>One last thing — please read carefully.</h1>
      <p class="sub">DiaFitus is fitness and lifestyle coaching. We are <strong>not</strong> a medical service.</p>
      <div class="consent-box">
        <label class="check">
          <input type="checkbox" id="consent1" />
          <span>I understand DiaFitus provides general fitness and nutrition <strong>suggestions only</strong>, not medical advice, and is not a substitute for consulting my doctor.</span>
        </label>
        <label class="check">
          <input type="checkbox" id="consent2" />
          <span>I confirm the information I have provided is accurate to the best of my knowledge.</span>
        </label>
        <label class="check">
          <input type="checkbox" id="consent3" />
          <span>I will check with my physician before starting any new exercise or nutrition program, especially regarding my diabetes and any medications.</span>
        </label>
      </div>
      <button class="btn btn-primary btn-lg next-btn" id="finishBtn" disabled>See my plan →</button>
    </section>

    <section class="step loading-step" data-step="loading">
      <div class="loader-wrap">
        <div class="loader"></div>
        <h2>Building your personalized program…</h2>
        <ul class="loader-list">
          <li class="done">Analyzing your diabetes profile</li>
          <li class="done">Matching glucose-safe exercises</li>
          <li>Calibrating nutrition guide</li>
          <li>Assigning your coach &amp; doctor</li>
        </ul>
      </div>
    </section>
  </main>

  <script>
    window.QUIZ_SUBMIT_URL = '/submit_quiz';
    window.QUIZ_NEXT_URL   = '/results';
    <?php if (in_array($preGender, ['male', 'female'], true)): ?>
    window.QUIZ_PRESET = { gender: <?= json_encode($preGender) ?> };
    <?php endif; ?>
  </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
