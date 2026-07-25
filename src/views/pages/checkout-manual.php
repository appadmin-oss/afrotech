<?php /** @var array $reg @var array|null $payment @var string $bankDetails */
$sym = Setting::get('currency_symbol', '₦');
$amount = $payment ? (int)$payment['amount'] : Setting::fee();
?>
<section class="section">
  <div class="wrap" style="max-width:560px">
    <a class="pill" href="<?= e(url('/summer/pay/' . $reg['reg_code'])) ?>">← Back</a>
    <h1 style="font-size:var(--fs-h1);margin-top:16px">Pay by bank transfer</h1>
    <p>Transfer <strong><?= e($sym . number_format($amount)) ?></strong> using the details below, then send your proof of payment on WhatsApp quoting your reference code.</p>
    <div class="receipt mt-4" style="text-align:left">
      <div class="muted mono" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.12em">Amount</div>
      <div class="starburst__fee" style="color:var(--red);font-size:var(--fs-h1)"><?= e($sym . number_format($amount)) ?></div>
      <div class="muted mono mt-4" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.12em">Reference</div>
      <div class="receipt__code" style="margin-top:6px"><?= e($reg['reg_code']) ?></div>
      <div class="muted mono mt-4" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.12em">Bank details</div>
      <p style="margin-top:6px"><?= nl2br(e($bankDetails)) ?></p>
    </div>
    <a class="btn btn--primary btn--lg btn--block mt-6" href="https://wa.me/<?= e(preg_replace('/\D/','',Setting::get('whatsapp_phone'))) ?>?text=<?= rawurlencode('Payment proof for '.$reg['reg_code']) ?>">Send proof on WhatsApp</a>
  </div>
</section>
