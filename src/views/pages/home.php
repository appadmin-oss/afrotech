<?php
/** @var array $tracks @var array $courses @var array $content */
?>
<!-- Hero -->
<section class="hero">
  <div class="binary-field" aria-hidden="true">
    <div class="binary-field__static"><?php
      // Static fallback field of 0s/1s (JS layers an animated canvas on top).
      $b = ''; for ($i = 0; $i < 900; $i++) $b .= (($i % 37 && $i % 5) ? (mt_rand(0,1)) : ' ');
      echo e($b);
    ?></div>
  </div>
  <div class="wrap hero__inner">
    <div>
      <span class="eyebrow"><?= e($content['hero_eyebrow']) ?></span>
      <h1 class="hero__wordmark">Afrotech<span>Academy</span></h1>
      <p class="hero__lede"><?= e($content['hero_subtitle']) ?></p>
      <div class="hero__cta">
        <a class="btn btn--primary btn--lg" href="<?= e(url('/summer')) ?>">Register Now <span class="hand">👆</span></a>
        <a class="btn btn--ghost btn--lg" href="<?= e(url('/academy')) ?>">Explore courses</a>
      </div>
      <div class="hero__meta">
        <div><div class="k">6</div><div class="l">Future-ready tracks</div></div>
        <div><div class="k"><?= e(AFT_SUMMER_FEE) ?></div><div class="l">Program fee</div></div>
        <div><div class="k"><?= e(AFT_SUMMER_AGE) ?></div><div class="l">Open to</div></div>
      </div>
    </div>
    <div class="hero__aside"><?php partial('starburst'); ?></div>
  </div>
</section>

<!-- What you'll learn -->
<section class="section" id="tracks">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">What you'll learn</span>
      <h2>Six tracks. One transformative summer.</h2>
      <p>Practical, hands-on learning designed to inspire confidence, creativity, and success — for the next generation of African builders.</p>
    </div>
    <div class="tracks">
      <?php foreach ($tracks as $i => $t): ?>
        <a class="track-row" href="<?= e(url('/summer/track/' . $t['slug'])) ?>">
          <div class="track-row__num"><?= sprintf('%02d', $i + 1) ?></div>
          <div>
            <div class="track-row__name"><?= e($t['name']) ?></div>
            <div class="track-row__desc"><?= e($t['summary'] ?? '') ?></div>
          </div>
          <span class="track-row__go"><?php partial('icon', ['name' => 'arrow']); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Why Afrotech -->
<section class="section band-ink">
  <div class="wrap grid-3">
    <div>
      <div class="card__icon" style="background:rgba(255,255,255,.08);color:#fff"><?php partial('icon',['name'=>'spark']); ?></div>
      <h3>Learn by building</h3>
      <p>Every track ends with something real — a website, a design portfolio, a mini campaign — not just a certificate.</p>
    </div>
    <div>
      <div class="card__icon" style="background:rgba(255,255,255,.08);color:#fff"><?php partial('icon',['name'=>'shield']); ?></div>
      <h3>Safe &amp; age-appropriate</h3>
      <p>Small groups, vetted faculty, and a curriculum shaped for young learners from age 7 upward.</p>
    </div>
    <div>
      <div class="card__icon" style="background:rgba(255,255,255,.08);color:#fff"><?php partial('icon',['name'=>'grid']); ?></div>
      <h3>Future-ready skills</h3>
      <p>Cybersecurity, AI, coding, design, and digital marketing — the literacies that matter for the next decade.</p>
    </div>
  </div>
</section>

<!-- Featured courses -->
<?php if ($courses): ?>
<section class="section">
  <div class="wrap">
    <div class="flex-between section-head" style="max-width:none">
      <div>
        <span class="eyebrow">From the catalog</span>
        <h2>Featured courses</h2>
      </div>
      <a class="btn btn--ghost" href="<?= e(url('/academy')) ?>">All courses</a>
    </div>
    <div class="card-grid">
      <?php foreach ($courses as $c): ?>
        <article class="card">
          <div class="card__icon"><?php partial('icon', ['name' => 'chip']); ?></div>
          <span class="pill pill--red"><?= e($c['level']) ?> · Age <?= e($c['age_range']) ?></span>
          <h3 style="margin-top:12px"><?= e($c['title']) ?></h3>
          <p><?= e($c['summary']) ?></p>
          <div class="card__foot flex-between">
            <span class="mono muted"><?= (int)$c['weeks'] ?> weeks</span>
            <a class="btn btn--light btn--sm" href="<?= e(url('/academy/' . $c['slug'])) ?>">Details</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Location + register CTA -->
<section class="section" id="visit">
  <div class="wrap grid-2">
    <div>
      <span class="eyebrow">Location</span>
      <h2 style="margin-top:8px">Two campuses in Alimosho, Lagos</h2>
      <div class="info-grid mt-6">
        <div class="info">
          <h4><span class="flex" style="gap:6px"><?php partial('icon',['name'=>'pin']); ?> Egbeda</span></h4>
          <p>CACENTRE, 2 Oremeji Street, Off Bakery Bus Stop, Egbeda, Alimosho, Lagos.</p>
        </div>
        <div class="info">
          <h4><span class="flex" style="gap:6px"><?php partial('icon',['name'=>'pin']); ?> Ishefun</span></h4>
          <p>CACENTRE, 18 Camp Davis Road, Orisunbare Phase 2 Bus Stop, Ishefun Road, Alimosho, Lagos.</p>
        </div>
      </div>
      <p class="mt-6 flex" style="gap:8px"><?php partial('icon',['name'=>'phone']); ?> <a href="tel:+2348100191456"><?= e(AFT_PHONE) ?></a></p>
      <p class="flex" style="gap:8px"><?php partial('icon',['name'=>'mail']); ?> <a href="mailto:<?= e(AFT_EMAIL) ?>"><?= e(AFT_EMAIL) ?></a></p>
    </div>
    <div class="card" style="display:flex;flex-direction:column;justify-content:center;background:var(--red);color:#fff;border:0">
      <span class="eyebrow" style="color:rgba(255,255,255,.85)">Summer School</span>
      <h2 style="color:#fff;margin-top:8px">Secure your child's place today.</h2>
      <p style="color:rgba(255,255,255,.92)"><?= e($content['summer_intro']) ?></p>
      <div class="mt-6"><a class="btn btn--light btn--lg" href="<?= e(url('/summer')) ?>">Register Now 👆</a></div>
    </div>
  </div>
</section>
