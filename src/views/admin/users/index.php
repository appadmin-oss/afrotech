<?php /** @var array $users @var bool $canManage @var array $me @var string|null $saved @var string|null $error */
$actorRole = Auth::role();
$assignable = Rbac::assignableRoles($actorRole);
?>
<div class="topbar">
  <div><span class="eyebrow">Access control</span><h1>Operators</h1></div>
  <?php if ($canManage): ?><a class="btn btn--primary btn--sm" href="<?= e(url('/admin/users/new')) ?>">+ New operator</a><?php endif; ?>
</div>

<?php if ($saved): ?><div class="alert alert--ok" style="margin-bottom:16px"><?= e($saved) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert--err" style="margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
  <div class="panel__head"><h2><?= count($users) ?> operators</h2></div>
  <?php if (!$users): ?>
    <p class="muted"><?= Database::available() ? 'No operators yet.' : 'Database not connected. Import database/schema.sql + seed.sql to create the first super admin.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Staff ID</th><th>Name</th><th>Username / email</th><th>Role</th><th>Status</th><th>Last login</th><?php if($canManage):?><th>Actions</th><?php endif;?></tr></thead>
      <tbody>
      <?php foreach ($users as $u):
        $isSelf = (int)$u['id'] === (int)$me['id'];
        $manageable = $canManage && !$isSelf && Rbac::canManageRole($actorRole, $u['role']);
      ?>
        <tr>
          <td class="mono"><?= e($u['staff_id']) ?></td>
          <td><?= e($u['name']) ?><?= $isSelf ? ' <span class="chip chip--role">you</span>' : '' ?></td>
          <td><?= e($u['username']) ?><br><span class="muted mono" style="font-size:11px"><?= e($u['email']) ?></span></td>
          <td>
            <?php if ($manageable): ?>
              <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>">
                <?= Csrf::field() ?>
                <select name="role" onchange="this.form.submit()">
                  <?php foreach (array_unique(array_merge([$u['role']], $assignable)) as $r): ?>
                    <option value="<?= e($r) ?>" <?= $u['role']===$r?'selected':'' ?>><?= e(Rbac::label($r)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            <?php else: ?>
              <span class="chip chip--role"><?= e(Rbac::label($u['role'])) ?></span>
            <?php endif; ?>
          </td>
          <td><span class="chip chip--<?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
          <td class="muted"><?= e($u['last_login_at'] ? date_pretty($u['last_login_at']) : 'never') ?></td>
          <?php if ($canManage): ?>
          <td class="actions-inline">
            <?php if ($manageable): ?>
              <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/status')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="status" value="<?= $u['status']==='active'?'suspended':'active' ?>">
                <button class="btn btn--light btn--sm"><?= $u['status']==='active'?'Suspend':'Reinstate' ?></button>
              </form>
              <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this operator?')">
                <?= Csrf::field() ?>
                <button class="btn btn--ghost btn--sm">Delete</button>
              </form>
            <?php else: ?>
              <span class="muted" style="font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Role → permission matrix -->
<div class="panel">
  <div class="panel__head"><h2>Roles &amp; permissions</h2><span class="muted mono" style="font-size:12px">RBAC matrix</span></div>
  <div class="table-wrap">
    <table class="matrix">
      <thead>
        <tr><th>Permission</th>
          <?php foreach (Rbac::ROLES as $role): ?><th><?= e(Rbac::label($role)) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach (Rbac::PERMISSIONS as $perm => $desc): ?>
          <tr>
            <td><strong><?= e($perm) ?></strong><br><span class="muted" style="font-size:12px"><?= e($desc) ?></span></td>
            <?php foreach (Rbac::ROLES as $role):
              $has = in_array($perm, Rbac::permissionsFor($role), true); ?>
              <td class="<?= $has ? 'yes' : 'no' ?>"><?= $has ? '✓' : '·' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted mt-4" style="font-size:12px">
    <?php foreach (Rbac::ROLE_BLURB as $role => $blurb): ?>
      <strong style="color:var(--txt)"><?= e(Rbac::label($role)) ?>:</strong> <?= e($blurb) ?><br>
    <?php endforeach; ?>
  </p>
</div>
