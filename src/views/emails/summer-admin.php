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
</table>
<p style="margin-top:16px;"><a href="<?= e(url('/admin/summer')) ?>" style="background:#E4022B;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:bold;font-size:14px;">Open in console →</a></p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
