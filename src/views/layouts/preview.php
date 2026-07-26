<?php
/**
 * Bare layout for framed previews inside the operator console.
 * No nav, no footer, no chrome — just the page tokens and whatever the
 * preview page renders, so an iframe shows the component in isolation.
 * @var string $bodyContent
 */
?>
<!doctype html>
<html lang="<?= e(AFT_LOCALE) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
<?php foreach (($pageCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= e(asset_v('css/' . $css)) ?>">
<?php endforeach; ?>
<style>
  /* The frame supplies its own scrolling; keep the body flush to its edges. */
  body { background: var(--paper); padding: 24px; }
</style>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<main id="main"><?= $bodyContent ?></main>
<?php foreach (($pageJs ?? []) as $js): ?>
<script src="<?= e(asset_v('js/' . $js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
