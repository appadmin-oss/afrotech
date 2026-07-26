<?php
/**
 * Framed preview of a form definition — the same FormRenderer output and the
 * same logic runtime the public page uses, rendered inert (no CSRF token, no
 * submit target) so nothing can be posted from here.
 *
 * @var array $def @var string $formKey @var string $symbol @var int $fee
 */
$settings = $def['settings'] ?? FormEngine::normalizeSettings([]);
$steps    = FormEngine::stepCount(FormEngine::resolve($def['fields'] ?? []));
?>
<div class="card" style="max-width:760px;margin-inline:auto;padding:clamp(20px,3vw,36px)">
  <div class="section-head" style="margin-bottom:20px">
    <span class="eyebrow">Preview · <?= e(($def['status'] ?? 'live') === 'draft' ? 'unpublished draft' : 'live v' . (int)($def['version'] ?? 1)) ?></span>
    <h2 style="font-size:var(--fs-h2)"><?= e($settings['introTitle']) ?></h2>
    <?php if ($settings['introBody'] !== ''): ?><p><?= e($settings['introBody']) ?></p><?php endif; ?>
    <?php if ($steps > 1): ?>
      <p class="muted" style="font-size:var(--fs-xs)"><?= (int)$steps ?> steps · conditional fields react as you answer</p>
    <?php endif; ?>
  </div>

  <?= FormRenderer::form($def, [
        'preview' => true,
        'fee'     => (int)$fee,
        'symbol'  => $symbol,
      ]) ?>
</div>
