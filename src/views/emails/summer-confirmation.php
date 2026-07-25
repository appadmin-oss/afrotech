<?php
/** @var array vars: student_name, guardian_name, track_name, reg_code, email, phone */
ob_start(); ?>
<h1 style="font-size:22px;margin:0 0 8px;">You're registered! 🎉</h1>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Hi <?= e($guardian_name ?? 'there') ?>,</p>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">
  Thank you for registering <strong style="color:#131313;"><?= e($student_name ?? '') ?></strong>
  for the Afrotech Academy Summer School — <strong style="color:#131313;"><?= e($track_name ?? 'Summer School') ?></strong> track.
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
  <tr><td align="center" style="border:1px dashed #E4022B;border-radius:12px;padding:16px;background:#fdecee;">
    <div style="font-size:12px;letter-spacing:2px;color:#A80016;text-transform:uppercase;">Your reference code</div>
    <div style="font-family:'Courier New',monospace;font-size:24px;font-weight:bold;color:#E4022B;letter-spacing:2px;margin-top:6px;"><?= e($reg_code ?? '') ?></div>
  </td></tr>
</table>
<p style="color:#4A4741;font-size:15px;line-height:1.6;"><strong>What happens next:</strong></p>
<ul style="color:#4A4741;font-size:15px;line-height:1.7;padding-left:20px;">
  <li>Our team will contact you on <strong><?= e($phone ?? '') ?></strong> to confirm your child's place.</li>
  <li>Complete the <strong><?= e(Setting::money(Setting::fee())) ?></strong> program fee at secure checkout (card, bank, USSD, or transfer).</li>
  <li>You'll receive campus, dates, and what-to-bring information before the program starts.</li>
</ul>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Please keep your reference code — quote it whenever you contact us.</p>
<p style="color:#4A4741;font-size:15px;line-height:1.6;">Warmly,<br>The Afrotech Academy team</p>
<?php $inner = ob_get_clean(); $emailTitle = 'Registration received';
require __DIR__ . '/_shell.php';
