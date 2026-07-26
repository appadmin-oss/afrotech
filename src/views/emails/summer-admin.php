<?php ob_start(); ?>
<h1 style="font-size:20px;margin:0 0 12px;">New summer registration</h1>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;border-collapse:collapse;">
  <tr><td style="color:#837D72;width:140px;">Reference</td><td style="font-family:monospace;font-weight:bold;"><?= e($reg_code ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Student</td><td><?= e($student_name ?? '') ?> (age <?= e($student_age ?? '') ?>)</td></tr>
  <tr><td style="color:#837D72;">Track</td><td><?= e($track_name ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Guardian</td><td><?= e($guardian_name ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Email</td><td><?= e($email ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Phone</td><td><?= e($phone ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Campus</td><td><?= e($location_pref ?? '—') ?></td></tr>
  <tr><td style="color:#837D72;">Experience</td><td><?= e($experience ?? 'none') ?></td></tr>
  <?php if (!empty($notes)): ?><tr><td style="color:#837D72;">Notes</td><td><?= nl2br(e($notes)) ?></td></tr><?php endif; ?>
  <?php if (!empty($addons)): ?>
  <tr><td style="color:#837D72;">Add-ons</td><td><?= e(Setting::money((int)$addons)) ?> · total <strong><?= e(Setting::money((int)($total ?? 0))) ?></strong></td></tr>
  <?php endif; ?>
</table>

<?php
/* Every question the live form asked, minus anything flagged sensitive —
   those stay inside the console rather than being copied into an inbox. */
$extra = array_values(array_filter($answer_rows ?? [], fn($r) => !in_array($r['key'], [
    'student_name','student_age','guardian_name','email','phone','track_slug','location_pref','experience','notes',
], true)));
?>
<?php if ($extra): ?>
<h2 style="font-size:15px;margin:20px 0 6px;color:#131313;">Form answers</h2>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;border-collapse:collapse;">
  <?php foreach ($extra as $row): ?>
  <tr>
    <td style="color:#837D72;width:180px;vertical-align:top;"><?= e($row['label']) ?></td>
    <td><?= nl2br(e($row['value'])) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<p style="margin-top:16px;"><a href="<?= e(url('/admin/summer')) ?>" style="background:#E4022B;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:bold;font-size:14px;">Open in console →</a></p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
