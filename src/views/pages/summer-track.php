<?php /** @var array $track @var array $tracks */ ?>
<section class="section">
  <div class="wrap" style="max-width:820px">
    <a class="pill" href="<?= e(url('/summer')) ?>">← All tracks</a>
    <div class="card__icon mt-6"><?php partial('icon', ['name' => $track['icon'] ?? 'chip']); ?></div>
    <span class="eyebrow">Summer School Track</span>
    <h1 style="font-size:var(--fs-h1);margin-top:8px"><?= e($track['name']) ?></h1>
    <p style="font-size:var(--fs-lg)"><?= e($track['summary'] ?? '') ?></p>

    <?php if (!empty($track['outcomes'])): ?>
    <div class="card mt-6">
      <h3>What your child will be able to do</h3>
      <ul class="card__list">
        <?php foreach ($track['outcomes'] as $o): ?><li><?= e($o) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="flex mt-6" style="gap:12px">
      <a class="btn btn--primary btn--lg" href="<?= e(url('/summer')) ?>">Register for <?= e($track['name']) ?></a>
      <span class="pill pill--red"><?= e(Setting::money(Setting::fee())) ?> · <?= e(Setting::get('age_label')) ?></span>
    </div>
  </div>
</section>
