<?php
/** @var array $reg @var array $payment */
$sym = Setting::get('currency_symbol', '₦');
ob_start(); ?>
<h1 style="font-size:22px;margin:0 0 8px;">Payment received ✅</h1>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Thank you — we've received your payment and <strong style="color:#131313;"><?= e($reg['student_name'] ?? 'your child') ?></strong>'s place is confirmed.</p>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;margin:12px 0;">
  <tr><td style="color:#837D72;width:150px;">Reference</td><td style="font-family:monospace;font-weight:bold;"><?= e($reg['reg_code'] ?? $payment['reference']) ?></td></tr>
  <?php if (!empty($reg['track_name'])): ?><tr><td style="color:#837D72;">Track</td><td><?= e($reg['track_name']) ?></td></tr><?php endif; ?>
  <?php if ((int)($payment['discount_amount'] ?? 0) > 0): ?>
    <tr><td style="color:#837D72;">Fee</td><td><?= e($sym . number_format((int)$payment['base_amount'])) ?></td></tr>
    <tr><td style="color:#837D72;">Discount<?= !empty($payment['discount_code']) ? ' ('.e($payment['discount_code']).')' : '' ?></td><td style="color:#E4022B;">−<?= e($sym . number_format((int)$payment['discount_amount'])) ?></td></tr>
  <?php endif; ?>
  <tr><td style="color:#837D72;">Amount paid</td><td style="font-weight:bold;color:#E4022B;"><?= e($sym . number_format((int)($payment['amount'] ?? 0))) ?></td></tr>
</table>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">We'll be in touch with campus, dates, and what to bring. Keep this receipt for your records.</p>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">See you in class,<br>The Afrotech Academy team</p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
