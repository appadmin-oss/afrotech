<?php
namespace Admin;

class DiscountsController extends \Controller {
    public function index(): void {
        \Rbac::require('discounts.manage');
        $this->view('admin/discounts/index', [
            'title'     => 'Discount codes · ' . AFT_NAME . ' Ops',
            'discounts' => \Discount::all(),
            'symbol'    => \Setting::get('currency_symbol', '₦'),
        ], 'admin');
    }

    public function edit(string $id = ''): void {
        \Rbac::require('discounts.manage');
        $this->view('admin/discounts/edit', [
            'title'    => ($id ? 'Edit code' : 'New code') . ' · ' . AFT_NAME . ' Ops',
            'discount' => $id ? \Discount::find((int)$id) : null,
        ], 'admin');
    }

    public function save(): void {
        \Csrf::require();
        \Rbac::require('discounts.manage');
        $d = [
            'id'          => (int)$this->input('id', 0),
            'code'        => (string)$this->input('code', ''),
            'description' => (string)$this->input('description', ''),
            'type'        => (string)$this->input('type', 'percent'),
            'value'       => (int)$this->input('value', 0),
            'min_amount'  => (int)$this->input('min_amount', 0),
            'max_uses'    => $this->input('max_uses', ''),
            'starts_at'   => (string)$this->input('starts_at', ''),
            'ends_at'     => (string)$this->input('ends_at', ''),
            'status'      => (string)$this->input('status', 'active'),
        ];
        if (trim($d['code']) !== '') \Discount::save($d);
        $this->redirect('/admin/discounts');
    }

    public function delete(string $id): void {
        \Csrf::require();
        \Rbac::require('discounts.manage');
        \Discount::delete((int)$id);
        $this->redirect('/admin/discounts');
    }
}
