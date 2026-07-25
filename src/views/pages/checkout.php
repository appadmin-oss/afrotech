<?php /** @var array $reg @var int $fee @var bool $paid @var bool $gateway @var string $bankDetails */
$sym = Setting::get('currency_symbol', '₦');
$err = flash_pop('checkout_err');
?>
<section class="section">
  <div class="wrap" style="max-width:560px">
    <a class="pill" href="<?= e(url('/summer')) ?>">← Summer School</a>
    <h1 style="font-size:var(--fs-h1);margin-top:16px">Complete payment</h1>
    <p class="muted mono"><?= e($reg['reg_code']) ?> · <?= e($reg['student_name']) ?> · <?= e($reg['track_name']) ?></p>

    <?php if ($paid): ?>
      <div class="alert alert--ok mt-6">This registration is already paid. 🎉 Nothing more to do — see you in class!</div>
      <a class="btn btn--primary mt-4" href="<?= e(url('/')) ?>">Back home</a>
    <?php elseif (Setting::deadlinePassed()): ?>
      <div class="alert alert--err mt-6">Registration has closed for this cohort. Please <a href="<?= e(url('/contact')) ?>">contact us</a> about the next intake.</div>
    <?php else: ?>
      <?php if ($err): ?><div class="alert alert--err mt-6"><?= e($err) ?></div><?php endif; ?>
      <div class="card mt-6">
        <div class="flex-between"><span class="muted">Program fee</span><span class="mono" id="ck-base"><?= e($sym . number_format($fee)) ?></span></div>
        <div class="flex-between mt-2" id="ck-disc-row" style="display:none;color:var(--red)"><span>Discount <span id="ck-disc-label" class="muted"></span></span><span class="mono" id="ck-disc">−</span></div>
        <hr style="border:0;border-top:1px solid var(--hair);margin:16px 0">
        <div class="flex-between"><span style="font-weight:700">Total due</span><span class="mono" id="ck-total" style="font-size:var(--fs-h3);color:var(--ink);font-weight:700"><?= e($sym . number_format($fee)) ?></span></div>

        <form class="form mt-6" method="post" action="<?= e(url('/summer/pay/' . $reg['reg_code'])) ?>">
          <?= Csrf::field() ?>
          <div class="field">
            <label>Discount code <span class="muted">(optional)</span></label>
            <div class="flex" style="gap:8px">
              <input type="text" name="discount_code" id="ck-code" style="text-transform:uppercase" placeholder="EARLYBIRD">
              <button type="button" class="btn btn--ghost btn--sm" id="ck-apply">Apply</button>
            </div>
            <div class="hint" id="ck-msg"></div>
          </div>
          <button class="btn btn--primary btn--lg btn--block">
            <?= $gateway ? 'Pay securely with Paystack' : 'Get payment instructions' ?>
          </button>
        </form>
        <p class="muted center mt-4" style="font-size:var(--fs-xs)">
          <?= $gateway ? 'Secure card / bank / USSD payment via Paystack.' : 'Online payment is being set up — you’ll receive bank transfer details.' ?>
        </p>
      </div>

      <script type="application/json" id="ck-cfg"><?= json_encode(['quote'=>url('/api/checkout/quote'),'csrf'=>Csrf::token(),'sym'=>$sym]) ?></script>
    <?php endif; ?>
  </div>
</section>
