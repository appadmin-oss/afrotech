<?php /** @var array $stats @var array $recent @var array $counts */
$u = Auth::user();
$initials = strtoupper(substr($u['name'] ?? 'O', 0, 1));
?>
<div class="topbar">
  <div><span class="eyebrow">Overview</span><h1>Dashboard</h1></div>
  <div class="who"><span class="avatar"><?= e($initials) ?></span>
    <div><strong><?= e($u['name']) ?></strong><br><span class="mono muted" style="font-size:11px"><?= e(Rbac::label($u['role'])) ?></span></div>
  </div>
</div>

<div class="stats">
  <div class="stat stat--accent"><div class="n"><?= (int)$stats['total'] ?></div><div class="l">Summer registrations</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['pending'] ?></div><div class="l">Pending review</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['confirmed'] ?></div><div class="l">Confirmed</div></div>
  <div class="stat"><div class="n"><?= (int)$stats['paid'] ?></div><div class="l">Paid</div></div>
  <div class="stat"><div class="n"><?= e(naira((int)$stats['revenue'])) ?></div><div class="l">Revenue (paid)</div></div>
</div>

<div class="stats">
  <div class="stat"><div class="n"><?= (int)$counts['students'] ?></div><div class="l">Students</div></div>
  <div class="stat"><div class="n"><?= (int)$counts['courses'] ?></div><div class="l">Courses</div></div>
  <div class="stat"><div class="n"><?= (int)$counts['operators'] ?></div><div class="l">Operators</div></div>
</div>

<div class="panel">
  <div class="panel__head">
    <h2>Latest registrations</h2>
    <?php if (Rbac::can('registrations.view')): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/summer')) ?>">View all</a><?php endif; ?>
  </div>
  <?php if (!$recent): ?>
    <p class="muted"><?= Database::available() ? 'No registrations yet.' : 'Connect the database to see live registrations. Submissions are being logged to storage/summer.log.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Code</th><th>Student</th><th>Track</th><th>Status</th><th>Payment</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="mono"><a href="<?= e(url('/admin/summer/' . $r['id'])) ?>"><?= e($r['reg_code']) ?></a></td>
          <td><?= e($r['student_name']) ?> <span class="muted">(<?= (int)$r['student_age'] ?>)</span></td>
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
