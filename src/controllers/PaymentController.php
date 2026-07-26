<?php

/**
 * PaymentController — the public checkout for a summer registration.
 * Flow: /summer/pay/{code} → (Paystack initialize) → gateway → callback verify.
 * When no gateway key is configured it falls back to manual bank-transfer
 * instructions so the site is still usable end-to-end.
 */
class PaymentController extends Controller {

    public function checkout(string $code): void {
        $reg = SummerRegistration::findByCode($code);
        if (!$reg) { $this->notFound(); return; }
        $payment = Payment::forRegistration((int)$reg['id']);
        $this->view('pages/checkout', [
            'title'        => 'Complete payment · ' . AFT_NAME,
            'reg'          => $reg,
            'fee'          => self::amountFor($reg),
            'baseFee'      => Setting::fee(),
            'addons'       => max(0, (int)($reg['addons_naira'] ?? 0)),
            'paid'         => ($reg['payment_status'] === 'paid') || ($payment && $payment['status'] === 'succeeded'),
            'gateway'      => Setting::bool('payment_enabled', true) && Paystack::enabled(),
            'bankDetails'  => Setting::get('bank_transfer_details'),
        ], 'main');
    }

    /**
     * What this registration owes: the fee captured at registration time,
     * which already includes any add-ons the form's logic priced. Older rows
     * (and the offline fallback) have no stored fee, so the current base
     * programme fee stands in.
     */
    public static function amountFor(?array $reg): int {
        $stored = (int)($reg['fee_naira'] ?? 0);
        return $stored > 0 ? $stored : Setting::fee();
    }

    /** POST /api/checkout/quote — live discount estimate (JSON). */
    public function quote(): void {
        Csrf::require();
        // Quote against the registration being paid for, not the base fee, or
        // a percentage code would be computed off the wrong amount.
        $code = strtoupper(trim((string)$this->input('reg_code', '')));
        $reg  = $code !== '' ? SummerRegistration::findByCode($code) : null;
        $fee  = self::amountFor($reg);
        $res = Discount::evaluate((string)$this->input('code', ''), $fee);
        if (!$res['ok']) {
            $this->json(['ok' => false, 'message' => $res['error'], 'base' => $fee, 'total' => $fee]);
        }
        $this->json([
            'ok'       => true,
            'base'     => $fee,
            'discount' => $res['discount'],
            'total'    => $res['total'],
            'label'    => $res['code']['description'] ?: $res['code']['code'],
            'symbol'   => Setting::get('currency_symbol', '₦'),
        ]);
    }

    /** POST /summer/pay/{code} — create payment + redirect to gateway. */
    public function start(string $code): void {
        Csrf::require();
        $reg = SummerRegistration::findByCode($code);
        if (!$reg) { $this->notFound(); return; }

        if (Setting::deadlinePassed()) {
            flash_set('checkout_err', 'Registration has closed for this cohort.');
            $this->redirect('/summer/pay/' . $reg['reg_code']);
            return;
        }

        $fee = self::amountFor($reg);
        $codeStr = (string)$this->input('discount_code', '');
        $discount = 0; $applied = null;
        if ($codeStr !== '') {
            $ev = Discount::evaluate($codeStr, $fee);
            if ($ev['ok']) { $discount = $ev['discount']; $applied = strtoupper(trim($codeStr)); }
        }
        $total = max(0, $fee - $discount);

        $useGateway = Setting::bool('payment_enabled', true) && Paystack::enabled();
        $pay = Payment::create([
            'registration_id' => (int)$reg['id'],
            'email'           => $reg['email'],
            'provider'        => $useGateway ? 'paystack' : 'manual',
            'currency'        => Setting::get('currency_code', 'NGN'),
            'base_amount'     => $fee,
            'discount_code'   => $applied,
            'discount_amount' => $discount,
            'amount'          => $total,
        ]);

        if (!$useGateway) {
            // Manual path — show bank transfer instructions.
            $this->redirect('/summer/pay/' . $reg['reg_code'] . '/manual?ref=' . urlencode($pay['reference']));
            return;
        }

        try {
            $callback = url('/summer/pay/callback');
            $resp = Paystack::initialize($reg['email'], $total, $pay['reference'], $callback, [
                'reg_code'     => $reg['reg_code'],
                'student_name' => $reg['student_name'],
                'track'        => $reg['track_name'],
            ]);
            $authUrl = $resp['data']['data']['authorization_url'] ?? null;
            if ($authUrl) { $this->redirect($authUrl); return; }
            error_log('[aft/pay] initialize failed: ' . ($resp['raw'] ?? ''));
        } catch (Throwable $e) {
            error_log('[aft/pay] ' . $e->getMessage());
        }
        flash_set('checkout_err', 'We could not reach the payment gateway. Please try again, or use bank transfer.');
        $this->redirect('/summer/pay/' . $reg['reg_code']);
    }

