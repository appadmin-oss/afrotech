<?php /** @var array|null $promo */
$p = $promo ?? [];
$v = fn($k, $d='') => e($p[$k] ?? $d);
?>
<div class="topbar"><div><a class="pill" href="<?= e(url('/admin/promotions')) ?>">← Promotions</a><h1 class="mt-2"><?= $promo ? 'Edit promotion' : 'New promotion' ?></h1></div></div>
<div class="panel" style="max-width:720px">
  <form class="form" method="post" action="<?= e(url('/admin/promotions/save')) ?>">
    <?= Csrf::field() ?>
    <?php if ($promo): ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" value="<?= $v('title') ?>" required></div>
    <div class="field"><label>Body</label><input type="text" name="body" value="<?= $v('body') ?>"></div>
    <div class="form__row">
      <div class="field"><label>Badge</label><input type="text" name="badge" value="<?= $v('badge') ?>" placeholder="NEW"></div>
      <div class="field"><label>Tone</label><select name="tone">
        <?php foreach (['red','ink','gold'] as $t): ?><option value="<?= $t ?>" <?= ($p['tone']??'red')===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="form__row">
      <div class="field"><label>CTA label</label><input type="text" name="cta_label" value="<?= $v('cta_label') ?>" placeholder="Register now"></div>
      <div class="field"><label>CTA link</label><input type="text" name="cta_href" value="<?= $v('cta_href','/summer') ?>"></div>
    </div>
    <div class="form__row">
      <div class="field"><label>Starts at</label><input type="datetime-local" name="starts_at" value="<?= $v('starts_at') ? date('Y-m-d\TH:i', strtotime($p['starts_at'])) : '' ?>"></div>
      <div class="field"><label>Ends at</label><input type="datetime-local" name="ends_at" value="<?= $v('ends_at') ? date('Y-m-d\TH:i', strtotime($p['ends_at'])) : '' ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="show_countdown" value="1" <?= !empty($p['show_countdown'])?'checked':'' ?>> <span>Show a live countdown to the end date in the ribbon</span></label>
    <div class="form__row">
      <div class="field"><label>Sort</label><input type="number" name="sort" value="<?= $v('sort','0') ?>"></div>
      <div class="field"><label>Status</label><select name="status">
        <option value="active" <?= ($p['status']??'active')==='active'?'selected':'' ?>>Active</option>
        <option value="paused" <?= ($p['status']??'')==='paused'?'selected':'' ?>>Paused</option>
      </select></div>
    </div>
    <div class="flex"><button class="btn btn--primary">Save promotion</button><a class="btn btn--ghost" href="<?= e(url('/admin/promotions')) ?>">Cancel</a></div>
  </form>
</div>
