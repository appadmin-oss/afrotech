<?php /** @var array|null $course @var array $tracks */
$c = $course ?? [];
$val = fn($k, $d = '') => e($c[$k] ?? $d);
?>
<div class="topbar">
  <div><a class="pill" href="<?= e(url('/admin/courses')) ?>">← Courses</a>
    <h1 class="mt-2"><?= $course ? 'Edit course' : 'New course' ?></h1></div>
</div>
<div class="panel" style="max-width:820px">
  <form class="form" method="post" action="<?= e(url('/admin/courses/save')) ?>">
    <?= Csrf::field() ?>
    <?php if ($course): ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" value="<?= $val('title') ?>" required></div>
    <div class="form__row">
      <div class="field"><label>Slug <span class="muted">(blank = auto)</span></label><input type="text" name="slug" value="<?= $val('slug') ?>"></div>
      <div class="field"><label>Track</label>
        <select name="track_slug"><option value="">— None —</option>
          <?php foreach ($tracks as $slug=>$name): ?><option value="<?= e($slug) ?>" <?= ($c['track_slug']??'')===$slug?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form__row">
      <div class="field"><label>Level</label><input type="text" name="level" value="<?= $val('level','Beginner') ?>"></div>
      <div class="field"><label>Age range</label><input type="text" name="age_range" value="<?= $val('age_range','7+') ?>"></div>
    </div>
    <div class="form__row">
      <div class="field"><label>Weeks</label><input type="number" name="weeks" value="<?= $val('weeks','4') ?>"></div>
      <div class="field"><label>Fee (₦)</label><input type="number" name="price_naira" value="<?= $val('price_naira','40000') ?>"></div>
    </div>
    <div class="field"><label>Instructor</label><input type="text" name="instructor" value="<?= $val('instructor','Afrotech Faculty') ?>"></div>
    <div class="field"><label>Summary</label><textarea name="summary" style="min-height:70px"><?= $val('summary') ?></textarea></div>
    <div class="field"><label>Body</label><textarea name="body"><?= $val('body') ?></textarea></div>
    <div class="field"><label>Syllabus (JSON array of {title, desc})</label><textarea name="syllabus_json" class="mono" style="font-size:13px"><?= $val('syllabus_json') ?></textarea><div class="hint">Example: [{"title":"Week 1 — Intro","desc":"…"}]</div></div>
    <div class="field"><label>Status</label>
      <select name="status">
        <option value="draft" <?= ($c['status']??'draft')==='draft'?'selected':'' ?>>Draft</option>
        <option value="published" <?= ($c['status']??'')==='published'?'selected':'' ?>>Published</option>
      </select>
    </div>
    <div class="flex"><button class="btn btn--primary">Save course</button><a class="btn btn--ghost" href="<?= e(url('/admin/courses')) ?>">Cancel</a></div>
  </form>
</div>
