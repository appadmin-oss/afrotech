<?php /** @var string $bodyContent */ ?>
<!doctype html>
<html lang="<?= e(AFT_LOCALE) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="robots" content="noindex">
<script src="<?= e(asset_v('js/theme-boot.js')) ?>" data-key="aft-theme"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>">
</head>
<body>
<?= $bodyContent ?>
<script src="<?= e(asset_v('js/app.js')) ?>" defer></script>
</body>
</html>
