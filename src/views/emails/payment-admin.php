<?php
/**
 * Academy-inbox notification that a place is now actually paid for.
 * Registrar staffing and campus lists run off paid registrations, not
 * submitted ones, so this is the event that matters operationally.
 *
 * @var array $reg @var array $payment @var array $addonRows @var string $receiptUrl @var string $via
 */
$sym = Setting::get('currency_symbol', '₦');
$viaLabel = [
    'callback' => 'Gateway (parent returned to the site)',
    'webhook'  => 'Gateway webhook',
    'operator' => 'Confirmed by hand in the console',
    'manual'   => 'Bank transfer',
][$via ?? 'callback'] ?? 'Gateway';
ob_start(); ?>
<h1 style="font-size:20px;margin:0 0 12px;">Payment confirmed ✅</h1>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;border-collapse:collapse;">
  <tr><td style="color:#837D72;width:150px;">Amount</td><td style="font-weight:bold;color:#E4022B;"><?= e($sym . number_format((int)($payment['amount'] ?? 0))) ?></td></tr>
  <tr><td style="color:#837D72;">Receipt ref</td><td style="font-family:monospace;"><?= e($payment['reference'] ?? '') ?></td></tr>
  <?php if (!empty($reg['reg_code'])): ?>
  <tr><td style="color:#837D72;">Registration</td><td style="font-family:monospace;font-weight:bold;"><?= e($reg['reg_code']) ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($reg['student_name'])): ?>
  <tr><td style="color:#837D72;">Student</td><td><?= e($reg['student_name']) ?><?= !empty($reg['student_age']) ? ' (age ' . (int)$reg['student_age'] . ')' : '' ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($reg['track_name'])): ?>
  <tr><td style="color:#837D72;">Track</td><td><?= e($reg['track_name']) ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($reg['location_pref'])): ?>
  <tr><td style="color:#837D72;">Campus</td><td><?= e($reg['location_pref']) ?></td></tr>
  <?php endif; ?>
  <tr><td style="color:#837D72;">Paid by</td><td><?= e($payment['email'] ?? '') ?></td></tr>
  <tr><td style="color:#837D72;">Route</td><td><?= e($viaLabel) ?></td></tr>
  <?php if (!empty($payment['discount_code'])): ?>
  <tr><td style="color:#837D72;">Discount</td><td><?= e($payment['discount_code']) ?> (−<?= e($sym . number_format((int)$payment['discount_amount'])) ?>)</td></tr>
  <?php endif; ?>
  <?php if (!empty($payment['confirmation_note'])): ?>
  <tr><td style="color:#837D72;">Operator note</td><td><?= nl2br(e($payment['confirmation_note'])) ?></td></tr>
  <?php endif; ?>
</table>

<?php if (!empty($addonRows)): ?>
<h2 style="font-size:15px;margin:18px 0 4px;color:#131313;">Add-ons to arrange</h2>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;color:#131313;border-collapse:collapse;">
  <?php foreach ($addonRows as [$label, $amount]): ?>
  <tr><td style="color:#837D72;width:220px;"><?= e($label) ?></td><td><?= e($sym . number_format((int)$amount)) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p style="margin-top:18px;">
  <a href="<?= e(url('/admin/summer')) ?>" style="background:#E4022B;color:#fff;text-decoration:none;padding:10px 18px;border-radius:999px;font-weight:bold;font-size:14px;">Open registrations →</a>
</p>
<p style="color:#837D72;font-size:12px;">Family receipt: <a href="<?= e($receiptUrl ?? '') ?>" style="color:#E4022B;"><?= e($receiptUrl ?? '') ?></a></p>
<?php $inner = ob_get_clean(); $emailTitle = 'Payment confirmed';
require __DIR__ . '/_shell.php';
