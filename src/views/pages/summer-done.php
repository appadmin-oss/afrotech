<?php /** @var string $code @var string $message @var array $data */ ?>
<section class="section">
  <div class="wrap" style="max-width:640px">
    <div class="receipt">
      <span class="eyebrow" style="justify-content:center">Registration received</span>
      <h1 style="font-size:var(--fs-h1);margin-top:12px">You're registered! 🎉</h1>
      <div class="receipt__code"><?= e($code) ?></div>
      <p><?= e($message) ?></p>
      <div class="flex" style="justify-content:center;gap:12px;margin-top:24px">
        <a class="btn btn--primary" href="<?= e(url('/academy')) ?>">Explore courses</a>
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Back home</a>
      </div>
    </div>
    <p class="muted center mt-6" style="font-size:var(--fs-sm)">Keep your reference code safe — quote it when you contact us on <?= e(AFT_PHONE) ?>.</p>
  </div>
</section>
