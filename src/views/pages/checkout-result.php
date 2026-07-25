<?php /** @var bool $ok @var array|null $payment @var array|null $reg */
$sym = Setting::get('currency_symbol', '₦');
?>
<section class="section">
  <div class="wrap" style="max-width:560px">
    <div class="receipt">
      <?php if ($ok): ?>
        <span class="eyebrow" style="justify-content:center">Payment confirmed</span>
        <h1 style="font-size:var(--fs-h1);margin-top:12px">You're all set! 🎉</h1>
        <?php if ($payment): ?><div class="receipt__code"><?= e($payment['reference']) ?></div><?php endif; ?>
        <p><?= $reg ? e($reg['student_name']) . "'s" : 'Your' ?> place is confirmed<?= $payment ? ' — ' . e($sym . number_format((int)$payment['amount'])) . ' received' : '' ?>. A receipt is on its way to your inbox.</p>
        <a class="btn btn--primary mt-4" href="<?= e(url('/')) ?>">Back home</a>
      <?php else: ?>
        <span class="eyebrow" style="justify-content:center;color:var(--red)">Payment not confirmed</span>
        <h1 style="font-size:var(--fs-h1);margin-top:12px">We couldn't confirm that payment</h1>
        <p>If you were charged, don't worry — quote your reference to us and we'll sort it out. Otherwise you can try again.</p>
        <?php if ($reg): ?><a class="btn btn--primary mt-4" href="<?= e(url('/summer/pay/' . $reg['reg_code'])) ?>">Try again</a><?php endif; ?>
        <a class="btn btn--ghost mt-2" href="<?= e(url('/contact')) ?>">Contact us</a>
      <?php endif; ?>
    </div>
  </div>
</section>
