<?php
namespace Admin;

class PaymentsController extends \Controller {
    public function index(): void {
        \Rbac::require('payments.view');
        $payments = \Payment::all();
        $collected = 0;
        $outstanding = 0;
        foreach ($payments as $p) {
            if ($p['status'] === 'succeeded') $collected += (int)$p['amount'];
            elseif ($p['status'] === 'pending') $outstanding += (int)$p['amount'];
        }
        $this->view('admin/payments/index', [
            'title'       => 'Payments · ' . AFT_NAME . ' Ops',
            'payments'    => $payments,
            'collected'   => $collected,
            'outstanding' => $outstanding,
            'symbol'      => \Setting::get('currency_symbol', '₦'),
            'canConfirm'  => \Rbac::can('payments.confirm'),
            'audited'     => \Payment::hasConfirmationColumns(),
            'flash'       => flash_pop('payments_msg'),
            'flashErr'    => flash_pop('payments_err'),
        ], 'admin');
    }

    /**
     * POST /admin/payments/{ref}/confirm — clear a bank transfer by hand.
     *
     * Families paying by transfer used to land in a worse place than card
     * payers: an operator could flip the registration to "paid", but the payment
     * row stayed pending and no receipt was ever sent. This runs the same
     * confirmation path a gateway payment takes, so the ledger, the
     * registration status and the receipt all agree.
     */
    public function confirm(string $ref): void {
        \Csrf::require();
        \Rbac::require('payments.confirm');

        $pay = \Payment::findByReference($ref);
        if (!$pay) { $this->notFound(); return; }

        $reg  = $pay['registration_id'] ? \SummerRegistration::find((int)$pay['registration_id']) : null;
        $note = (string)$this->input('note', '');

        $res = (new \PaymentController())->confirmManually($pay, $reg, \Auth::id(), $note);
        flash_set($res['ok'] ? 'payments_msg' : 'payments_err', $res['message']);
        $this->redirect('/admin/payments');
    }
}