    /** GET /summer/pay/{code}/manual — bank-transfer fallback instructions. */
    public function manual(string $code): void {
        $reg = SummerRegistration::findByCode($code);
        if (!$reg) { $this->notFound(); return; }
        $ref = (string)$this->input('ref', '');
        $payment = $ref ? Payment::findByReference($ref) : Payment::forRegistration((int)$reg['id']);
        $this->view('pages/checkout-manual', [
            'title'       => 'Bank transfer · ' . AFT_NAME,
            'reg'         => $reg,
            'payment'     => $payment,
            'bankDetails' => Setting::get('bank_transfer_details'),
        ], 'main');
    }

    /**
     * Does a gateway charge actually pay this payment off?
     *
     * Pure, so it can be tested without a gateway. Three things must hold, and
     * the amount check is the one that matters: Paystack reports minor units
     * (kobo), and the reference alone says nothing about how much was paid — a
     * tampered or reused initialisation could settle for less.
     */
    public static function chargeAcceptable(?array $data, array $pay): bool {
        if (!is_array($data)) return false;
        if (($data['status'] ?? '') !== 'success') return false;
        // Minor units, so ₦1 is 100. Over-payment is acceptable; under-payment is not.
        if ((int)($data['amount'] ?? 0) < (int)$pay['amount'] * 100) return false;
        // A charge settled in another currency is not this invoice.
        $expected = strtoupper((string)($pay['currency'] ?? 'NGN'));
        $got      = strtoupper((string)($data['currency'] ?? $expected));
        return $got === $expected;
    }

    /**
     * GET /summer/pay/callback?reference=… — where the gateway returns the
     * parent's browser. Verifies server-side, then redirects to the receipt so
     * a refresh re-reads a record instead of re-verifying a charge.
     */
    public function callback(): void {
        $ref = (string)$this->input('reference', $this->input('trxref', ''));
        $pay = $ref ? Payment::findByReference($ref) : null;
        if (!$pay) { $this->notFound(); return; }
        $reg = $pay['registration_id'] ? SummerRegistration::find((int)$pay['registration_id']) : null;

        $ok = ($pay['status'] === 'succeeded');   // webhook may have beaten us here
        if (!$ok) {
            try {
                $resp = Paystack::verify($ref);
                $data = $resp['data']['data'] ?? null;
                if (self::chargeAcceptable($data, $pay)) {
                    $ok = true;
                    $this->finalise($pay, $reg, (string)($data['reference'] ?? $ref), $resp['raw'] ?? '', 'callback');
                } else {
                    Payment::markFailed($ref, 'failed', $resp['raw'] ?? '');
                }
            } catch (Throwable $e) {
                // A gateway we can't reach is not a failed payment. Leave the row
                // pending so the webhook (or an operator) can still confirm it,
                // and tell the parent the truth rather than "declined".
                error_log('[aft/pay/verify] ' . $e->getMessage());
                $this->view('pages/checkout-result', [
                    'title'     => 'Payment pending · ' . AFT_NAME,
                    'ok'        => false,
                    'pending'   => true,
                    'payment'   => $pay,
                    'reg'       => $reg,
                ], 'main');
                return;
            }
        }

        if ($ok) { $this->redirect('/summer/receipt/' . rawurlencode($ref)); return; }

        $this->view('pages/checkout-result', [
            'title'   => 'Payment not confirmed · ' . AFT_NAME,
            'ok'      => false,
            'payment' => Payment::findByReference($ref),
            'reg'     => $reg,
        ], 'main');
    }

    /**
     * GET /summer/receipt/{reference} — the confirmation itself.
     *
     * A stable, re-visitable, printable page rather than a one-shot render off
     * the gateway redirect: parents bookmark it, forward it, and come back to
     * it when a school asks for proof. Only a succeeded payment gets one.
     */
    public function receipt(string $ref): void {
        $pay = Payment::findByReference($ref);
        if (!$pay) { $this->notFound(); return; }

        $reg = $pay['registration_id'] ? SummerRegistration::find((int)$pay['registration_id']) : null;

        if ($pay['status'] !== 'succeeded') {
            // Not paid yet — send them where they can act, not to a dead receipt.
            $this->view('pages/checkout-result', [
                'title'   => 'Payment pending · ' . AFT_NAME,
                'ok'      => false,
                'pending' => $pay['status'] === 'pending',
                'payment' => $pay,
                'reg'     => $reg,
            ], 'main');
            return;
        }

        $this->view('pages/receipt', [
            'title'       => 'Receipt ' . $pay['reference'] . ' · ' . AFT_NAME,
            'description' => 'Payment receipt for the Afrotech Academy Summer School.',
            'payment'     => $pay,
            'reg'         => $reg,
            'addonRows'   => $reg ? self::addonBreakdown($reg) : [],
            'bodyClass'   => 'page-receipt',
        ], 'main');
    }

