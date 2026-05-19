<?php
$pageTitle = 'DiaFitus — Personalized Fitness Coaching for People with Diabetes';
$pageDescription = 'A diabetes-aware fitness program built around your blood sugar, your goals and your schedule.';
$bodyClass = 'landing';
require __DIR__ . '/includes/header.php';
?>
  <header class="nav">
    <div class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name">DiaFitus</span>
    </div>
    <nav>
      <a href="#how">How it works</a>
      <a href="#why">Why DiaFitus</a>
      <a href="#reviews">Reviews</a>
      <a href="#faq">FAQ</a>
    </nav>
    <a href="questionnaire.php" class="btn btn-ghost">Start now</a>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <span class="pill">Built for Type 1, Type 2 &amp; Pre-diabetes</span>
      <h1>Fitness coaching designed around your <span class="accent">diabetes</span>.</h1>
      <p class="lede">Answer a short health questionnaire and get a personalized training plan,
      a nutrition guide, and direct chat access to coaches and doctors — all built around your
      blood sugar, your goals, and your schedule.</p>
      <a href="questionnaire.php" class="btn btn-primary btn-lg">Take the 2-minute assessment →</a>
      <div class="hero-meta">
        <div><strong>20k+</strong><span>people coached</span></div>
        <div><strong>4.9 / 5</strong><span>from 3,400+ reviews</span></div>
        <div><strong>24 / 7</strong><span>coach &amp; doctor chat</span></div>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>
    </div>
  </section>

  <section id="how" class="section">
    <h2>How DiaFitus works</h2>
    <div class="grid-3">
      <div class="card">
        <div class="step-num">01</div>
        <h3>Answer the assessment</h3>
        <p>Tell us about your diabetes type, current routine, goals and any side effects you're experiencing.</p>
      </div>
      <div class="card">
        <div class="step-num">02</div>
        <h3>Get your plan</h3>
        <p>Receive a personalized training program — gym or at home — plus a nutrition PDF tailored to your needs.</p>
      </div>
      <div class="card">
        <div class="step-num">03</div>
        <h3>Chat with coaches &amp; doctors</h3>
        <p>Get unlimited access to certified coaches and licensed doctors through our private Telegram channel.</p>
      </div>
    </div>
  </section>

  <section id="why" class="section dark">
    <h2>Why people choose DiaFitus</h2>
    <div class="grid-4">
      <div class="feat"><div class="feat-icon">❤️</div><h4>Glucose-aware training</h4><p>Workouts that respect your blood sugar response.</p></div>
      <div class="feat"><div class="feat-icon">🥗</div><h4>Nutrition guide</h4><p>A clear PDF of what to eat — before, during, after.</p></div>
      <div class="feat"><div class="feat-icon">💬</div><h4>Real human support</h4><p>Coaches and doctors reply directly on Telegram.</p></div>
      <div class="feat"><div class="feat-icon">📈</div><h4>Track everything</h4><p>Log workouts, meals, glucose, soreness in one place.</p></div>
    </div>
  </section>

  <section id="reviews" class="section">
    <h2>Loved by thousands living with diabetes</h2>
    <p class="lede" style="margin-bottom:2rem">Real reviews from real members. 4.9 average from 3,400+ ratings.</p>
    <div class="grid-3">
      <?php
      $reviews = [
        ['Marcus T.',  'Type 2',        'My A1C dropped from 8.1 to 6.4 in four months. The coaches actually understand diabetes — they don\'t just throw a generic workout at you.'],
        ['Lena R.',    'Type 1',        'Finally a program that doesn\'t crash my blood sugar. The Telegram chat with the doctor on standby is gold.'],
        ['David P.',   'Pre-diabetes',  'Lost 12 kg in six months and my doctor took me off two medications. I\'d pay much more for what I\'ve gotten.'],
        ['Aisha K.',   'Type 2',        'I always thought lifting weights wasn\'t for diabetics. My coach proved me wrong — I feel stronger than I did in my 30s.'],
        ['Tom B.',     'Type 1',        'The nutrition PDF alone is worth the subscription. Clear, no fluff, exactly what to eat around training.'],
        ['Priya N.',   'Gestational',   'They built me a safe routine during pregnancy. Doctor approved it within a day. So reassuring.'],
        ['Carlos M.',  'Type 2',        'I\'m 58 and have never been more active. The plan grew with me — started with 15-minute walks and now I\'m at the gym 4x a week.'],
        ['Hannah G.',  'Type 1',        'Hypos used to scare me away from cardio. With their pre-workout fueling guide it\'s been months without one.'],
        ['Yusuf A.',   'Pre-diabetes',  'My fasting glucose went from 118 to 92. The weekly check-ins keep me honest.'],
        ['Megan F.',   'Type 2',        'Worth every cent. My energy in the afternoons came back within three weeks.'],
        ['Rajiv S.',   'Type 2',        'The home program needs zero equipment. Game-changer for someone who hates gyms.'],
        ['Olivia W.',  'Type 1',        '24/7 access to a doctor on Telegram is unreal. They reply faster than my own clinic.'],
      ];
      foreach ($reviews as $r): [$name, $tag, $text] = $r; ?>
        <div class="testi">
          <div class="stars">★★★★★</div>
          <p><?= e($text) ?></p>
          <span class="who">— <?= e($name) ?> · <?= e($tag) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="faq" class="section">
    <h2>Frequently asked</h2>
    <details><summary>Is DiaFitus safe for Type 1 diabetes?</summary><p>Every program is reviewed by our medical team and adapted to your insulin schedule and glucose response. That said, DiaFitus is a coaching service, not a medical provider — always confirm any changes with your treating physician.</p></details>
    <details><summary>Do I need a gym membership?</summary><p>No. You choose gym or at-home (or a mix) during the assessment, and we build the plan around that.</p></details>
    <details><summary>Can I cancel any time?</summary><p>Yes — there's no lock-in. Cancel from your dashboard in one click.</p></details>
    <details><summary>How fast do I get my plan?</summary><p>Within 24 hours of finishing the assessment, delivered through your dashboard and Telegram.</p></details>
    <details><summary>Is this medical advice?</summary><p>No. DiaFitus provides general fitness and lifestyle suggestions only. We are not your doctor. Always consult a licensed medical professional before changing your exercise, diet, or medication.</p></details>
  </section>

  <section class="cta">
    <h2>Ready to train smarter with diabetes?</h2>
    <p>Take the assessment now and see your personalized plan in minutes.</p>
    <a href="questionnaire.php" class="btn btn-primary btn-lg">Start the assessment →</a>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
