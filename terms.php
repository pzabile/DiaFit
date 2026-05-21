<?php
$pageTitle = 'Terms & Conditions — DiaFitus';
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
    <h1>Terms &amp; Conditions</h1>
    <p class="muted">Last updated: <?= date('F j, Y') ?></p>

    <h2>1. Welcome to <?= e($brand) ?></h2>
    <p>These Terms &amp; Conditions (the "Agreement") govern your access to and use of the <?= e($brand) ?> website, mobile interfaces, member portal, coach messaging, nutrition guides, exercise programs and any related digital content (collectively, the "Product" or "Service"). The Service is operated by <strong><?= e($company) ?></strong>, located at <strong>1317 Westminster Dr, Woodridge, Illinois 60517, USA</strong> ("Company," "we," "our," "us"). The Company makes the Service available to you ("you," "your," "User") subject to this Agreement.</p>
    <p>By accessing or using the Service, you accept and agree to be bound by this Agreement. Read it carefully before using the Service. If you do not agree, do not access or use the Service.</p>

    <h2>2. Privacy</h2>
    <p>Our handling of your personal information is described in our <a href="/privacy">Privacy Policy</a>, which is incorporated into and forms part of this Agreement. By using the Service you also accept the Privacy Policy.</p>

    <h2>3. Medical disclaimer — please read carefully</h2>
    <p><strong><?= e($brand) ?> is not a medical provider.</strong> The Service provides general fitness, lifestyle and nutrition <em>suggestions</em> only. Information, exercise programs, nutrition guides, PDFs and any messages or guidance delivered through the Service are for informational and educational purposes only. They <strong>do not constitute medical advice, diagnosis or treatment</strong>.</p>
    <p>Diabetes is a serious medical condition. You must consult a licensed physician before starting any program offered through the Service, and before changing or stopping any medication. Decisions to act on any suggestion from the Service are entirely your own; you are responsible for verifying any information with your treating doctor.</p>
    <p>Coaches, support staff and any individuals communicating with you through Telegram, email or any other channel are not acting as your physician. References to "doctors" or "medical review" describe general review of program templates and do not establish a doctor-patient relationship. No such relationship is created by your use of the Service.</p>
    <p><strong>Call your physician or local emergency number immediately if you experience symptoms of severe hypoglycemia, hyperglycemia, chest pain, shortness of breath, fainting, or any other medical emergency.</strong> Do not rely on the Service in a medical emergency.</p>

    <h2>4. Eligibility</h2>
    <p>You must be at least 18 years old to use the Service. By using the Service you represent that you are of legal age, that any information you provide is accurate and current, and that you are legally able to enter into this Agreement.</p>

    <h2>5. Subscriptions, payment and renewal</h2>
    <p>The Service is offered as one or more time-limited plans (for example a 7-day, 4-week or 12-week plan) at the prices shown at checkout. All prices are in U.S. Dollars unless stated otherwise; in other countries the actual amount charged may differ based on conversion and local payment processing.</p>
    <p>Payment is processed by Stripe, Inc. on our behalf. By providing payment information you authorize the Company (via Stripe) to charge the displayed amount for the plan you selected. You confirm that you are duly authorized to use the payment method provided.</p>
    <p>Plans are sold as one-time purchases for the listed period. We do not enroll you in automatic monthly billing unless you separately and expressly opt in to a recurring plan at checkout. If you do enroll in a recurring plan, you may cancel at any time before the next renewal by writing to <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>. Deleting the mobile or web interface does not cancel any subscription and does not entitle you to a refund.</p>

    <h2>6. No refunds (digital content)</h2>
    <p><strong>All sales are final.</strong> By purchasing access you acknowledge that the Service consists of digital content (personalized programs, nutrition guides, coach chat, dashboard access) delivered immediately on payment, that you expressly request immediate access to that content, and that you waive any statutory withdrawal right that would otherwise apply once digital content has been provided.</p>
    <p>If you believe the Service is materially faulty, contact <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> with a detailed description and supporting evidence (screenshots, error messages). We will evaluate each case on its facts and, at our sole discretion, may extend your access, provide additional content, or in extraordinary cases issue a partial or full refund. Outside such cases <strong>no refunds will be issued</strong>.</p>
    <p>If you purchased through Apple App Store or Google Play, any refund requests for those purchases must be addressed to Apple or Google directly under their respective policies.</p>

    <h2>7. Your account and conduct</h2>
    <p>You are responsible for maintaining the confidentiality of your password and any related credentials. We recommend changing your password frequently and notifying us immediately of any unauthorized use at <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>. We are not liable for any loss or damage resulting from someone else using your account, with or without your permission.</p>
    <p>You agree not to: use another person's account; share or resell your access; interfere with or attempt to disrupt the Service, its servers, networks or infrastructure; use the Service to send unsolicited communications, malware, or harmful, harassing, abusive, threatening, obscene, racist, illegal or infringing content; or violate any applicable law in connection with the Service.</p>
    <p>If we receive a file from you (uploaded photo, document, etc.) you confirm you have the right to share it and that it does not infringe a third party right.</p>

    <h2>8. Acceptable use of the Service</h2>
    <p>You use the Service for your own personal, non-commercial purposes only. You will not reverse-engineer, decompile or attempt to extract our content for redistribution. You will not impersonate another person or misrepresent your health information.</p>

    <h2>9. Coaching and AI-assisted support</h2>
    <p>To improve quality and detect misuse, we may use automated tools and artificial intelligence to assist coaches, summarize messages or pre-draft replies. Conversations within the coaching service may be reviewed by staff or analyzed by AI tools for quality and safety. By using the coaching service you acknowledge and agree to this practice.</p>
    <p>We reserve the right to suspend or terminate your access to the coaching service, in whole or in part, at our sole discretion and without prior notice, if we determine your use is inappropriate, abusive or in violation of this Agreement.</p>
    <p>Any information from coaches or the AI is for general wellness purposes only. It is not a substitute for professional medical, legal, financial or other specialized advice. Always consult a qualified healthcare professional when in doubt about whether our suggestions are suitable for you.</p>

    <h2>10. Intellectual property</h2>
    <p>All content provided through the Service — including the questionnaire, exercise programs, nutrition PDFs, written guides, brand marks, software and dashboard — is owned by the Company or its licensors and is protected by applicable U.S. and international intellectual property laws. You receive a limited, revocable, non-transferable, non-exclusive license to use this content for personal use only during your active subscription period.</p>

    <h2>11. User submissions</h2>
    <p>You may submit information through the questionnaire, dashboard logs, meal photos and coach chat ("Submissions"). You grant the Company a worldwide, royalty-free license to use the Submissions in order to operate the Service, improve our programs and contact you. You represent that you have the right to share each Submission you provide.</p>

    <h2>12. Disclaimer of warranties</h2>
    <p>The Service is provided <strong>"as is"</strong> and <strong>"as available"</strong> without warranties of any kind, express or implied, including without limitation warranties of merchantability, non-infringement, fitness for a particular purpose, security or accuracy. To the fullest extent permitted by law, we expressly disclaim all such warranties and make no guarantee that:</p>
    <ul>
      <li>the Service will meet your specific requirements;</li>
      <li>the Service will be uninterrupted, timely, secure or error-free;</li>
      <li>the results obtainable from the Service will be accurate or reliable;</li>
      <li>any content, materials or recommendations will meet your expectations or be beneficial to you.</li>
    </ul>

    <h2>13. Limitation of liability</h2>
    <p>To the fullest extent permitted by law, the Company, its officers, employees, contractors, coaches, medical reviewers, affiliates and partners shall <strong>not be liable</strong> for any direct, indirect, incidental, special, consequential, exemplary or punitive damages, or for any loss of profits, revenue, data, health, well-being, fitness, body composition or other intangibles arising out of or related to:</p>
    <ul>
      <li>your access to or use of (or inability to use) the Service;</li>
      <li>any exercise, nutrition, lifestyle or other suggestion provided through the Service;</li>
      <li>any worsening, complication or side effect of diabetes or any other medical condition — including hypoglycemia, hyperglycemia, injury, illness, hospitalization or death;</li>
      <li>any reliance on information obtained through the Service, including information from coaches, written guides or PDFs;</li>
      <li>any action you take or fail to take based on the Service.</li>
    </ul>
    <p>You acknowledge that exercise carries inherent physical risk, that diabetes increases that risk, and that <strong>you assume all such risk</strong>. You are solely responsible for your health decisions. Where law does not allow the exclusion of certain damages, our aggregate liability is limited to the total amount you paid us in the twelve months preceding the claim. This Section survives any termination or expiration of this Agreement.</p>

    <h2>14. Indemnification</h2>
    <p>You agree to indemnify, defend and hold harmless the Company and its personnel from any claim, loss, liability, damage or expense (including reasonable attorneys' fees) arising out of (a) your use of the Service, (b) your breach of this Agreement, (c) your violation of any law or third-party right, or (d) any action taken with your account whether by you or by someone else. This Section survives termination.</p>

    <h2>15. Modifications, interruption and termination</h2>
    <p>We may modify, suspend, disrupt or discontinue the Service or any part of it, for all users or for you specifically, at any time with or without notice. We will not be liable for any such action or for any resulting loss or damage. The Service depends on software, hardware and tools owned or operated by us and our contractors and suppliers; while we make commercially reasonable efforts to keep the Service reliable, no service is 100% available, and we do not guarantee uninterrupted, consistent, timely or error-free access.</p>
    <p>We may modify this Agreement by posting an updated version. Material changes will be communicated by email or by a notice on the website. Continued use of the Service after the effective date constitutes acceptance.</p>

    <h2>16. Notices</h2>
    <p>We may give notices to you by email to the address on file or by posting on the website; notices are deemed received on the date sent or posted. Notices to us must be sent to <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a>.</p>

    <h2>17. Governing law &amp; dispute resolution</h2>
    <p>This Agreement is governed by the laws of the State of Illinois, United States, without regard to its conflict-of-laws principles. You agree to first attempt to resolve any dispute informally by emailing <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a> with the following information: (i) date of purchase, (ii) the email used at purchase, (iii) your name, (iv) a description of the issue (with supporting evidence where applicable).</p>
    <p>Any action or proceeding arising out of or relating to this Agreement shall be brought exclusively in the state or federal courts located in DuPage County, Illinois, and you irrevocably consent to their personal jurisdiction. Nothing in this Agreement affects mandatory consumer-protection rights that you have under the law of the country in which you reside.</p>
    <p>Where permitted by law, you and the Company each agree to bring claims only in an individual capacity and not as a plaintiff or class member in any purported class or representative action.</p>

    <h2>18. Miscellaneous</h2>
    <p>This Agreement, together with the Privacy Policy, constitutes the entire agreement between you and us regarding the Service and supersedes all prior agreements. You confirm that you have not relied on any promise or representation not set forth here. If any provision is held unenforceable, the remaining provisions remain in full force. Sections 3, 6, 10–14 and 17 survive termination. We may assign this Agreement; you may not.</p>

    <h2>19. Contact</h2>
    <p><?= e($company) ?> &middot; 1317 Westminster Dr, Woodridge, Illinois 60517, USA<br>
       Support: <a href="mailto:<?= e($support) ?>"><?= e($support) ?></a></p>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
