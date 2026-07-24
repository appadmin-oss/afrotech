<?php /** @var string|null $error */ ?>
<section class="section">
  <div class="wrap" style="max-width:440px">
    <div class="card">
      <span class="eyebrow">Student sign-in</span>
      <h1 style="font-size:var(--fs-h2);margin-top:8px">Welcome back</h1>
      <p class="muted">Access your courses and progress.</p>
      <?php if ($error): ?><div class="alert alert--err mt-4"><?= e($error) ?></div><?php endif; ?>
      <form class="form mt-6" method="post" action="<?= e(url('/login')) ?>">
        <?= Csrf::field() ?>
        <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn btn--primary btn--block">Sign in</button>
      </form>
      <p class="muted center mt-6" style="font-size:var(--fs-sm)">New here? <a href="<?= e(url('/summer')) ?>" style="color:var(--red);font-weight:600">Register for Summer School</a> to get an account.</p>
    </div>
  </div>
</section>
