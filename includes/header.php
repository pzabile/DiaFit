<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= e($pageTitle ?? cfg('brand_name')) ?></title>
<meta name="description" content="<?= e($pageDescription ?? 'Personalized fitness coaching for people living with diabetes.') ?>" />
<link rel="stylesheet" href="styles.css" />
</head>
<body class="<?= e($bodyClass ?? '') ?>">
