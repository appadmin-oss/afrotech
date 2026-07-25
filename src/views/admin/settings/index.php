<?php /** @var array $values @var array $groups @var string|null $saved */
$labels = [
  'program_name'=>'Program name','age_label'=>'Age label (shown on flier/hero)','min_age'=>'Minimum age (number)',
  'cohort_label'=>'Cohort label','cohort_start'=>'Cohort start date','seats_total'=>'Total seats',
  'summer_fee'=>'Summer fee (number, no symbol)','currency_symbol'=>'Currency symbol','currency_code'=>'Currency code (e.g. NGN)',
  'payment_enabled'=>'Online payment enabled','payment_provider'=>'Payment provider','bank_transfer_details'=>'Bank transfer details (manual fallback)',
  'registration_deadline'=>'Registration deadline (YYYY-MM-DD HH:MM)','whatsapp_phone'=>'WhatsApp / phone','contact_email'=>'Contact email',
];
?>
<div class="topbar"><div><span class="eyebrow">Configuration</span><h1>Settings</h1></div></div>
<?php if ($saved): ?><div class="alert alert--ok" style="margin-bottom:16px"><?= e($saved) ?></div><?php endif; ?>
<?php if (!Database::available()): ?><div class="alert alert--err" style="margin-bottom:16px">Database not connected — edits won't persist. The site uses the built-in defaults.</div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/settings/save')) ?>">
  <?= Csrf::field() ?>
  <?php foreach ($groups as $group => $keys): ?>
    <div class="panel">
      <div class="panel__head"><h2><?= e($group) ?></h2></div>
      <div class="form">
        <?php foreach ($keys as $k): $v = $values[$k] ?? ''; ?>
          <div class="field">
            <label><?= e($labels[$k] ?? $k) ?> <span class="muted mono" style="font-size:11px"><?= e($k) ?></span></label>
            <?php if ($k === 'payment_enabled'): ?>
              <select name="<?= e($k) ?>"><option value="1" <?= $v==='1'?'selected':'' ?>>Enabled</option><option value="0" <?= $v!=='1'?'selected':'' ?>>Disabled (manual only)</option></select>
            <?php elseif ($k === 'bank_transfer_details'): ?>
              <textarea name="<?= e($k) ?>" style="min-height:70px"><?= e($v) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button class="btn btn--primary">Save settings</button>
</form>
