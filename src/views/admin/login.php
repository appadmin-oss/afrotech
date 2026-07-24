<?php /** @var string|null $error */ ?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="brand"><span class="brand__mark">A</span> Afrotech <span class="muted" style="font-weight:500">Ops</span></div>
    <h1 class="center" style="font-size:var(--fs-h3)">Operator sign-in</h1>
    <p class="center muted" style="font-size:var(--fs-sm)">Role-based access · authorised staff only</p>
    <?php if ($error): ?><div class="alert alert--err mt-4"><?= e($error) ?></div><?php endif; ?>
    <form class="form mt-6" method="post" action="<?= e(url('/admin/login')) ?>">
      <?= Csrf::field() ?>
      <div class="field"><label>Username or email</label><input type="text" name="identifier" required autofocus></div>
      <div class="field"><label>Password</label><input type="password" name="password" required></div>
      <button class="btn btn--primary btn--block">Sign in</button>
    </form>
    <p class="center muted mt-6" style="font-size:var(--fs-xs)"><a href="<?= e(url('/')) ?>">← Back to site</a></p>
  </div>
</div>
