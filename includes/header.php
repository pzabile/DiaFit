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
<link rel="stylesheet" href="/styles.css" />
<?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
