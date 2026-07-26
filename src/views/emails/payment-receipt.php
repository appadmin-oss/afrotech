<?php
/** @var array $reg @var array $payment @var array $addonRows @var string $receiptUrl */
$sym = Setting::get('currency_symbol', '₦');
$addonRows = $addonRows ?? [];
$addonTotal = 0;
foreach ($addonRows as [, $amt]) $addonTotal += (int)$amt;
// The base amount includes any add-ons the form priced, so back them out to
// show the programme fee on its own line.
$programmeFee = max(0, (int)($payment['base_amount'] ?? 0) - $addonTotal);
ob_start(); ?>
<h1 style="font-size:22px;margin:0 0 8px;">Payment received ✅</h1>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Thank you — we've received your payment and <strong style="color:#131313;"><?= e($reg['student_name'] ?? 'your child') ?></strong>'s place is confirmed.</p>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;margin:12px 0;">
  <tr><td style="color:#837D72;width:150px;">Reference</td><td style="font-family:monospace;font-weight:bold;"><?= e($reg['reg_code'] ?? $payment['reference']) ?></td></tr>
  <?php if (!empty($reg['track_name'])): ?><tr><td style="color:#837D72;">Track</td><td><?= e($reg['track_name']) ?></td></tr><?php endif; ?>
  <tr><td style="color:#837D72;">Programme fee</td><td><?= e($sym . number_format($programmeFee)) ?></td></tr>
  <?php foreach ($addonRows as [$label, $amount]): ?>
    <tr><td style="color:#837D72;"><?= e($label) ?></td><td><?= e($sym . number_format((int)$amount)) ?></td></tr>
  <?php endforeach; ?>
  <?php if ((int)($payment['discount_amount'] ?? 0) > 0): ?>
    <tr><td style="color:#837D72;">Discount<?= !empty($payment['discount_code']) ? ' ('.e($payment['discount_code']).')' : '' ?></td><td style="color:#E4022B;">−<?= e($sym . number_format((int)$payment['discount_amount'])) ?></td></tr>
  <?php endif; ?>
  <tr><td style="color:#837D72;border-top:2px solid #0E0E0E;padding-top:10px;"><strong>Amount paid</strong></td><td style="font-weight:bold;color:#E4022B;border-top:2px solid #0E0E0E;padding-top:10px;"><?= e($sym . number_format((int)($payment['amount'] ?? 0))) ?></td></tr>
</table>
<?php if (!empty($receiptUrl)): ?>
<p style="margin:18px 0;">
  <a href="<?= e($receiptUrl) ?>" style="background:#E4022B;color:#fff;text-decoration:none;padding:11px 20px;border-radius:999px;font-weight:bold;font-size:14px;">View or print your receipt →</a>
</p>
<p style="color:#837D72;font-size:12px;line-height:1.6;">Your receipt stays available at this address, so you can come back to it or forward it whenever proof of payment is asked for.</p>
<?php endif; ?>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">We'll be in touch with campus, dates, and what to bring. Keep this receipt for your records.</p>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">See you in class,<br>The Afrotech Academy team</p>
<?php $inner = ob_get_clean(); require __DIR__ . '/_shell.php';
