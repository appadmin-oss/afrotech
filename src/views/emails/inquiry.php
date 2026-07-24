<?php ob_start(); ?>
<h1 style="font-size:20px;margin:0 0 12px;">New enquiry</h1>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;">
  <tr><td style="color:#837D72;width:100px;">Name</td><td><?= e($name ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Email</td><td><?= e($email ?? '') ?></td></tr>
  <?php if (!empty($phone)): ?><tr><td style="color:#837D72;">Phone</td><td><?= e($phone) ?></td></tr><?php endif; ?>
  <tr><td style="color:#837D72;vertical-align:top;">Message</td><td><?= nl2br(e($message ?? '')) ?></td></tr>
</table>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
