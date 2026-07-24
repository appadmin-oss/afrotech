<?php
/** @var array $tracks @var array $content @var array $errors @var array $old */
$errors = $errors ?? [];
$old = $old ?? [];
$ov = fn($k, $d = '') => e($old[$k] ?? $d);
$err = fn($k) => isset($errors[$k]) ? '<div class="err">' . e($errors[$k]) . '</div>' : '<div class="err"></div>';
$hasErr = fn($k) => isset($errors[$k]) ? ' has-error' : '';
?>
<section class="hero" style="padding-block:clamp(40px,6vw,72px)">
  <div class="binary-field" aria-hidden="true"><div class="binary-field__static"><?php
    $b=''; for($i=0;$i<700;$i++)$b.=(($i%31&&$i%4)?mt_rand(0,1):' '); echo e($b);
  ?></div></div>
  <div class="wrap hero__inner" style="grid-template-columns:1.1fr .9fr">
    <div>
      <span class="eyebrow">Summer School · <?= e(AFT_SUMMER_AGE) ?></span>
      <h1 class="hero__wordmark" style="font-size:clamp(38px,6vw,72px)">Register<span>for summer</span></h1>
      <p class="hero__lede"><?= e($content['summer_intro']) ?></p>
      <div class="hero__meta">
        <div><div class="k"><?= e(AFT_SUMMER_FEE) ?></div><div class="l">Per learner</div></div>
        <div><div class="k">4 weeks</div><div class="l">Per track</div></div>
      </div>
    </div>
    <div class="hero__aside"><?php partial('starburst'); ?></div>
  </div>
</section>

<section class="section--tight">
  <div class="wrap">
    <div class="card" style="max-width:760px;margin-inline:auto;padding:clamp(24px,4vw,48px)">
      <div data-success>
        <div class="section-head" style="margin-bottom:24px">
          <span class="eyebrow">Registration form</span>
          <h2 style="font-size:var(--fs-h2)">Tell us about your child</h2>
          <p>Takes two minutes. We'll email confirmation and payment details right after.</p>
        </div>

        <div data-form-alert><?= $errors ? '<div class="alert alert--err">Please check the highlighted fields.</div>' : '' ?></div>

        <form class="form mt-4" method="post" action="<?= e(url('/api/summer/register')) ?>" data-async novalidate>
          <?= Csrf::field() ?>
          <div class="form__row">
            <div class="field<?= $hasErr('student_name') ?>">
              <label>Student's full name <span class="req">*</span></label>
              <input type="text" name="student_name" value="<?= $ov('student_name') ?>" required>
              <?= $err('student_name') ?>
            </div>
            <div class="field<?= $hasErr('student_age') ?>">
              <label>Student's age <span class="req">*</span></label>
              <input type="number" name="student_age" min="5" max="19" value="<?= $ov('student_age') ?>" required>
              <?= $err('student_age') ?>
            </div>
          </div>

          <div class="field<?= $hasErr('track_slug') ?>">
            <label>Choose a track <span class="req">*</span></label>
            <select name="track_slug" required>
              <option value="">Select a program…</option>
              <?php foreach ($tracks as $t): ?>
                <option value="<?= e($t['slug']) ?>" <?= ($old['track_slug'] ?? '') === $t['slug'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('track_slug') ?>
          </div>

          <div class="form__row">
            <div class="field<?= $hasErr('guardian_name') ?>">
              <label>Parent / guardian name <span class="req">*</span></label>
              <input type="text" name="guardian_name" value="<?= $ov('guardian_name') ?>" required>
              <?= $err('guardian_name') ?>
            </div>
            <div class="field<?= $hasErr('phone') ?>">
              <label>Phone / WhatsApp <span class="req">*</span></label>
              <input type="tel" name="phone" value="<?= $ov('phone') ?>" placeholder="+234…" required>
              <?= $err('phone') ?>
            </div>
          </div>

          <div class="field<?= $hasErr('email') ?>">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" value="<?= $ov('email') ?>" required>
            <?= $err('email') ?>
          </div>

          <div class="form__row">
            <div class="field">
              <label>Preferred campus</label>
              <select name="location_pref">
                <option value="Egbeda">Egbeda — 2 Oremeji Street</option>
                <option value="Ishefun">Ishefun — 18 Camp Davis Road</option>
                <option value="Online">Online</option>
                <option value="No preference">No preference</option>
              </select>
            </div>
            <div class="field">
              <label>Experience with this track</label>
              <select name="experience">
                <option value="none">Brand new — no experience</option>
                <option value="some">A little experience</option>
                <option value="confident">Confident / done some before</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Anything we should know? <span class="muted">(optional)</span></label>
            <textarea name="notes" placeholder="Allergies, accessibility needs, questions…"><?= $ov('notes') ?></textarea>
          </div>

          <label class="check">
            <input type="checkbox" required>
            <span>I confirm I'm the parent/guardian and consent to Afrotech Academy contacting me about this registration.</span>
          </label>

          <button type="submit" class="btn btn--primary btn--lg btn--block">Complete registration · <?= e(AFT_SUMMER_FEE) ?></button>
          <p class="muted center" style="font-size:var(--fs-xs)">No payment is taken here — we'll email you secure payment details after confirming a place.</p>
        </form>
      </div>
    </div>
  </div>
</section>
