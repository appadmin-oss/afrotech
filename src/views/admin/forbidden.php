<?php /** @var string $permission */ ?>
<div class="topbar"><h1>Access denied</h1></div>
<div class="panel">
  <p class="mono" style="color:var(--red);font-weight:700">// 403 — permission required</p>
  <h2 class="mt-2">You don't have access to this area.</h2>
  <p>Your role (<strong><?= e(Rbac::label(Auth::role())) ?></strong>) is missing the <code class="mono"><?= e($permission) ?></code> permission. If you need it, ask a super admin to adjust your role.</p>
  <a class="btn btn--primary mt-4" href="<?= e(url('/admin')) ?>">Back to dashboard</a>
</div>
