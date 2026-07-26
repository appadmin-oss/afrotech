<?php /** @var bool $ok @var array|null $payment @var array|null $reg @var bool $pending */
$sym = Setting::get('currency_symbol', '₦');
// "Pending" is its own outcome, not a failure: we couldn't reach the gateway to
// verify, or the webhook hasn't landed yet. Telling a parent who just paid that
// their payment failed is worse than telling them we're still checking.
$pending = $pending ?? false;
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
      <?php elseif ($pending): ?>
        <span class="eyebrow" style="justify-content:center">Payment pending</span>
        <h1 style="font-size:var(--fs-h1);margin-top:12px">We're still confirming this one</h1>
        <?php if ($payment): ?><div class="receipt__code"><?= e($payment['reference']) ?></div><?php endif; ?>
        <p>
          Your payment may well have gone through — we just couldn't confirm it with the bank in time.
          Nothing is lost: confirmations also reach us automatically, and this page will show your receipt
          once it does. <strong>Don't pay twice.</strong>
        </p>
        <?php if ($payment): ?>
          <a class="btn btn--primary mt-4" href="<?= e(url('/summer/receipt/' . rawurlencode($payment['reference']))) ?>">Check again</a>
        <?php endif; ?>
        <a class="btn btn--ghost mt-2" href="<?= e(url('/contact')) ?>">Talk to us</a>
      <?php else: ?>
        <span class="eyebrow" style="justify-content:center;color:var(--red)">Payment not confirmed</span>
        <h1 style="font-size:var(--fs-h1);margin-top:12px">We couldn't confirm that payment</h1>
        <?php if ($payment): ?><div class="receipt__code"><?= e($payment['reference']) ?></div><?php endif; ?>
        <p>The bank didn't complete this charge, so you have not been billed. You can try again, or pay by transfer instead.</p>
        <?php if ($reg): ?><a class="btn btn--primary mt-4" href="<?= e(url('/summer/pay/' . $reg['reg_code'])) ?>">Try again</a><?php endif; ?>
        <a class="btn btn--ghost mt-2" href="<?= e(url('/contact')) ?>">Contact us</a>
      <?php endif; ?>
    </div>
  </div>
</section>
