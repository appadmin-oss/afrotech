<?php /** @var array $r @var array $answerRows @var int $formVersion */
$canManage = Rbac::can('registrations.manage');
$answerRows = $answerRows ?? [];
$addons = max(0, (int)($r['addons_naira'] ?? 0));
?>
<div class="topbar">
  <div><a class="pill" href="<?= e(url('/admin/summer')) ?>">← Registrations</a>
    <h1 class="mt-2"><?= e($r['student_name']) ?></h1>
    <span class="mono muted"><?= e($r['reg_code']) ?></span></div>
  <div class="flex" style="gap:8px">
    <span class="chip chip--<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
    <span class="chip chip--<?= e($r['payment_status']) ?>"><?= e($r['payment_status']) ?></span>
  </div>
</div>

<?php if ($msg = flash_pop('summer_msg')): ?>
  <div class="alert alert--ok" style="margin-bottom:var(--sp-5)"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msgErr = flash_pop('summer_err')): ?>
  <div class="alert alert--err" style="margin-bottom:var(--sp-5)"><?= e($msgErr) ?></div>
<?php endif; ?>

<div class="grid-2" style="align-items:start">
  <div class="panel">
    <h2>Details</h2>
    <table class="data mt-4" style="border:0">
      <tbody>
        <tr><th>Student</th><td><?= e($r['student_name']) ?> (age <?= (int)$r['student_age'] ?>)</td></tr>
        <tr><th>Track</th><td><?= e($r['track_name']) ?></td></tr>
        <tr><th>Guardian</th><td><?= e($r['guardian_name']) ?></td></tr>
        <tr><th>Email</th><td class="mono"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
        <tr><th>Phone</th><td class="mono"><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
        <tr><th>Campus</th><td><?= e($r['location_pref'] ?: '—') ?></td></tr>
        <tr><th>Experience</th><td><?= e($r['experience']) ?></td></tr>
        <tr><th>Fee</th><td><?= e(naira((int)$r['fee_naira'])) ?>
          <?php if ($addons > 0): ?><span class="muted">(includes <?= e(naira($addons)) ?> in add-ons)</span><?php endif; ?>
        </td></tr>
        <tr><th>Registered</th><td><?= e(datetime_pretty($r['created_at'])) ?></td></tr>
        <?php $pay = Payment::forRegistration((int)$r['id']); ?>
        <?php if ($pay): ?>
        <tr>
          <th>Payment</th>
          <td>
            <span class="chip chip--<?= $pay['status'] === 'succeeded' ? 'paid' : ($pay['status'] === 'pending' ? 'pending' : 'cancelled') ?>"><?= e($pay['status']) ?></span>
            <?php if ($pay['status'] === 'succeeded'): ?>
              <a class="mono" style="font-size:var(--fs-xs)" href="<?= e(url('/summer/receipt/' . rawurlencode($pay['reference']))) ?>" target="_blank" rel="noopener">view receipt ↗</a>
            <?php else: ?>
              <span class="mono muted" style="font-size:var(--fs-xs)"><?= e($pay['reference']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr><th>Form version</th><td class="mono">v<?= (int)($formVersion ?? 1) ?></td></tr>
      </tbody>
    </table>
    <?php if (!empty($r['notes'])): ?>
      <h3 class="mt-4" style="font-size:var(--fs-h3)">Notes</h3>
      <p><?= nl2br(e($r['notes'])) ?></p>
    <?php endif; ?>

    <?php if ($answerRows): ?>
      <h3 class="mt-6" style="font-size:var(--fs-h3)">Form answers</h3>
      <p class="muted" style="font-size:var(--fs-xs)">
        Every question the form asked when this family applied — replayed against v<?= (int)($formVersion ?? 1) ?>.
      </p>
      <table class="data mt-4" style="border:0">
        <tbody>
          <?php foreach ($answerRows as $row): ?>
            <tr>
              <th style="white-space:normal"><?= e($row['label']) ?>
                <?php if ($row['sensitive']): ?>
                  <span class="chip chip--waived" title="Sensitive — excluded from exports and emails">sensitive</span>
                <?php endif; ?>
              </th>
              <td style="white-space:normal"><?= nl2br(e($row['value'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Manage</h2>
    <?php if (!$canManage): ?>
      <p class="muted">You have read-only access to registrations.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('/admin/summer/' . $r['id'] . '/status')) ?>" class="form mt-4">
        <?= Csrf::field() ?>
        <div class="field"><label>Registration status</label>
          <select name="status">
            <?php foreach (['pending','confirmed','waitlisted','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--primary btn--sm">Update status</button>
      </form>
      <form method="post" action="<?= e(url('/admin/summer/' . $r['id'] . '/payment')) ?>" class="form mt-6">
        <?= Csrf::field() ?>
        <div class="field"><label>Payment</label>
          <select name="payment_status">
            <?php foreach (['unpaid','paid','waived'] as $s): ?>
              <option value="<?= $s ?>" <?= $r['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn--ink btn--sm">Update payment</button>
      </form>
    <?php endif; ?>
  </div>
</div>
