<?php
$pageTitle = 'Privacy Policy — DiaFitus';
$bodyClass = 'legal';
require __DIR__ . '/includes/header.php';
$company = cfg('company_name');
$brand   = cfg('brand_name');
$support = cfg('support_email');
?>
  <header class="nav slim">
    <a href="/" class="brand">
      <span class="logo-dot"></span>
      <span class="brand-name"><?= e($brand) ?></span>
    </a>
  </header>

  <main class="legal-main">
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: <?= date('F j, Y') ?></p>

    <p>This Privacy Policy forms part of the <a href="/terms">Terms &amp; Conditions</a> of <strong><?= e($company) ?></strong>, located at <strong>1317 Westminster Dr, Woodridge, Illinois 60517, USA</strong> ("we," "our," "us," "Company"). All capitalized terms used in this Policy have the meaning given in the Terms &amp; Conditions unless defined below.</p>
    <p>This Policy explains how we collect, use, store and share your information. By providing personal information, you consent to our collection, use and disclosure of it in accordance with this Policy and any other arrangement we have with you. We may change this Policy from time to time by posting an updated version. The current effective date is at the bottom of this page.</p>

    <h2>1. Definitions</h2>
    <ul>
      <li><strong>Personal Information</strong> — any information that identifies, relates to, describes or could reasonably be linked to you.</li>
      <li><strong>Payment Information</strong> — information needed to purchase the Service (last four digits of card, billing country, brand, payment ID).</li>
      <li><strong>Health data</strong> — information about your health, body and lifestyle you share with us in the questionnaire, weekly check-ins, daily logs and meal photos.</li>
      <li><strong>Automatic Data</strong> — technical data automatically sent by your device and browser when you use the Service (IP address, browser identifier, device characteristics, page-view statistics).</li>
      <li><strong>Non-Personal Information</strong> — aggregated or anonymized information that does not identify any individual.</li>
    </ul>

    <h2>2. Information we collect</h2>
    <p>We collect information you voluntarily provide through the questionnaire, account creation, dashboard logging, support correspondence, surveys, and any communication with our coaches.</p>
    <p>This includes:</p>
    <ul>
      <li><strong>Identity &amp; contact</strong>: first name, email address, phone number, postal address (if you provide it), date of birth (if you provide it).</li>
      <li><strong>Demographics</strong>: gender, age, height, weight.</li>
      <li><strong>Health information</strong>: diabetes type, years since diagnosis, medications, complications, fasting glucose, hypoglycemia history, exercise history, lifestyle, sleep, diet style, foot condition, current symptoms, weekly glucose / weight / energy ratings, meal photos and notes, daily check-in entries.</li>
      <li><strong>Payment Information</strong>: the last four digits of your card, brand, billing country, transaction ID — processed by Stripe on our behalf. We never see or store your full card number.</li>
      <li><strong>Automatic Data</strong>: IP address, browser and device characteristics, page-view statistics, error logs.</li>
    </ul>

    <h2>3. Why we collect this information</h2>
    <ul>
      <li>To build and deliver your personalized program;</li>
      <li>To operate the dashboard, the Telegram support channel and the messaging features;</li>
      <li>To process payments and detect fraud;</li>
      <li>To send service emails (welcome, account setup, plan delivery, billing receipts) and product updates;</li>
      <li>To respond to support requests and resolve disputes;</li>
      <li>To improve our programs, content and Service through analytics and user-experience research;</li>
      <li>To comply with legal obligations and enforce our Terms.</li>
    </ul>
    <p>We may send you service notices and security alerts you cannot opt out of (for example, password reset, billing receipts).</p>

    <h2>4. Health information &mdash; how we treat it</h2>
    <p>You voluntarily share information about your diabetes and overall health. We treat this information as <strong>sensitive personal data</strong> and use it solely to deliver and improve the Service for you. We <strong>do not</strong> sell or rent health information. We <strong>do not</strong> share it with advertisers. Access is restricted to staff and contracted coaches/medical reviewers who need it to serve you. Health data is encrypted in transit (TLS) and at rest where supported.</p>

    <h2>5. Direct marketing</h2>
    <p>We may send marketing emails about new features and offers. By creating an account you consent to receive these. You may opt out at any time using the unsubscribe link in any marketing email or by writing to <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>. Opting out of marketing does not stop service-related emails.</p>

    <h2>6. Disclosure of your information</h2>
    <p>We share Personal Information only with:</p>
    <ul>
      <li><strong>Stripe, Inc.</strong> — to process payments. Stripe acts as a separate data controller for the payment data it collects; see Stripe's own privacy policy.</li>
      <li><strong>Telegram</strong> — when you communicate with our coaches there, your messages are processed by Telegram FZ-LLC under its own privacy policy.</li>
      <li><strong>Email and hosting providers</strong> — Hostinger (web hosting and email) processes information solely on our instructions.</li>
      <li><strong>Customer-support tooling and AI providers</strong> — we may use AI tools to assist coaches or summarize messages; see Section 11.</li>
      <li><strong>Legal authorities</strong> — when required by law, court order or to protect our rights or the safety of others.</li>
    </ul>
    <p>We do not sell, rent or trade personal information to third parties for their own marketing purposes.</p>

    <h2>7. Cookies and similar technologies</h2>
    <p>We use a small number of cookies (and equivalent local-storage entries) to maintain your session, remember your progress between pages and measure basic site performance. You can block cookies via your browser settings; some Service features may stop working if you do.</p>

    <h2>8. Data security</h2>
    <p>We protect your data using industry-standard measures including TLS encryption in transit, encrypted backups, strict server-level password protection for the admin panel, role-restricted database access, strong password hashing (bcrypt), CSRF protection on every form and MIME-type validation on every file upload. No method of transmission or storage is 100% secure, however, and we cannot guarantee absolute security. You are responsible for keeping your password confidential and notifying us of any unauthorized use.</p>

    <h2>9. Data retention</h2>
    <p>We retain your personal data only as long as necessary for the purposes described above:</p>
    <ul>
      <li>Account and program data: kept for up to <strong>5 years</strong> after your last update or your last active subscription.</li>
      <li>Health data: kept for up to <strong>5 years</strong> after the end of provision of services, unless you request earlier deletion.</li>
      <li>Marketing-consent records: kept up to <strong>2 years</strong> after consent is given or withdrawn.</li>
      <li>Payment records: kept as long as required by U.S. tax and accounting law (typically 7 years).</li>
    </ul>
    <p>At the end of the retention period (or earlier on your request) personal data is destroyed using overwriting or, where applicable, physical destruction. We may retain data longer where required to comply with legal obligations or to protect vital interests.</p>

    <h2>10. Your rights</h2>
    <p>Depending on where you live, you may have the right to:</p>
    <ul>
      <li>access the personal data we hold about you;</li>
      <li>correct or update it;</li>
      <li>request deletion of your account and data;</li>
      <li>restrict or object to certain processing;</li>
      <li>receive a portable copy in a common machine-readable format;</li>
      <li>withdraw consent at any time (without affecting the lawfulness of prior processing);</li>
      <li>lodge a complaint with your local data-protection authority.</li>
    </ul>
    <p>To exercise any of these rights, email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>. We may need to verify your identity before fulfilling the request and will respond within the time required by applicable law.</p>

    <h2>11. AI-assisted tooling</h2>
    <p>We may use AI tools to assist coaches in drafting replies, summarizing messages, or detecting safety issues. Conversations in the coaching service may be reviewed by AI and by staff. Where AI tools are used:</p>
    <ul>
      <li>data is processed only to enhance the Service, improve user experience and provide efficient support;</li>
      <li>the AI providers we use are bound by data-protection terms and confidentiality obligations;</li>
      <li>AI is not used to make final decisions about subscription management, refunds or data-subject rights. Those are handled by a person.</li>
    </ul>

    <h2>12. Children</h2>
    <p>The Service is intended for adults aged 18 and over. We do not knowingly collect personal information from children under 18. If you believe a child has provided us information, email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> and we will delete it.</p>

    <h2>13. International transfers</h2>
    <p>The Company is based in the United States. Your information may be processed in the United States and other countries where our service providers operate. Where required, we use appropriate safeguards (such as Standard Contractual Clauses) for international transfers.</p>

    <h2>14. California residents (CCPA)</h2>
    <p>To the extent the California Consumer Privacy Act ("CCPA") applies, you have the right to: know the personal data we collect from you; request deletion of your personal data; opt out of any sale or sharing of your personal data; access your personal data; and not be discriminated against for exercising these rights. <strong>We do not sell, share, lease or rent your personal information.</strong> To exercise any CCPA right, email <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>.</p>

    <h2>15. Health information disclaimer</h2>
    <p>The Service is not a medical service. The data you log helps your coach personalize your program but is not used for diagnosis or treatment. See our <a href="/terms">Terms &amp; Conditions</a> for the full medical disclaimer and limitation of liability.</p>

    <h2>16. Changes to this Policy</h2>
    <p>We may update this Policy from time to time. Material changes will be communicated by email or by a notice on the website. The "Last updated" date at the top of this page reflects the most recent version.</p>

    <h2>17. Contact</h2>
    <p><?= e($company) ?> &middot; 1317 Westminster Dr, Woodridge, Illinois 60517, USA<br>
       Support &amp; privacy requests: <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a></p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
