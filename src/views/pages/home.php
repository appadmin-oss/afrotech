<?php
/** @var array $tracks @var array $courses @var array $content */
$fee = Setting::money(Setting::fee());
$age = Setting::get('age_label', 'Age 7+');
$deadline = Setting::deadline();
$trackWords = implode(',', array_map(fn($t) => strtoupper(preg_replace('/[^A-Za-z]/', '', $t['name'])), $tracks));
?>
<!-- Hero (general academy — not summer-specific) -->
<section class="hero">
  <div class="binary-field" data-binary data-words="AFROTECH,ACADEMY,<?= e($trackWords) ?>" data-density="1.1" aria-hidden="true"></div>
  <div class="wrap hero__inner">
    <div data-reveal>
      <span class="eyebrow">Afrostrength · Youth Technology Academy</span>
      <h1 class="hero__wordmark">Afrotech<span>Academy</span></h1>
      <p class="hero__lede">Future-ready technology skills for the next generation of African builders. Cybersecurity, AI, coding, design, and more — taught hands-on and project-first, from age <?= (int) Setting::int('min_age', 7) ?> up.</p>
      <div class="hero__cta">
        <a class="btn btn--primary btn--lg" href="<?= e(url('/academy')) ?>">Explore courses</a>
        <a class="btn btn--ghost btn--lg" href="<?= e(url('/summer')) ?>">Summer School</a>
      </div>
      <div class="hero__meta">
        <div><div class="k"><?= count($tracks) ?></div><div class="l">Future-ready tracks</div></div>
        <div><div class="k"><?= e($age) ?></div><div class="l">Open from</div></div>
        <div><div class="k">2</div><div class="l">Lagos campuses</div></div>
      </div>
    </div>
    <div class="hero__aside" data-reveal>
      <div class="stack-panel">
        <div class="stack-panel__bar">
          <span class="dots"><i></i><i></i><i></i></span>
          <span class="mono">~/afrotech — the stack</span>
        </div>
        <div class="stack-panel__body">
          <?php foreach ($tracks as $i => $t): ?>
            <a class="stack-row" href="<?= e(url('/summer/track/' . $t['slug'])) ?>">
              <span class="stack-row__n mono"><?= sprintf('%02d', $i + 1) ?></span>
              <span class="stack-row__i"><?php partial('icon', ['name' => $t['icon'] ?? 'chip']); ?></span>
              <span class="stack-row__t"><?= e($t['name']) ?></span>
              <span class="stack-row__b mono">10<?= $i % 2 ?>1</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Trust strip -->
<section class="strip">
  <div class="wrap strip__inner">
    <span>🔒 Safe, small groups</span><span>🎓 Certificate on completion</span>
    <span>🧑🏾‍🏫 Vetted faculty</span><span>📍 2 Lagos campuses + online</span>
  </div>
</section>

<!-- What you'll learn -->
<section class="section" id="tracks">
  <div class="wrap">
    <div class="section-head" data-reveal>
      <span class="eyebrow">What you'll learn</span>
      <h2>Six tracks. One transformative summer.</h2>
      <p>Practical, hands-on learning designed to inspire confidence, creativity, and success — for the next generation of African builders.</p>
    </div>
    <div class="tracks" data-reveal>
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

<!-- How it works -->
<section class="section band-ink" id="how">
  <div class="wrap">
    <div class="section-head center" data-reveal style="margin-inline:auto">
      <span class="eyebrow" style="justify-content:center">How it works</span>
      <h2>From sign-up to certificate in four weeks</h2>
    </div>
    <div class="steps" data-reveal>
      <div class="step"><div class="step__n">01</div><h3>Register</h3><p>Pick a track and register your child online in two minutes.</p></div>
      <div class="step"><div class="step__n">02</div><h3>Pay &amp; confirm</h3><p>Pay securely online or by transfer. We confirm the place and share dates.</p></div>
      <div class="step"><div class="step__n">03</div><h3>Learn by building</h3><p>Hands-on classes, small groups, real projects — on campus or online.</p></div>
      <div class="step"><div class="step__n">04</div><h3>Showcase</h3><p>Finish with something real and a certificate to be proud of.</p></div>
    </div>
  </div>
</section>

<!-- Featured courses -->
<?php if ($courses): ?>
<section class="section">
  <div class="wrap">
    <div class="flex-between section-head" style="max-width:none" data-reveal>
      <div><span class="eyebrow">From the catalog</span><h2>Featured courses</h2></div>
      <a class="btn btn--ghost" href="<?= e(url('/academy')) ?>">All courses</a>
    </div>
    <div class="card-grid" data-reveal>
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

<!-- Stats band -->
<section class="section band-red">
  <div class="wrap statband" data-reveal>
    <div><div class="statband__n"><?= count($tracks) ?></div><div class="statband__l">Tracks</div></div>
    <div><div class="statband__n"><?= e($age) ?></div><div class="statband__l">Ages welcomed</div></div>
    <div><div class="statband__n">4</div><div class="statband__l">Weeks per track</div></div>
    <div><div class="statband__n">2</div><div class="statband__l">Lagos campuses</div></div>
  </div>
