<?php /** @var array $students @var bool $canManage */ ?>
<div class="topbar"><div><span class="eyebrow">Academy</span><h1>Students</h1></div></div>
<div class="panel">
  <?php if (!$students): ?>
    <p class="muted"><?= Database::available() ? 'No student accounts yet.' : 'Database not connected.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Track</th><th>Status</th><th>Joined</th><?php if($canManage):?><th></th><?php endif;?></tr></thead>
      <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td class="mono"><?= e($s['student_id']) ?></td>
          <td><?= e($s['name']) ?></td>
          <td class="mono"><?= e($s['email']) ?></td>
          <td class="muted"><?= e($s['track_slug'] ?: '—') ?></td>
          <td><span class="chip chip--<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
          <td class="muted"><?= e(date_pretty($s['created_at'])) ?></td>
          <?php if($canManage):?>
          <td class="actions-inline">
            <form method="post" action="<?= e(url('/admin/students/' . $s['id'] . '/status')) ?>">
              <?= Csrf::field() ?>
              <select name="status" onchange="this.form.submit()">
                <?php foreach (['active','paused','withdrawn'] as $st): ?><option value="<?= $st ?>" <?= $s['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
              </select>
            </form>
          </td>
          <?php endif;?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
