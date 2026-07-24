<?php /** @var array $courses @var array $tracks */ ?>
<section class="section">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Course catalog</span>
      <h1 style="font-size:var(--fs-h1)">Learn with Afrotech Academy</h1>
      <p>Hands-on, project-based courses across our six tracks. Enrol, learn, and build.</p>
    </div>

    <?php if (!$courses): ?>
      <div class="card center"><p>Our catalog is being prepared. Check back soon — or <a href="<?= e(url('/summer')) ?>">register for Summer School</a>.</p></div>
    <?php else: ?>
    <div class="card-grid">
      <?php foreach ($courses as $c): ?>
        <article class="card">
          <div class="card__icon"><?php partial('icon', ['name' => 'chip']); ?></div>
          <div class="flex" style="gap:8px;flex-wrap:wrap">
            <span class="pill pill--red"><?= e($c['level']) ?></span>
            <span class="pill">Age <?= e($c['age_range']) ?></span>
          </div>
          <h3 style="margin-top:12px"><?= e($c['title']) ?></h3>
          <p><?= e($c['summary']) ?></p>
          <div class="card__foot flex-between">
            <span class="mono muted"><?= (int)$c['weeks'] ?> weeks · <?= e(naira((int)$c['price_naira'])) ?></span>
            <a class="btn btn--light btn--sm" href="<?= e(url('/academy/' . $c['slug'])) ?>">View course</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
