<?php /** @var string $bodyContent */ ?>
<!doctype html>
<html lang="<?= e(AFT_LOCALE) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(AFT_NAME) ?>">
<meta name="theme-color" content="#E4022B">
<script>/* apply persisted theme before paint */try{var t=localStorage.getItem('aft-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php partial('nav'); ?>
<main id="main"><?= $bodyContent ?></main>
<?php partial('footer'); ?>
<script src="<?= e(asset_v('js/app.js')) ?>" defer></script>
</body>
</html>
