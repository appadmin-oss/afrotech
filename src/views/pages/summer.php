<?php
/** @var array $tracks @var array $content @var array $def
 *  @var array $errors @var array $old */
$errors   = $errors ?? [];
$old      = $old ?? [];
$settings = $def['settings'] ?? FormEngine::normalizeSettings([]);
$fee      = Setting::fee();
$feeLabel = Setting::money($fee);
$age      = Setting::get('age_label', 'Age 7+');
$deadline = Setting::deadline();
$closed   = Setting::deadlinePassed();
$flashErr = flash_pop('summer_err');
$steps    = FormEngine::stepCount(FormEngine::resolve($def['fields'] ?? []));
?>
<section class="hero" style="padding-block:clamp(40px,6vw,72px)">
  <div class="binary-field" data-binary data-words="REGISTER,SUMMER,AFROTECH" data-density="1" aria-hidden="true"></div>
  <div class="wrap hero__inner" style="grid-template-columns:1.1fr .9fr">
    <div data-reveal>
      <span class="eyebrow">Summer School · <?= e($age) ?></span>
      <h1 class="hero__wordmark" style="font-size:clamp(38px,6vw,72px)">Register<span>for summer</span></h1>
      <p class="hero__lede"><?= e($content['summer_intro'] ?? '') ?></p>
      <div class="hero__meta">
        <div><div class="k"><?= e($feeLabel) ?></div><div class="l">Per learner</div></div>
        <div><div class="k">4 weeks</div><div class="l">Per track</div></div>
        <?php if ($deadline && !$closed): ?>
        <div><div class="k mono" data-countdown="<?= e(date('c', $deadline)) ?>" style="font-size:var(--fs-h3)"></div><div class="l">Closes in</div></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero__aside" data-reveal><?php partial('starburst'); ?></div>
  </div>
</section>

<section class="section--tight">
  <div class="wrap">
    <div class="card" style="max-width:760px;margin-inline:auto;padding:clamp(24px,4vw,48px)">
      <?php if ($closed): ?>
        <div class="section-head" style="margin-bottom:0">
          <span class="eyebrow" style="color:var(--red)">Registration closed</span>
          <h2 style="font-size:var(--fs-h2)">This cohort is full</h2>
          <p><?= $settings['closedMessage'] !== ''
                ? e($settings['closedMessage'])
                : 'Registration for the current cohort has closed.' ?>
             <a href="<?= e(url('/contact')) ?>" style="color:var(--red);font-weight:600">Contact us</a>
             to join the waiting list for the next intake.</p>
        </div>
      <?php else: ?>
      <div data-success>
        <div class="section-head" style="margin-bottom:24px">
          <span class="eyebrow">Registration form</span>
          <h2 style="font-size:var(--fs-h2)"><?= e($settings['introTitle']) ?></h2>
          <p><?= $settings['introBody'] !== ''
                ? e($settings['introBody'])
                : "Takes two minutes. You'll go straight to secure payment — apply any discount code there." ?></p>
          <?php if ($steps > 1): ?>
            <p class="muted" style="font-size:var(--fs-xs);margin-top:var(--sp-2)">
              <?= (int)$steps ?> short steps · your answers decide which questions you see.
            </p>
          <?php endif; ?>
        </div>

        <?php if ($flashErr): ?>
          <div class="alert alert--err" style="margin-bottom:var(--sp-4)"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <?= FormRenderer::form($def, [
              'action'  => url('/api/summer/register'),
              'errors'  => $errors,
              'old'     => $old,
              'fee'     => $fee,
              'symbol'  => Setting::get('currency_symbol', '₦'),
              'prefill' => $_GET,
            ]) ?>

        <p class="muted center" style="font-size:var(--fs-xs);margin-top:var(--sp-4)">
          Secure checkout next — pay online (card / bank / USSD) or by transfer. Discount codes applied at checkout.
        </p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
