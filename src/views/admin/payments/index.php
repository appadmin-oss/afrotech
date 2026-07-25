<?php /** @var array $payments @var int $collected @var string $symbol */ ?>
<div class="topbar"><div><span class="eyebrow">Summer school</span><h1>Payments</h1></div></div>
<div class="stats">
  <div class="stat stat--accent"><div class="n"><?= e($symbol . number_format($collected)) ?></div><div class="l">Collected</div></div>
  <div class="stat"><div class="n"><?= count($payments) ?></div><div class="l">Transactions</div></div>
  <div class="stat"><div class="n"><?= count(array_filter($payments, fn($p)=>$p['status']==='succeeded')) ?></div><div class="l">Succeeded</div></div>
</div>
<div class="panel">
  <?php if (!$payments): ?>
    <p class="muted"><?= Database::available() ? 'No payments recorded yet.' : 'Database not connected.' ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Reference</th><th>Email</th><th>Provider</th><th>Base</th><th>Discount</th><th>Charged</th><th>Status</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td class="mono"><?= e($p['reference']) ?><?= $p['discount_code'] ? '<br><span class="muted" style="font-size:11px">'.e($p['discount_code']).'</span>' : '' ?></td>
        <td class="mono" style="font-size:11px"><?= e($p['email']) ?></td>
        <td><?= e($p['provider']) ?></td>
        <td><?= e($symbol . number_format((int)$p['base_amount'])) ?></td>
        <td><?= (int)$p['discount_amount'] ? '−'.$symbol.number_format((int)$p['discount_amount']) : '—' ?></td>
        <td><strong><?= e($symbol . number_format((int)$p['amount'])) ?></strong></td>
        <td><span class="chip chip--<?= $p['status']==='succeeded'?'paid':($p['status']==='pending'?'pending':'cancelled') ?>"><?= e($p['status']) ?></span></td>
        <td class="muted"><?= e(date_pretty($p['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
