<?php /** @var array $payments @var int $collected @var int $outstanding @var string $symbol
 *  @var bool $canConfirm @var bool $audited @var string|null $flash @var string|null $flashErr */
$viaLabels = [
    'callback' => 'gateway',
    'webhook'  => 'webhook',
    'operator' => 'by hand',
    'manual'   => 'transfer',
];
?>
<div class="topbar"><div><span class="eyebrow">Summer school</span><h1>Payments</h1></div></div>

<?php if ($flash): ?><div class="alert alert--ok" style="margin-bottom:var(--sp-5)"><?= e($flash) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="alert alert--err" style="margin-bottom:var(--sp-5)"><?= e($flashErr) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat stat--accent"><div class="n"><?= e($symbol . number_format($collected)) ?></div><div class="l">Collected</div></div>
  <div class="stat"><div class="n"><?= e($symbol . number_format($outstanding)) ?></div><div class="l">Awaiting payment</div></div>
  <div class="stat"><div class="n"><?= count($payments) ?></div><div class="l">Transactions</div></div>
  <div class="stat"><div class="n"><?= count(array_filter($payments, fn($p)=>$p['status']==='succeeded')) ?></div><div class="l">Succeeded</div></div>
</div>

<div class="panel">
  <div class="panel__head">
    <h2>Ledger</h2>
    <?php if ($canConfirm): ?>
      <p class="muted" style="font-size:var(--fs-xs);max-width:56ch;margin:0">
        Confirming a transfer marks the registration paid and emails the family their receipt —
        the same thing a card payment does. It cannot be undone from here.
      </p>
    <?php endif; ?>
  </div>

  <?php if (!$audited && Database::available()): ?>
    <div class="alert alert--err" style="margin-bottom:var(--sp-4)">
      <strong>Confirmation audit columns are missing.</strong> Import
      <code>database/migrations/2026-07-payment-confirmation.sql</code> to record which route
      confirmed each payment and to guarantee a receipt is only ever emailed once.
    </div>
  <?php endif; ?>

  <?php if (!$payments): ?>
    <p class="muted"><?= Database::available() ? 'No payments recorded yet.' : 'Database not connected.' ?></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr>
      <th>Reference</th><th>Email</th><th>Method</th><th>Base</th><th>Discount</th>
      <th>Charged</th><th>Status</th><th>Confirmed</th><th>When</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td class="mono">
          <?php if ($p['status'] === 'succeeded'): ?>
            <a href="<?= e(url('/summer/receipt/' . rawurlencode($p['reference']))) ?>" target="_blank" rel="noopener"><?= e($p['reference']) ?></a>
          <?php else: ?>
            <?= e($p['reference']) ?>
          <?php endif; ?>
          <?= $p['discount_code'] ? '<br><span class="muted" style="font-size:11px">'.e($p['discount_code']).'</span>' : '' ?>
        </td>
        <td class="mono" style="font-size:11px"><?= e($p['email']) ?></td>
        <td><?= e($p['provider']) ?></td>
        <td><?= e($symbol . number_format((int)$p['base_amount'])) ?></td>
        <td><?= (int)$p['discount_amount'] ? '−'.$symbol.number_format((int)$p['discount_amount']) : '—' ?></td>
        <td><strong><?= e($symbol . number_format((int)$p['amount'])) ?></strong></td>
        <td><span class="chip chip--<?= $p['status']==='succeeded'?'paid':($p['status']==='pending'?'pending':'cancelled') ?>"><?= e($p['status']) ?></span></td>
        <td class="muted" style="font-size:11px">
          <?php if (!empty($p['confirmed_via'])): ?>
            <?= e($viaLabels[$p['confirmed_via']] ?? $p['confirmed_via']) ?>
            <?php if (!empty($p['receipt_sent_at'])): ?><br><span title="Receipt emailed <?= e(datetime_pretty($p['receipt_sent_at'])) ?>">receipt sent</span><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="muted"><?= e(date_pretty($p['created_at'])) ?></td>
        <td style="text-align:right">
          <?php if ($canConfirm && $p['status'] === 'pending'): ?>
            <form method="post" action="<?= e(url('/admin/payments/' . rawurlencode($p['reference']) . '/confirm')) ?>"
                  onsubmit="return confirm('Confirm <?= e($symbol . number_format((int)$p['amount'])) ?> received for <?= e($p['reference']) ?>? This marks the place paid and emails the receipt.')">
              <?= Csrf::field() ?>
              <input type="hidden" name="note" value="Bank transfer confirmed in console">
              <button class="btn btn--primary btn--sm">Confirm payment</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
