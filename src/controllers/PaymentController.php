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

    /** GET /summer/pay/callback?reference=… — verify + finalise. */
    public function callback(): void {
        $ref = (string)$this->input('reference', $this->input('trxref', ''));
        $pay = $ref ? Payment::findByReference($ref) : null;
        if (!$pay) { $this->notFound(); return; }
        $reg = $pay['registration_id'] ? SummerRegistration::find((int)$pay['registration_id']) : null;

        $ok = false;
        if ($pay['status'] === 'succeeded') {
            $ok = true; // idempotent — already verified
        } else {
            try {
                $resp = Paystack::verify($ref);
                $data = $resp['data']['data'] ?? null;
                if (($data['status'] ?? '') === 'success' && (int)($data['amount'] ?? 0) >= (int)$pay['amount'] * 100) {
                    $ok = true;
                    $this->finalise($pay, $reg, $data['reference'] ?? $ref, $resp['raw'] ?? '');
                } else {
                    Payment::markFailed($ref, 'failed', $resp['raw'] ?? '');
                }
            } catch (Throwable $e) {
                error_log('[aft/pay/verify] ' . $e->getMessage());
            }
        }

        $this->view('pages/checkout-result', [
            'title'   => ($ok ? 'Payment confirmed' : 'Payment not confirmed') . ' · ' . AFT_NAME,
            'ok'      => $ok,
            'payment' => Payment::findByReference($ref),
            'reg'     => $reg,
        ], 'main');
    }

    /** POST /webhooks/paystack — server-to-server confirmation. */
    public function webhook(): void {
        $raw = (string) file_get_contents('php://input');
        $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        if (!Paystack::verifySignature($raw, $sig)) { http_response_code(401); echo 'bad signature'; exit; }
        $event = json_decode($raw, true);
        if (($event['event'] ?? '') === 'charge.success') {
            $ref = $event['data']['reference'] ?? '';
            $pay = $ref ? Payment::findByReference($ref) : null;
            if ($pay && $pay['status'] !== 'succeeded') {
                $reg = $pay['registration_id'] ? SummerRegistration::find((int)$pay['registration_id']) : null;
                $this->finalise($pay, $reg, $event['data']['reference'] ?? $ref, $raw);
            }
        }
        http_response_code(200); echo 'ok'; exit;
    }

    /** Shared success side-effects: mark paid, bump discount, email receipt. */
    private function finalise(array $pay, ?array $reg, string $providerRef, string $raw): void {
        Payment::markSucceeded($pay['reference'], $providerRef, $raw);
        if ($reg) {
            SummerRegistration::setPayment((int)$reg['id'], 'paid');
            SummerRegistration::setStatus((int)$reg['id'], 'confirmed');
        }
        if (!empty($pay['discount_code'])) Discount::markUsed($pay['discount_code']);
        Mailer::sendTo($pay['email'],
            'Payment received — ' . ($reg['reg_code'] ?? $pay['reference']),
            render_email('payment-receipt', [
                'reg'     => $reg ?? [],
                'payment' => $pay,
            ]));
    }
}
