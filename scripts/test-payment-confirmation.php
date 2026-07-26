<?php
/**
 * Payment-confirmation test harness — no database, no gateway.
 *
 *   php scripts/test-payment-confirmation.php
 *
 * Covers the decision that decides whether money actually landed. A charge is
 * only allowed to settle an invoice when the gateway says success, the amount
 * covers it (in minor units — the classic 100× mistake), and the currency
 * matches. Everything else is a mismatch, not a payment.
 *
 * The once-only guarantee (Payment::claimSucceeded / claimReceipt) is enforced
 * by a conditional UPDATE, so it is asserted against a live table rather than
 * here; what this file pins down is that no code path treats an unacceptable
 * charge as acceptable.
 */

declare(strict_types=1);

define('AFT_ROOT', dirname(__DIR__));
// chargeAcceptable() is pure and static, but it lives on a controller — so the
// base class has to exist before the file will load. Nothing else is booted:
// no database, no session, no gateway.
require AFT_ROOT . '/src/core/Controller.php';
require AFT_ROOT . '/src/controllers/PaymentController.php';

$pass = 0;
$fail = 0;

function ok(string $what, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  \033[32m✓\033[0m {$what}\n"; return; }
    $fail++;
    echo "  \033[31m✗ {$what}\033[0m" . ($detail !== '' ? "  — {$detail}" : '') . "\n";
}
function group(string $name): void { echo "\n\033[1m{$name}\033[0m\n"; }

/** An invoice for ₦55,000 in naira. */
$invoice = ['amount' => 55000, 'currency' => 'NGN', 'reference' => 'AFT-PAY-260726-ABC123'];

group('A charge that settles the invoice');

ok('exact amount in kobo is accepted',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5500000, 'currency' => 'NGN'], $invoice));

ok('an over-payment is accepted',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 6000000, 'currency' => 'NGN'], $invoice));

ok('a missing currency is assumed to be the invoice currency',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5500000], $invoice));

ok('currency case does not matter',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5500000, 'currency' => 'ngn'], $invoice));

group('A charge that does not');

ok('an under-payment is refused',
    !PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5499900, 'currency' => 'NGN'], $invoice));

// The bug this guards: naira compared against kobo would read ₦55,000 paid as
// 55,000 kobo (₦550) — or, the other way round, wave through a 100× shortfall.
ok('a major-unit amount is refused (kobo vs naira)',
    !PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 55000, 'currency' => 'NGN'], $invoice));

ok('a failed charge is refused',
    !PaymentController::chargeAcceptable(['status' => 'failed', 'amount' => 5500000, 'currency' => 'NGN'], $invoice));

ok('an abandoned charge is refused',
    !PaymentController::chargeAcceptable(['status' => 'abandoned', 'amount' => 5500000, 'currency' => 'NGN'], $invoice));

ok('a charge in another currency is refused',
    !PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5500000, 'currency' => 'USD'], $invoice));

ok('a charge with no amount is refused',
    !PaymentController::chargeAcceptable(['status' => 'success', 'currency' => 'NGN'], $invoice));

ok('a charge with no status is refused',
    !PaymentController::chargeAcceptable(['amount' => 5500000, 'currency' => 'NGN'], $invoice));

ok('an empty payload is refused',
    !PaymentController::chargeAcceptable([], $invoice));

ok('a null payload is refused (gateway returned nothing usable)',
    !PaymentController::chargeAcceptable(null, $invoice));

group('Edge cases');

ok('a zero-amount invoice (fully discounted) accepts a zero charge',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 0, 'currency' => 'NGN'],
        ['amount' => 0, 'currency' => 'NGN']));

ok('a string amount from JSON is still compared numerically',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => '5500000', 'currency' => 'NGN'], $invoice));

ok('an invoice with no currency defaults to NGN and still matches',
    PaymentController::chargeAcceptable(['status' => 'success', 'amount' => 5500000, 'currency' => 'NGN'],
        ['amount' => 55000]));

echo "\n" . str_repeat('─', 52) . "\n";
echo $fail === 0
    ? "\033[32mAll {$pass} assertions passed.\033[0m\n"
    : "\033[31m{$fail} failed\033[0m, {$pass} passed.\n";
exit($fail === 0 ? 0 : 1);
