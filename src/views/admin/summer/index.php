<?php /** @var array $rows @var array $stats @var array $filters @var array $tracks */
$qs = http_build_query(array_filter($filters));
?>
<div class="topbar">
  <div><span class="eyebrow">Summer school</span><h1>Registrations</h1></div>
  <?php if (Rbac::can('registrations.manage')): ?>
    <a class="btn btn--ink btn--sm" href="<?= e(url('/admin/summer/export.csv' . ($qs ? '?' . $qs : ''))) ?>">Export CSV</a>
  <?php endif; ?>
</div>

<div class="stats">
  <div class="stat stat--accent"><div class="n"><?= (int)$stats['total'] ?></div><div class="l">Total</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['pending'] ?></div><div class="l">Pending</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['confirmed'] ?></div><div class="l">Confirmed</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['waitlisted'] ?></div><div class="l">Waitlisted</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['cancelled'] ?></div><div class="l">Cancelled</div></div>
</div>

<div class="panel">
  <div class="panel__head">
    <h2><?= count($rows) ?> shown</h2>
    <form class="filters" method="get" action="<?= e(url('/admin/summer')) ?>">
      <input type="search" name="q" placeholder="Name, email, code…" value="<?= e($filters['q']) ?>">
      <select name="status">
        <?php foreach (['all'=>'All statuses','pending'=>'Pending','confirmed'=>'Confirmed','waitlisted'=>'Waitlisted','cancelled'=>'Cancelled'] as $k=>$l): ?>
          <option value="<?= e($k) ?>" <?= $filters['status']===$k?'selected':'' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="track">
        <option value="">All tracks</option>
        <?php foreach ($tracks as $slug=>$name): ?>
          <option value="<?= e($slug) ?>" <?= $filters['track']===$slug?'selected':'' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--primary btn--sm">Filter</button>
    </form>
  </div>

  <?php if (!$rows): ?>
    <p class="muted"><?= Database::available() ? 'No registrations match these filters.' : 'Database not connected — submissions are logged to storage/summer.log.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Code</th><th>Student</th><th>Age</th><th>Guardian</th><th>Track</th><th>Status</th><th>Payment</th><th>Registered</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><a href="<?= e(url('/admin/summer/' . $r['id'])) ?>"><?= e($r['reg_code']) ?></a></td>
          <td><?= e($r['student_name']) ?></td>
          <td><?= (int)$r['student_age'] ?></td>
          <td><?= e($r['guardian_name']) ?><br><span class="muted mono" style="font-size:11px"><?= e($r['email']) ?></span></td>
          <td><?= e($r['track_name']) ?></td>
          <td><span class="chip chip--<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
          <td><span class="chip chip--<?= e($r['payment_status']) ?>"><?= e($r['payment_status']) ?></span></td>
          <td class="muted"><?= e(date_pretty($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
