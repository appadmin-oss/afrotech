<?php
/** Site-wide announcement ribbon, driven by the Promotion model. */
$promo = Promotion::active();
if (!$promo) return;
$href = $promo['cta_href'] ?: '/summer';
$href = preg_match('#^https?://#', $href) ? $href : url($href);
?>
<div class="ribbon ribbon--<?= e($promo['tone']) ?>">
  <div class="wrap ribbon__inner">
    <?php if (!empty($promo['badge'])): ?><span class="ribbon__badge"><?= e($promo['badge']) ?></span><?php endif; ?>
    <span class="ribbon__text"><strong><?= e($promo['title']) ?></strong><?php if (!empty($promo['body'])): ?> <span class="hide-sm">— <?= e($promo['body']) ?></span><?php endif; ?></span>
    <?php if (!empty($promo['show_countdown']) && !empty($promo['ends_at'])): ?>
      <span class="ribbon__count" data-countdown="<?= e(date('c', strtotime($promo['ends_at']))) ?>"></span>
    <?php endif; ?>
    <?php if (!empty($promo['cta_label'])): ?>
      <a class="ribbon__cta" href="<?= e($href) ?>"><?= e($promo['cta_label']) ?> →</a>
    <?php endif; ?>
  </div>
</div>
