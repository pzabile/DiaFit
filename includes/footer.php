  <footer class="site-footer">
    <div class="brand"><span class="logo-dot"></span><span class="brand-name"><?= e(cfg('brand_name')) ?></span></div>
    <div class="footer-links">
      <a href="/terms">Terms &amp; Conditions</a>
      <a href="/privacy">Privacy Policy</a>
      <a href="/login">Member sign in</a>
      <a href="mailto:<?= e(cfg('support_email')) ?>">Contact</a>
    </div>
    <p class="footer-legal">© <?= date('Y') ?> <?= e(cfg('company_name')) ?>. <?= e(cfg('brand_name')) ?> provides general fitness and lifestyle suggestions and is not a substitute for medical advice. Always consult your physician.</p>
  </footer>
  <script src="/script.js"></script>
</body>
</html>
