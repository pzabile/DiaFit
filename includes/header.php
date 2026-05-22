<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= e($pageTitle ?? cfg('brand_name')) ?></title>
<meta name="description" content="<?= e($pageDescription ?? 'Personalized fitness coaching for people living with diabetes.') ?>" />
<link rel="icon" href="/assets/logo/logo-mark.svg" type="image/svg+xml" />
<link rel="apple-touch-icon" href="/assets/logo/logo-mark.svg" />
<?php if (($bodyClass ?? '') === 'admin-page'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/admin-v2.css" />
<?php elseif (($bodyClass ?? '') === 'portal-page'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/portal-v2.css" />
<?php else: ?>
<link rel="stylesheet" href="/styles.css" />
<?php if (!empty($extraHead)) echo $extraHead; ?>
<?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
