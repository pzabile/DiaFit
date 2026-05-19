<?php
$pageTitle = 'Terms & Conditions — DiaFitus';
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
    <h1>Terms &amp; Conditions</h1>
    <p class="muted">Last updated: <?= date('F j, Y') ?></p>

    <p>These Terms &amp; Conditions ("Terms") govern your use of the <?= e($brand) ?> website,
    application, content and subscription services (collectively, the "Service"), operated by
    <?= e($company) ?> ("we", "us", "our"). By accessing or using the Service you agree to these Terms.
    If you do not agree, do not use the Service.</p>

    <h2>1. Important medical disclaimer</h2>
    <p><strong><?= e($brand) ?> is not a medical provider.</strong> The Service provides general
    fitness, lifestyle, and nutrition <em>suggestions</em> only. The information, exercise programs,
    nutrition guides, coach communications and any other content delivered by the Service are for
    informational and educational purposes only and <strong>do not constitute medical advice,
    diagnosis or treatment</strong>.</p>

    <p>Diabetes is a serious medical condition. <strong>You must consult a licensed physician</strong>
    before starting any exercise, nutrition, or lifestyle program offered through the Service, and
    before changing or stopping any medication. The decision to follow any suggestion made by
    <?= e($brand) ?> is entirely your own. You are responsible for verifying any information with
    your treating doctor and for confirming its appropriateness for your individual condition.</p>

    <p>Coaches, support staff and any individuals communicating with you through Telegram or any
    other channel are not acting as your physician. Any reference to "doctors" describes general
    medical review of program templates; it does not establish a doctor–patient relationship between
    you and <?= e($company) ?> or any individual associated with <?= e($brand) ?>.</p>

    <p><strong>Call your physician or local emergency number immediately if you experience symptoms
    of severe hypoglycemia, hyperglycemia, chest pain, shortness of breath, fainting, or any other
    medical emergency.</strong> Do not rely on the Service in a medical emergency.</p>

    <h2>2. Eligibility</h2>
    <p>You must be at least 18 years old to use the Service. By using the Service you represent that
    you are of legal age and that any information you provide is accurate, current and complete.</p>

    <h2>3. Account &amp; subscription</h2>
    <p>The Service is offered on a recurring monthly subscription basis. The current price is shown
    at checkout. Subscriptions automatically renew each month at the then-current price unless you
    cancel before the next billing date. You can cancel any time from your dashboard or by emailing
    <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>. Cancellation stops future charges; it
    does not refund the current billing period except as set out in our refund policy.</p>

    <h2>4. Refund policy</h2>
    <p>We offer a 14-day money-back guarantee on your first month. Email
    <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> within 14 days of your first charge
    and we will issue a full refund. After 14 days, payments are non-refundable.</p>

    <h2>5. Payments</h2>
    <p>Payments are processed by Stripe. We do not store your full card details. By providing payment
    information you authorize <?= e($company) ?> (via Stripe) to charge the agreed amount each month
    until you cancel.</p>

    <h2>6. Acceptable use</h2>
    <p>You agree not to: (a) use the Service in any way that violates any applicable law;
    (b) attempt to interfere with, compromise or disable the Service; (c) reproduce, resell, sublicense
    or commercially exploit any part of the Service; (d) share your account credentials; (e) misrepresent
    your identity or health information; or (f) use the Service for any purpose other than your own
    personal, non-commercial use.</p>

    <h2>7. Intellectual property</h2>
    <p>All content provided through the Service — including the questionnaire, exercise programs,
    nutrition PDFs, written guides, brand marks and software — is owned by <?= e($company) ?> or its
    licensors and is protected by applicable intellectual-property laws. You receive a limited,
    revocable, non-transferable, non-exclusive license to use this content for personal use during
    your active subscription.</p>

    <h2>8. User submissions</h2>
    <p>You may submit information through the questionnaire, the dashboard logs, and any chat with
    our coaches ("Submissions"). You grant <?= e($company) ?> a worldwide, royalty-free license to
    use the Submissions in order to operate the Service, improve our programs, and contact you. You
    represent that you have the right to share any Submission you provide.</p>

    <h2>9. Limitation of liability — please read carefully</h2>
    <p><strong>To the fullest extent permitted by law, <?= e($company) ?>, its officers, employees,
    contractors, coaches, medical reviewers, affiliates and partners shall not be liable</strong> for
    any direct, indirect, incidental, special, consequential, exemplary or punitive damages, or for
    any loss of profits, revenue, data, health, well-being, fitness, body composition or other
    intangibles arising out of or related to:</p>
    <ul>
      <li>your access to or use of (or inability to use) the Service;</li>
      <li>any exercise, nutrition, lifestyle or other suggestion provided through the Service;</li>
      <li>any worsening, complication or side effect of diabetes or any other medical condition,
      including but not limited to hypoglycemia, hyperglycemia, injury, illness, hospitalization or
      death;</li>
      <li>any reliance on information obtained through the Service, including information from
      coaches, written guides or PDFs;</li>
      <li>any action you take or fail to take based on the Service.</li>
    </ul>
    <p>You acknowledge and accept that exercise carries inherent physical risk, that diabetes
    increases that risk, and that <strong>you assume all such risk</strong>. You are solely
    responsible for your health decisions. If your jurisdiction does not allow the exclusion of
    certain damages, our liability is limited to the maximum extent permitted, and in any case shall
    not exceed the amount you paid us in the three months preceding the claim.</p>

    <h2>10. Indemnification</h2>
    <p>You agree to indemnify, defend and hold harmless <?= e($company) ?> and its personnel from any
    claim, loss, liability, damage or expense (including reasonable attorneys' fees) arising out of
    (a) your use of the Service, (b) your breach of these Terms, or (c) your violation of any law or
    third-party right.</p>

    <h2>11. Modifications</h2>
    <p>We may modify these Terms at any time by posting the updated version on our website. Material
    changes will be communicated by email. Continued use after the effective date constitutes
    acceptance.</p>

    <h2>12. Termination</h2>
    <p>We may suspend or terminate your access at any time for any reason, including suspected
    breach of these Terms. Sections that by their nature should survive termination (including
    Sections 1, 7, 8, 9, 10, and 13) will survive.</p>

    <h2>13. Governing law</h2>
    <p>These Terms are governed by the laws of the jurisdiction in which <?= e($company) ?> is
    incorporated, without regard to conflict-of-law principles. Any dispute will be resolved
    exclusively in the competent courts of that jurisdiction.</p>

    <h2>14. Contact</h2>
    <p><?= e($company) ?> — <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a></p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
