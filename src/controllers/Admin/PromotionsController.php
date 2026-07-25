<?php
namespace Admin;

class PromotionsController extends \Controller {
    public function index(): void {
        \Rbac::require('promotions.manage');
        $this->view('admin/promotions/index', [
            'title'      => 'Promotions · ' . AFT_NAME . ' Ops',
            'promotions' => \Promotion::all(),
        ], 'admin');
    }

    public function edit(string $id = ''): void {
        \Rbac::require('promotions.manage');
        $this->view('admin/promotions/edit', [
            'title' => ($id ? 'Edit promotion' : 'New promotion') . ' · ' . AFT_NAME . ' Ops',
            'promo' => $id ? \Promotion::find((int)$id) : null,
        ], 'admin');
    }

    public function save(): void {
        \Csrf::require();
        \Rbac::require('promotions.manage');
        $d = [
            'id'             => (int)$this->input('id', 0),
            'title'          => (string)$this->input('title', ''),
            'body'           => (string)$this->input('body', ''),
            'badge'          => (string)$this->input('badge', ''),
            'cta_label'      => (string)$this->input('cta_label', ''),
            'cta_href'       => (string)$this->input('cta_href', ''),
            'tone'           => (string)$this->input('tone', 'red'),
            'show_countdown' => $this->input('show_countdown', '') ? 1 : 0,
            'starts_at'      => (string)$this->input('starts_at', ''),
            'ends_at'        => (string)$this->input('ends_at', ''),
            'sort'           => (int)$this->input('sort', 0),
            'status'         => (string)$this->input('status', 'active'),
        ];
        if (trim($d['title']) !== '') \Promotion::save($d);
        $this->redirect('/admin/promotions');
    }

    public function delete(string $id): void {
        \Csrf::require();
        \Rbac::require('promotions.manage');
        \Promotion::delete((int)$id);
        $this->redirect('/admin/promotions');
    }
}
