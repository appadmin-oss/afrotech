<?php
namespace Admin;

class ContentController extends \Controller {
    private const KEYS = ['hero_eyebrow', 'hero_title', 'hero_subtitle', 'summer_intro'];

    public function index(): void {
        \Rbac::require('content.manage');
        $this->view('admin/content/index', [
            'title'   => 'Landing content · ' . AFT_NAME . ' Ops',
            'content' => \ContentBlock::all(),
            'keys'    => self::KEYS,
            'saved'   => flash_pop('content_saved'),
        ], 'admin');
    }

    public function save(): void {
        \Csrf::require();
        \Rbac::require('content.manage');
        foreach (self::KEYS as $k) {
            $val = $this->input($k, null);
            if ($val !== null) \ContentBlock::set($k, (string)$val);
        }
        flash_set('content_saved', 'Landing copy updated. Changes are live now.');
        $this->redirect('/admin/content');
    }
}
