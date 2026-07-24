<?php ob_start(); ?>
<h1 style="font-size:20px;margin:0 0 12px;">Your operator account is ready</h1>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Hi <?= e($name ?? '') ?>,</p>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">An Afrotech Academy operator account has been created for you.</p>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;margin:12px 0;">
  <tr><td style="color:#837D72;width:120px;">Staff ID</td><td style="font-family:monospace;font-weight:bold;"><?= e($staff_id ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Username</td><td><?= e($username ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Role</td><td><?= e(Rbac::label($role ?? 'viewer')) ?></td></tr>
</table>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Sign in with the temporary password you were given, then change it. Access is role-based — you'll only see the areas your role permits.</p>
<p style="margin-top:16px;"><a href="<?= e(url('/admin/login')) ?>" style="background:#E4022B;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:bold;font-size:14px;">Sign in →</a></p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
