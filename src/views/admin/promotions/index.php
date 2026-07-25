<?php /** @var array $promotions */ ?>
<div class="topbar">
  <div><span class="eyebrow">Academy</span><h1>Promotions</h1></div>
  <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/promotions/new')) ?>">+ New promotion</a>
</div>
<div class="panel">
  <?php if (!$promotions): ?>
    <p class="muted"><?= Database::available() ? 'No promotions yet. The announcement ribbon is hidden until you add one.' : 'Database not connected.' ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Title</th><th>Badge</th><th>Tone</th><th>Countdown</th><th>Window</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($promotions as $p): ?>
      <tr>
        <td><?= e($p['title']) ?></td>
        <td><?= e($p['badge'] ?: '—') ?></td>
        <td><span class="chip chip--role"><?= e($p['tone']) ?></span></td>
        <td><?= $p['show_countdown'] ? 'Yes' : '—' ?></td>
        <td class="muted"><?= e($p['ends_at'] ? 'ends ' . date_pretty($p['ends_at']) : 'no end') ?></td>
        <td><span class="chip chip--<?= $p['status']==='active'?'confirmed':'unpaid' ?>"><?= e($p['status']) ?></span></td>
        <td class="actions-inline">
          <a class="btn btn--light btn--sm" href="<?= e(url('/admin/promotions/' . $p['id'] . '/edit')) ?>">Edit</a>
          <form method="post" action="<?= e(url('/admin/promotions/' . $p['id'] . '/delete')) ?>" onsubmit="return confirm('Delete?')"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
