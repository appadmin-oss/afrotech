<?php /** @var array $discounts @var string $symbol */ ?>
<div class="topbar">
  <div><span class="eyebrow">Summer school</span><h1>Discount codes</h1></div>
  <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/discounts/new')) ?>">+ New code</a>
</div>
<div class="panel">
  <?php if (!$discounts): ?>
    <p class="muted"><?= Database::available() ? 'No discount codes yet.' : 'Database not connected.' ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Code</th><th>Description</th><th>Value</th><th>Uses</th><th>Window</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($discounts as $d): ?>
      <tr>
        <td class="mono"><strong><?= e($d['code']) ?></strong></td>
        <td><?= e($d['description'] ?: '—') ?></td>
        <td><?= $d['type']==='percent' ? ((int)$d['value'].'%') : ($symbol . number_format((int)$d['value'])) ?></td>
        <td><?= (int)$d['used_count'] ?><?= $d['max_uses']!==null ? ' / '.(int)$d['max_uses'] : '' ?></td>
        <td class="muted"><?= e($d['ends_at'] ? 'ends '.date_pretty($d['ends_at']) : 'no end') ?></td>
        <td><span class="chip chip--<?= $d['status']==='active'?'confirmed':'unpaid' ?>"><?= e($d['status']) ?></span></td>
        <td class="actions-inline">
          <a class="btn btn--light btn--sm" href="<?= e(url('/admin/discounts/' . $d['id'] . '/edit')) ?>">Edit</a>
          <form method="post" action="<?= e(url('/admin/discounts/' . $d['id'] . '/delete')) ?>" onsubmit="return confirm('Delete?')"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