    /**
     * The priced add-ons a registration chose, itemised from the form version
     * that collected them. Shared by checkout and the receipt so the two always
     * agree about what was bought.
     *
     * @return array<int, array{0:string, 1:int}>
     */
    public static function addonBreakdown(array $reg): array {
        if ((int)($reg['addons_naira'] ?? 0) <= 0) return [];
        $answers = SummerRegistration::answers($reg);
        if (!$answers) return [];

        $def = FormDef::findVersion(FormDef::SUMMER, (int)($reg['form_version'] ?? 1)) ?? FormDef::live();
        $rows = [];
        foreach (FormEngine::resolve($def['fields'] ?? []) as $f) {
            if (!array_key_exists($f['key'], $answers)) continue;
            $v = $answers[$f['key']];
            $picked = array_map('strval', is_array($v) ? $v : [$v]);
            if (!empty($f['price']) && !in_array($picked[0] ?? '', ['', 'no'], true)) {
                $rows[] = [$f['priceLabel'] ?? $f['label'], (int)$f['price']];
            }
            foreach ($f['options'] ?? [] as $o) {
                if (!empty($o['price']) && in_array((string)$o['value'], $picked, true)) {
                    $rows[] = [$o['label'], (int)$o['price']];
                }
            }
        }
        return $rows;
    }

    /** POST /webhooks/paystack — server-to-server confirmation. */
    public function webhook(): void {
        $raw = (string) file_get_contents('php://input');
        $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        if (!Paystack::verifySignature($raw, $sig)) { http_response_code(401); echo 'bad signature'; exit; }
        $event = json_decode($raw, true);
        if (($event['event'] ?? '') === 'charge.success') {
            $ref = (string)($event['data']['reference'] ?? '');
            $pay = $ref ? Payment::findByReference($ref) : null;
            if ($pay) {
                // The signature proves the event came from Paystack, but not that
                // it settles THIS invoice — check the amount and currency too.
                if (self::chargeAcceptable($event['data'] ?? null, $pay)) {
                    $reg = $pay['registration_id'] ? SummerRegistration::find((int)$pay['registration_id']) : null;
                    $this->finalise($pay, $reg, $ref, $raw, 'webhook');
                } else {
                    error_log('[aft/pay/webhook] charge does not match invoice ' . $ref);
                }
            }
        }
        // Always 200: a non-2xx makes Paystack retry, and a mismatched or
        // unknown reference will never become acceptable on a retry.
        http_response_code(200); echo 'ok'; exit;
    }

    /**
     * Confirm a payment, once.
     *
     * Called from three places — the browser callback, the webhook, and an
     * operator clearing a bank transfer — which can overlap. The claim below is
     * the gate: whoever flips the row owns the side effects, everyone else
     * returns immediately. Without it, a parent gets two receipts and a
     * limited-use discount code burns two of its uses on one sale.
     *
     * Returns true if this call was the one that confirmed it.
     */
    private function finalise(array $pay, ?array $reg, string $providerRef, string $raw, string $via, int $adminId = 0, string $note = ''): bool {
        if (!Payment::claimSucceeded($pay['reference'], $providerRef, $raw, $via, $adminId, $note)) {
            return false;   // someone else got there first
        }

        if ($reg) {
            SummerRegistration::setPayment((int)$reg['id'], 'paid');
            SummerRegistration::setStatus((int)$reg['id'], 'confirmed', $adminId);
        }
        if (!empty($pay['discount_code'])) Discount::markUsed($pay['discount_code']);

        // The receipt is claimed separately so a payment confirmed before this
        // migration existed can't be emailed twice by a later re-verification.
        if (Payment::claimReceipt($pay['reference'])) {
            $fresh = Payment::findByReference($pay['reference']) ?? $pay;
            $vars = [
                'reg'       => $reg ?? [],
                'payment'   => $fresh,
                'addonRows' => $reg ? self::addonBreakdown($reg) : [],
                'receiptUrl'=> url('/summer/receipt/' . rawurlencode($pay['reference'])),
                'via'       => $via,
            ];
            $code = $reg['reg_code'] ?? $pay['reference'];

            Mailer::sendTo($pay['email'],
                'Payment received — ' . $code,
                render_email('payment-receipt', $vars),
                AFT_EMAIL);

            // The academy inbox needs to know a place is now actually paid for;
            // registrar staffing and campus lists run off this.
            Mailer::send('Payment confirmed · ' . $code . ' · ' . Setting::money((int)$pay['amount']),
                render_email('payment-admin', $vars),
                $pay['email']);
        }
        return true;
    }

    /**
     * Confirm a bank transfer by hand. Runs the same path a gateway payment
     * takes — same claim, same receipt, same confirmed status — so a family who
     * paid at the counter is not a second-class record with no confirmation.
     * Returns [ok, message].
     */
    public function confirmManually(array $pay, ?array $reg, int $adminId, string $note = ''): array {
        if ($pay['status'] === 'succeeded') return ['ok' => false, 'message' => 'That payment was already confirmed.'];
        $done = $this->finalise($pay, $reg, (string)$pay['reference'], '', 'operator', $adminId, $note);
        return $done
            ? ['ok' => true, 'message' => 'Payment confirmed — receipt emailed to ' . $pay['email'] . '.']
            : ['ok' => false, 'message' => 'That payment was confirmed elsewhere a moment ago.'];
    }
}
