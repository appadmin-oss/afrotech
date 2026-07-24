<?php
/** @var string $bodyContent */
$u = Auth::user();
$nav = [
  ['Overview', [
    ['/admin',          'Dashboard',       'dashboard.view'],
  ]],
  ['Summer school', [
    ['/admin/summer',   'Registrations',   'registrations.view'],
  ]],
  ['Academy', [
    ['/admin/courses',  'Courses',         'courses.view'],
    ['/admin/students', 'Students',        'students.view'],
    ['/admin/content',  'Landing content', 'content.manage'],
  ]],
  ['Access control', [
    ['/admin/users',    'Operators',       'users.view'],
  ]],
];
?>
<!doctype html>
<html lang="<?= e(AFT_LOCALE) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="robots" content="noindex">
<script>try{var t=localStorage.getItem('aft-admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>">
</head>
<body>
<div class="admin">
  <aside class="side">
    <a class="side__brand" href="<?= e(url('/admin')) ?>">
      <span class="brand__mark">A</span> Afrotech <span class="muted" style="font-weight:500">Ops</span>
    </a>
    <?php foreach ($nav as [$group, $items]):
        $visible = array_filter($items, fn($i) => Rbac::can($i[2]));
        if (!$visible) continue; ?>
      <div class="side__group"><?= e($group) ?></div>
      <?php foreach ($visible as [$href, $label, $perm]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= (current_path() === $href || ($href !== '/admin' && is_active($href))) ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="side__foot">
      Signed in as <strong style="color:var(--txt)"><?= e($u['name'] ?? 'Operator') ?></strong><br>
      <span class="mono"><?= e(Rbac::label($u['role'] ?? 'viewer')) ?> · <?= e($u['staff_id'] ?? '') ?></span>
      <form method="post" action="<?= e(url('/admin/logout')) ?>" class="mt-4"><?= Csrf::field() ?>
        <button class="btn btn--ghost btn--sm btn--block">Sign out</button>
      </form>
    </div>
  </aside>
  <div class="main"><?= $bodyContent ?></div>
</div>
<script src="<?= e(asset_v('js/app.js')) ?>" defer></script>
</body>
</html>
