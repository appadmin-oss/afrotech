<?php
/** @var string|null $sent @var array $errors @var array $old */
$errors = $errors ?? []; $old = $old ?? [];
$ov = fn($k) => e($old[$k] ?? '');
?>
<section class="section">
  <div class="wrap grid-2">
    <div>
      <span class="eyebrow">Contact</span>
      <h1 style="font-size:var(--fs-h1);margin-top:8px">Let's talk about your child's summer.</h1>
      <p style="font-size:var(--fs-lg)">Questions about tracks, ages, campuses, or payment? Send a message — we reply within one working day.</p>
      <div class="info-grid mt-6">
        <div class="info"><h4>Call / WhatsApp</h4><p><a href="tel:+2348100191456"><?= e(AFT_PHONE) ?></a></p></div>
        <div class="info"><h4>Email</h4><p><a href="mailto:<?= e(AFT_EMAIL) ?>"><?= e(AFT_EMAIL) ?></a></p></div>
        <div class="info"><h4>Egbeda</h4><p>2 Oremeji Street, Off Bakery Bus Stop, Alimosho.</p></div>
        <div class="info"><h4>Ishefun</h4><p>18 Camp Davis Road, Orisunbare Phase 2, Alimosho.</p></div>
      </div>
    </div>
    <div class="card">
      <?php if ($sent): ?>
        <div class="alert alert--ok"><?= e($sent) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?><div class="alert alert--err">Please check the highlighted fields.</div><?php endif; ?>
      <form class="form mt-4" method="post" action="<?= e(url('/api/contact')) ?>">
        <?= Csrf::field() ?>
        <div class="field<?= isset($errors['name'])?' has-error':'' ?>">
          <label>Your name <span class="req">*</span></label>
          <input type="text" name="name" value="<?= $ov('name') ?>" required>
          <div class="err"><?= e($errors['name'] ?? '') ?></div>
        </div>
        <div class="field<?= isset($errors['email'])?' has-error':'' ?>">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" value="<?= $ov('email') ?>" required>
          <div class="err"><?= e($errors['email'] ?? '') ?></div>
        </div>
        <div class="field">
          <label>Phone <span class="muted">(optional)</span></label>
          <input type="tel" name="phone" value="<?= $ov('phone') ?>">
        </div>
        <div class="field<?= isset($errors['message'])?' has-error':'' ?>">
          <label>Message <span class="req">*</span></label>
          <textarea name="message" required><?= $ov('message') ?></textarea>
          <div class="err"><?= e($errors['message'] ?? '') ?></div>
        </div>
        <button class="btn btn--primary btn--block">Send message</button>
      </form>
    </div>
  </div>
</section>
