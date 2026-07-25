<?php /** @var array|null $discount */
$d = $discount ?? [];
$v = fn($k, $x='') => e($d[$k] ?? $x);
?>
<div class="topbar"><div><a class="pill" href="<?= e(url('/admin/discounts')) ?>">← Discount codes</a><h1 class="mt-2"><?= $discount ? 'Edit code' : 'New code' ?></h1></div></div>
<div class="panel" style="max-width:640px">
  <form class="form" method="post" action="<?= e(url('/admin/discounts/save')) ?>">
    <?= Csrf::field() ?>
    <?php if ($discount): ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><?php endif; ?>
    <div class="form__row">
      <div class="field"><label>Code</label><input type="text" name="code" value="<?= $v('code') ?>" style="text-transform:uppercase" required></div>
      <div class="field"><label>Status</label><select name="status">
        <option value="active" <?= ($d['status']??'active')==='active'?'selected':'' ?>>Active</option>
        <option value="paused" <?= ($d['status']??'')==='paused'?'selected':'' ?>>Paused</option>
      </select></div>
    </div>
    <div class="field"><label>Description</label><input type="text" name="description" value="<?= $v('description') ?>"></div>
    <div class="form__row">
      <div class="field"><label>Type</label><select name="type">
        <option value="percent" <?= ($d['type']??'percent')==='percent'?'selected':'' ?>>Percent (%)</option>
        <option value="fixed" <?= ($d['type']??'')==='fixed'?'selected':'' ?>>Fixed (₦)</option>
      </select></div>
      <div class="field"><label>Value</label><input type="number" name="value" value="<?= $v('value','0') ?>" required><div class="hint">Percent 0–100, or a naira amount.</div></div>
    </div>
    <div class="form__row">
      <div class="field"><label>Minimum order (₦)</label><input type="number" name="min_amount" value="<?= $v('min_amount','0') ?>"></div>
      <div class="field"><label>Max uses <span class="muted">(blank = ∞)</span></label><input type="number" name="max_uses" value="<?= $d && $d['max_uses']!==null ? (int)$d['max_uses'] : '' ?>"></div>
    </div>
    <div class="form__row">
      <div class="field"><label>Starts at</label><input type="datetime-local" name="starts_at" value="<?= $v('starts_at') ? date('Y-m-d\TH:i', strtotime($d['starts_at'])) : '' ?>"></div>
      <div class="field"><label>Ends at</label><input type="datetime-local" name="ends_at" value="<?= $v('ends_at') ? date('Y-m-d\TH:i', strtotime($d['ends_at'])) : '' ?>"></div>
    </div>
    <div class="flex"><button class="btn btn--primary">Save code</button><a class="btn btn--ghost" href="<?= e(url('/admin/discounts')) ?>">Cancel</a></div>
  </form>
</div>
