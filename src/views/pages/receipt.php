<?php
/**
 * Payment receipt — the confirmation a family keeps.
 *
 * Reached at /summer/receipt/{reference} after the gateway returns, and
 * re-visitable forever: parents forward it, print it, and produce it when a
 * school or a sponsor asks for proof of payment. It renders from the stored
 * payment row, never from a gateway response, so it reads the same on the
 * hundredth visit as the first.
 *
 * @var array $payment @var array|null $reg @var array $addonRows
 */
$sym  = Setting::get('currency_symbol', '₦');
$base = (int)$payment['base_amount'];
$disc = (int)$payment['discount_amount'];
$paid = (int)$payment['amount'];
$when = $payment['verified_at'] ?: $payment['created_at'];
$method = $payment['provider'] === 'manual' ? 'Bank transfer' : 'Card / bank / USSD (Paystack)';
// A hand-cleared transfer says so, so an operator can tell at a glance which
// receipts came from the gateway and which someone signed off.
if (($payment['confirmed_via'] ?? '') === 'operator') $method = 'Bank transfer (confirmed by our team)';
$addonTotal = 0;
foreach ($addonRows as [, $amt]) $addonTotal += (int)$amt;
$programmeFee = max(0, $base - $addonTotal);
?>
<section class="section--tight">
  <div class="wrap" style="max-width:640px">

    <div class="receipt-doc" id="receipt">
      <div class="receipt-doc__head">
        <div>
          <div class="receipt-doc__brand">Afrotech <span>Academy</span></div>
          <div class="receipt-doc__org"><?= e(AFT_PARENT) ?></div>
        </div>
        <div class="receipt-doc__stamp">Paid</div>
      </div>

      <h1 class="receipt-doc__title">Payment receipt</h1>
      <p class="receipt-doc__lede">
        <?= $reg ? e($reg['student_name']) . "'s" : 'Your' ?> place on the
        <?= $reg && $reg['track_name'] ? e($reg['track_name']) . ' track' : 'Summer School' ?>
        is confirmed. Keep this receipt — quote the reference whenever you contact us.
      </p>

      <dl class="receipt-doc__meta">
        <div>
          <dt>Receipt reference</dt>
          <dd class="mono"><?= e($payment['reference']) ?></dd>
        </div>
        <?php if ($reg): ?>
        <div>
          <dt>Registration code</dt>
          <dd class="mono"><?= e($reg['reg_code']) ?></dd>
        </div>
        <?php endif; ?>
        <div>
          <dt>Paid on</dt>
          <dd><?= e(datetime_pretty($when)) ?></dd>
        </div>
        <div>
          <dt>Method</dt>
          <dd><?= e($method) ?></dd>
        </div>
        <div>
          <dt>Billed to</dt>
          <dd><?= e($payment['email']) ?></dd>
        </div>
        <?php if ($reg && $reg['location_pref']): ?>
        <div>
          <dt>Campus</dt>
          <dd><?= e($reg['location_pref']) ?></dd>
        </div>
        <?php endif; ?>
      </dl>

      <table class="receipt-doc__lines">
        <tbody>
          <tr>
            <th scope="row">Programme fee<?= $reg && $reg['track_name'] ? ' — ' . e($reg['track_name']) : '' ?></th>
            <td><?= e($sym . number_format($programmeFee)) ?></td>
          </tr>
          <?php foreach ($addonRows as [$label, $amount]): ?>
          <tr>
            <th scope="row"><?= e($label) ?></th>
            <td><?= e($sym . number_format((int)$amount)) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ($disc > 0): ?>
          <tr class="receipt-doc__discount">
            <th scope="row">Discount<?= $payment['discount_code'] ? ' (' . e($payment['discount_code']) . ')' : '' ?></th>
            <td>−<?= e($sym . number_format($disc)) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">Total paid</th>
            <td><?= e($sym . number_format($paid)) ?></td>
          </tr>
        </tfoot>
      </table>

      <p class="receipt-doc__foot">
        Issued by <?= e(AFT_PARENT) ?> · <?= e(AFT_PHONE) ?> ·
        <a href="mailto:<?= e(AFT_EMAIL) ?>"><?= e(AFT_EMAIL) ?></a><br>
        A copy was emailed to <?= e($payment['email']) ?>. This receipt stays available at this address.
      </p>
    </div>

    <div class="receipt-actions">
      <button type="button" class="btn btn--ink" data-print>Print or save as PDF</button>
      <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Back home</a>
      <a class="btn btn--ghost" href="<?= e(url('/contact')) ?>">Something looks wrong?</a>
    </div>

    <p class="muted center" style="font-size:var(--fs-xs);margin-top:var(--sp-4)">
      What happens next: we'll be in touch with dates, what to bring, and campus details before the programme starts.
    </p>
  </div>
</section>
