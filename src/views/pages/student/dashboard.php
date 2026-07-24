<?php /** @var array $me @var array $enrollments @var array $suggested */ ?>
<section class="section">
  <div class="wrap">
    <div class="flex-between">
      <div>
        <span class="eyebrow">My Learning</span>
        <h1 style="font-size:var(--fs-h1)">Hi, <?= e(explode(' ', $me['name'])[0]) ?> 👋</h1>
        <p class="mono muted"><?= e($me['student_id'] ?? '') ?></p>
      </div>
      <form method="post" action="<?= e(url('/logout')) ?>"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Sign out</button></form>
    </div>

    <h2 class="mt-6" style="font-size:var(--fs-h2)">Your courses</h2>
    <?php if (!$enrollments): ?>
      <div class="card center mt-4"><p>You're not enrolled in any courses yet.</p><a class="btn btn--primary mt-4" href="<?= e(url('/academy')) ?>">Browse the catalog</a></div>
    <?php else: ?>
    <div class="card-grid mt-4">
      <?php foreach ($enrollments as $e): ?>
        <article class="card">
          <span class="pill pill--red"><?= e($e['level'] ?? 'Course') ?></span>
          <h3 style="margin-top:12px"><?= e($e['title']) ?></h3>
          <p><?= e($e['summary'] ?? '') ?></p>
          <div class="card__foot">
            <div class="flex-between"><span class="mono muted">Progress</span><span class="mono"><?= (int)$e['progress'] ?>%</span></div>
            <div style="height:8px;background:var(--card-2);border-radius:99px;overflow:hidden;margin-top:6px">
              <div style="height:100%;width:<?= (int)$e['progress'] ?>%;background:var(--red)"></div>
            </div>
            <a class="btn btn--light btn--sm btn--block mt-4" href="<?= e(url('/academy/' . $e['slug'])) ?>">Open course</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($suggested): ?>
    <h2 class="mt-6" style="font-size:var(--fs-h2)">Recommended for you</h2>
    <div class="card-grid mt-4">
      <?php foreach ($suggested as $c): ?>
        <article class="card">
          <h3><?= e($c['title']) ?></h3>
          <p><?= e($c['summary']) ?></p>
          <div class="card__foot"><a class="btn btn--light btn--sm btn--block" href="<?= e(url('/academy/' . $c['slug'])) ?>">View course</a></div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
