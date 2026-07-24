<?php /** @var array $courses */
$canManage = Rbac::can('courses.manage');
?>
<div class="topbar">
  <div><span class="eyebrow">Academy</span><h1>Courses</h1></div>
  <?php if ($canManage): ?><a class="btn btn--primary btn--sm" href="<?= e(url('/admin/courses/new')) ?>">+ New course</a><?php endif; ?>
</div>
<div class="panel">
  <?php if (!$courses): ?>
    <p class="muted"><?= Database::available() ? 'No courses yet.' : 'Database not connected — showing nothing to edit. Seeded fallback courses appear on the public site.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Title</th><th>Track</th><th>Level</th><th>Weeks</th><th>Fee</th><th>Status</th><?php if($canManage):?><th></th><?php endif;?></tr></thead>
      <tbody>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><?= e($c['title']) ?></td>
          <td class="muted"><?= e($c['track_slug'] ?: '—') ?></td>
          <td><?= e($c['level']) ?></td>
          <td><?= (int)$c['weeks'] ?></td>
          <td><?= e(naira((int)$c['price_naira'])) ?></td>
          <td><span class="chip chip--<?= $c['status']==='published'?'confirmed':'unpaid' ?>"><?= e($c['status']) ?></span></td>
          <?php if($canManage):?>
          <td class="actions-inline">
            <a class="btn btn--light btn--sm" href="<?= e(url('/admin/courses/' . $c['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/admin/courses/' . $c['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this course?')"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Delete</button></form>
          </td>
          <?php endif;?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
