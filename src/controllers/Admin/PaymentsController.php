<?php
namespace Admin;

class PaymentsController extends \Controller {
    public function index(): void {
        \Rbac::require('payments.view');
        $payments = \Payment::all();
        $collected = 0;
        foreach ($payments as $p) if ($p['status'] === 'succeeded') $collected += (int)$p['amount'];
        $this->view('admin/payments/index', [
            'title'     => 'Payments · ' . AFT_NAME . ' Ops',
            'payments'  => $payments,
            'collected' => $collected,
            'symbol'    => \Setting::get('currency_symbol', '₦'),
        ], 'admin');
    }
}
