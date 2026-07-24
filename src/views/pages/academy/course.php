<?php /** @var array $course @var array $syllabus @var array|null $track @var bool $enrolled */ ?>
<section class="section">
  <div class="wrap grid-2" style="align-items:start">
    <div>
      <a class="pill" href="<?= e(url('/academy')) ?>">← All courses</a>
      <div class="flex mt-4" style="gap:8px;flex-wrap:wrap">
        <span class="pill pill--red"><?= e($course['level']) ?></span>
        <span class="pill">Age <?= e($course['age_range']) ?></span>
        <span class="pill"><?= (int)$course['weeks'] ?> weeks</span>
        <?php if ($track): ?><span class="pill"><?= e($track['name']) ?></span><?php endif; ?>
      </div>
      <h1 style="font-size:var(--fs-h1);margin-top:16px"><?= e($course['title']) ?></h1>
      <p style="font-size:var(--fs-lg)"><?= e($course['summary']) ?></p>
      <?php if (!empty($course['body'])): ?><p><?= e($course['body']) ?></p><?php endif; ?>

      <?php if ($syllabus): ?>
        <h2 class="mt-6" style="font-size:var(--fs-h2)">Syllabus</h2>
        <div class="tracks mt-4">
          <?php foreach ($syllabus as $i => $s): ?>
            <div class="track-row" style="cursor:default">
              <div class="track-row__num"><?= sprintf('%02d', $i + 1) ?></div>
              <div>
                <div class="track-row__name" style="font-size:var(--fs-h3)"><?= e($s['title'] ?? '') ?></div>
                <div class="track-row__desc"><?= e($s['desc'] ?? '') ?></div>
              </div>
              <span></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="card" style="position:sticky;top:88px">
      <div class="stat"><div class="n"><?= e(naira((int)$course['price_naira'])) ?></div><div class="l">Course fee</div></div>
      <ul class="card__list mt-4">
        <li><?= (int)$course['weeks'] ?> weeks, hands-on</li>
        <li>Ages <?= e($course['age_range']) ?></li>
        <li>Instructor: <?= e($course['instructor'] ?: 'Afrotech Faculty') ?></li>
        <li>Certificate of completion</li>
      </ul>
      <div class="card__foot">
        <?php if ($enrolled): ?>
          <a class="btn btn--ink btn--block" href="<?= e(url('/dashboard')) ?>">Go to My Learning ✓</a>
        <?php elseif (StudentAuth::check()): ?>
          <form method="post" action="<?= e(url('/api/enroll')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="course_id" value="<?= (int)($course['id'] ?? 0) ?>">
            <button class="btn btn--primary btn--block">Enrol in this course</button>
          </form>
        <?php else: ?>
          <a class="btn btn--primary btn--block" href="<?= e(url('/login')) ?>">Sign in to enrol</a>
          <a class="btn btn--ghost btn--block mt-2" href="<?= e(url('/summer')) ?>">Or register for Summer School</a>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>
