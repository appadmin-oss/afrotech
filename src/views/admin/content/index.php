<?php /** @var array $content @var array $keys @var string|null $saved */
$labels = [
  'hero_eyebrow'  => 'Hero eyebrow (small label above the title)',
  'hero_title'    => 'Hero title',
  'hero_subtitle' => 'Hero paragraph',
  'summer_intro'  => 'Summer intro paragraph',
];
?>
<div class="topbar"><div><span class="eyebrow">Academy</span><h1>Landing content</h1></div></div>
<?php if ($saved): ?><div class="alert alert--ok" style="margin-bottom:16px"><?= e($saved) ?></div><?php endif; ?>
<?php if (!Database::available()): ?><div class="alert alert--err" style="margin-bottom:16px">Database not connected — edits won't persist. Public pages show the built-in defaults.</div><?php endif; ?>
<div class="panel" style="max-width:820px">
  <form class="form" method="post" action="<?= e(url('/admin/content/save')) ?>">
    <?= Csrf::field() ?>
    <?php foreach ($keys as $k): ?>
      <div class="field">
        <label><?= e($labels[$k] ?? $k) ?></label>
        <?php if (str_contains($k, 'subtitle') || str_contains($k, 'intro')): ?>
          <textarea name="<?= e($k) ?>"><?= e($content[$k] ?? '') ?></textarea>
        <?php else: ?>
          <input type="text" name="<?= e($k) ?>" value="<?= e($content[$k] ?? '') ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn--primary">Save content</button>
  </form>
</div>
