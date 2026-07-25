<?php
/** The flier's spiky "PROGRAM FEE" badge — figures come from Settings. */
$fee = Setting::money(Setting::fee());
$age = Setting::get('age_label', 'Age 7+');
?>
<div class="starburst" role="img" aria-label="Program fee <?= e($fee) ?>, <?= e($age) ?>">
  <svg viewBox="0 0 200 200" aria-hidden="true">
    <?php
      $spikes = 24; $cx = 100; $cy = 100; $rOuter = 98; $rInner = 80; $pts = [];
      for ($i = 0; $i < $spikes * 2; $i++) {
          $r = ($i % 2 === 0) ? $rOuter : $rInner;
          $a = M_PI * $i / $spikes - M_PI / 2;
          $pts[] = round($cx + $r * cos($a), 2) . ',' . round($cy + $r * sin($a), 2);
      }
    ?>
    <polygon points="<?= implode(' ', $pts) ?>" fill="#0E0E0E"/>
    <circle cx="100" cy="100" r="72" fill="none" stroke="#E4022B" stroke-width="2.5" stroke-dasharray="3 5"/>
  </svg>
  <div class="starburst__body">
    <div class="starburst__label">Program Fee</div>
    <div class="starburst__fee"><?= e($fee) ?></div>
    <div class="starburst__age"><?= e($age) ?></div>
  </div>
</div>
