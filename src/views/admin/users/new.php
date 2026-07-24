<?php /** @var array $assignable @var string|null $error @var array $old */
$old = $old ?? [];
$ov = fn($k) => e($old[$k] ?? '');
?>
<div class="topbar">
  <div><a class="pill" href="<?= e(url('/admin/users')) ?>">← Operators</a><h1 class="mt-2">New operator</h1></div>
</div>
<?php if ($error): ?><div class="alert alert--err" style="margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
<div class="panel" style="max-width:640px">
  <form class="form" method="post" action="<?= e(url('/admin/users/save')) ?>">
    <?= Csrf::field() ?>
    <div class="field"><label>Full name</label><input type="text" name="name" value="<?= $ov('name') ?>" required></div>
    <div class="form__row">
      <div class="field"><label>Username</label><input type="text" name="username" value="<?= $ov('username') ?>" required></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= $ov('email') ?>" required></div>
    </div>
    <div class="field"><label>Temporary password</label><input type="text" name="password" minlength="8" required><div class="hint">At least 8 characters. Share securely; they can change it later.</div></div>
    <div class="field"><label>Role</label>
      <select name="role" required>
        <?php foreach ($assignable as $r): ?>
          <option value="<?= e($r) ?>" <?= $ov('role')===$r?'selected':'' ?>><?= e(Rbac::label($r)) ?> — <?= e(Rbac::ROLE_BLURB[$r] ?? '') ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">You can only assign roles junior to your own.</div>
    </div>
    <div class="flex"><button class="btn btn--primary">Create operator</button><a class="btn btn--ghost" href="<?= e(url('/admin/users')) ?>">Cancel</a></div>
  </form>
</div>
