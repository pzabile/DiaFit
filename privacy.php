<?php
$pageTitle = 'Privacy Policy — DiaFitus';
$bodyClass = 'legal';
require __DIR__ . '/includes/header.php';
$company = cfg('company_name');
$brand   = cfg('brand_name');
$support = cfg('support_email');
?>
  <header class="nav slim">
    <a href="index.php" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name"><?= e($brand) ?></span>
    </a>
  </header>

  <main class="legal-main">
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: <?= date('F j, Y') ?></p>

    <p>This Privacy Policy explains how <?= e($company) ?> ("we", "us") collects, uses and protects
    your information when you use the <?= e($brand) ?> website and services (the "Service"). By
    using the Service you agree to this Policy.</p>

    <h2>1. Information we collect</h2>
    <ul>
      <li><strong>Questionnaire answers</strong>: diabetes type, gender, age, weight, exercise
      history, goals, training preferences and reported side effects.</li>
      <li><strong>Account details</strong>: first name, email address and phone number.</li>
      <li><strong>Payment information</strong>: processed directly by Stripe. We receive only the
      last four digits, card brand, billing country and subscription status — never your full card
      number.</li>
      <li><strong>Service usage</strong>: dashboard log entries (workouts, food, blood-sugar
      readings, soreness, notes), messages with our coaches, and basic technical data such as IP
      address, browser type and pages visited.</li>
    </ul>

    <h2>2. How we use your information</h2>
    <p>We use your information to: (a) build and deliver your personalized fitness program;
    (b) operate the dashboard and the Telegram support channel; (c) process payments and prevent
    fraud; (d) send service-related emails (welcome message, billing receipts, plan updates);
    (e) improve our programs and content; (f) comply with legal obligations.</p>

    <h2>3. Sensitive health information</h2>
    <p>You voluntarily share information relating to your diabetes and overall health. We treat this
    information confidentially and use it solely to deliver the Service. We do <strong>not</strong>
    sell health information. We do <strong>not</strong> share it with advertisers. Access is
    restricted to staff and contracted coaches/medical reviewers who need it to serve you.</p>

    <h2>4. Sharing</h2>
    <p>We share your information only with:</p>
    <ul>
      <li><strong>Stripe</strong> — payment processing.</li>
      <li><strong>Telegram</strong> — all 24/7 support communication happens through the Telegram
      messaging app. When you message your coach there, your messages are processed by Telegram
      under its own privacy policy. We use Telegram both to receive notifications about your
      account and to chat with members.</li>
      <li><strong>Email provider / hosting</strong> — to deliver the website and emails.</li>
      <li><strong>Legal authorities</strong> — when required by law, court order or to protect
      our rights.</li>
    </ul>
    <p>We do not sell or rent personal information to third parties.</p>

    <h2>5. Cookies</h2>
    <p>We use a small number of cookies (or equivalent local storage) to maintain your session,
    remember your questionnaire answers between pages, and measure basic site performance. You can
    block cookies through your browser settings; some features may stop working if you do.</p>

    <h2>6. Data retention</h2>
    <p>We retain your account and program data while your subscription is active and for up to 24
    months afterwards, unless a longer period is required for legal, tax or accounting reasons.
    You can request deletion at any time (see Section 8).</p>

    <h2>7. Security</h2>
    <p>We protect your data using industry-standard measures including TLS encryption in transit,
    encrypted backups, restricted staff access, and strong password requirements. No method of
    transmission or storage is 100% secure, however, and we cannot guarantee absolute security.</p>

    <h2>8. Your rights</h2>
    <p>Depending on where you live, you may have the right to: access the personal data we hold
    about you; correct or update it; request deletion; restrict or object to processing; receive a
    portable copy; and lodge a complaint with your local data-protection authority. To exercise any
    of these rights email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>.</p>

    <h2>9. Children</h2>
    <p>The Service is intended for adults aged 18 and over. We do not knowingly collect personal
    information from children. If you believe a child has provided us information, contact us and we
    will delete it.</p>

    <h2>10. International transfers</h2>
    <p>Your information may be processed in countries outside your own. We rely on appropriate
    safeguards (such as standard contractual clauses) when transferring data internationally.</p>

    <h2>11. Changes</h2>
    <p>We may update this Policy from time to time. Material changes will be communicated by email
    or via a notice on the website.</p>

    <h2>12. Health-information disclaimer</h2>
    <p>The Service is not a medical service. The data you log helps your coach personalize your
    program but is not used for diagnosis or treatment. See our <a href="terms.php">Terms &amp;
    Conditions</a> for the full medical disclaimer and limitation of liability.</p>

    <h2>13. Contact</h2>
    <p><?= e($company) ?> — <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a></p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