</section>

<!-- Testimonials -->
<section class="section" id="parents">
  <div class="wrap">
    <div class="section-head" data-reveal><span class="eyebrow">Parents & guardians</span><h2>Trusted by Lagos families</h2></div>
    <div class="card-grid" data-reveal>
      <?php
      $quotes = [
        ['My daughter built her first website and hasn\'t stopped since. The confidence boost was worth it alone.', 'Mrs. Adeyemi', 'Parent, Egbeda'],
        ['Safe, structured, and genuinely fun. The instructors clearly love what they do.', 'Mr. Okonkwo', 'Parent, Ishefun'],
        ['The AI class taught my son to use these tools responsibly — exactly what I wanted.', 'Mrs. Balogun', 'Parent, Online'],
      ];
      foreach ($quotes as $q): ?>
        <figure class="card quote">
          <div class="quote__mark">"</div>
          <blockquote><?= e($q[0]) ?></blockquote>
          <figcaption><strong><?= e($q[1]) ?></strong><span class="muted"><?= e($q[2]) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section" id="faq">
  <div class="wrap" style="max-width:820px">
    <div class="section-head" data-reveal><span class="eyebrow">FAQ</span><h2>Questions, answered</h2></div>
    <div class="faq" data-reveal>
      <?php
      $faqs = [
        ['What ages is this for?', 'The Summer School is open to learners from ' . e($age) . '. Courses list their own recommended age range.'],
        ['How much does it cost?', 'The program fee is ' . e($fee) . ' per learner, per track. Discount codes may apply at checkout.'],
        ['Can my child attend online?', 'Yes — choose "Online" as your preferred campus during registration.'],
        ['How do I pay?', 'You can pay securely online by card, bank, or USSD, or by direct bank transfer. You\'ll get details right after registering.'],
        ['Do they get a certificate?', 'Yes. Every learner who completes a track receives a certificate of completion.'],
      ];
      foreach ($faqs as $f): ?>
        <details class="faq__item">
          <summary><?= e($f[0]) ?><span class="faq__plus" aria-hidden="true">+</span></summary>
          <div class="faq__a"><?= $f[1] ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Location + register CTA -->
<section class="section" id="visit">
  <div class="wrap grid-2">
    <div data-reveal>
      <span class="eyebrow">Location</span>
      <h2 style="margin-top:8px">Two campuses in Alimosho, Lagos</h2>
      <div class="info-grid mt-6">
        <div class="info"><h4><span class="flex" style="gap:6px"><?php partial('icon',['name'=>'pin']); ?> Egbeda</span></h4><p>CACENTRE, 2 Oremeji Street, Off Bakery Bus Stop, Egbeda, Alimosho, Lagos.</p></div>
        <div class="info"><h4><span class="flex" style="gap:6px"><?php partial('icon',['name'=>'pin']); ?> Ishefun</span></h4><p>CACENTRE, 18 Camp Davis Road, Orisunbare Phase 2 Bus Stop, Ishefun Road, Alimosho, Lagos.</p></div>
      </div>
      <p class="mt-6 flex" style="gap:8px"><?php partial('icon',['name'=>'phone']); ?> <a href="tel:<?= e(preg_replace('/\s/','',Setting::get('whatsapp_phone'))) ?>"><?= e(Setting::get('whatsapp_phone')) ?></a></p>
      <p class="flex" style="gap:8px"><?php partial('icon',['name'=>'mail']); ?> <a href="mailto:<?= e(Setting::get('contact_email')) ?>"><?= e(Setting::get('contact_email')) ?></a></p>
    </div>
    <div class="cta-card" data-reveal>
      <div class="binary-field" data-binary data-density="0.8" data-accent="#ffffff" aria-hidden="true"></div>
      <div class="cta-card__body">
        <span class="eyebrow" style="color:rgba(255,255,255,.9)">Start building</span>
        <h2 style="color:#fff;margin-top:8px">Ready to build the future?</h2>
        <p style="color:rgba(255,255,255,.92)">Explore our courses or join the next cohort. Hands-on, project-first learning for young African builders.</p>
        <?php if ($deadline && !Setting::deadlinePassed()): ?>
          <p class="mono" style="color:#fff;margin-top:12px">Summer School closes in <span data-countdown="<?= e(date('c', $deadline)) ?>" class="countdown-inline"></span></p>
        <?php endif; ?>
        <div class="mt-6 flex" style="gap:12px;flex-wrap:wrap">
          <a class="btn btn--light btn--lg" href="<?= e(url('/academy')) ?>">Explore courses</a>
          <a class="btn btn--lg" style="background:transparent;color:#fff;border:2px solid rgba(255,255,255,.6)" href="<?= e(url('/summer')) ?>">Summer School</a>
        </div>
      </div>
    </div>
  </div>
</section>
