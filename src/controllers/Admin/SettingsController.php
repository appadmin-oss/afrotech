<?php
namespace Admin;

class SettingsController extends \Controller {
    public function index(): void {
        \Rbac::require('settings.manage');
        $this->view('admin/settings/index', [
            'title'  => 'Settings · ' . AFT_NAME . ' Ops',
            'values' => \Setting::all(),
            'groups' => \Setting::groups(),
            'saved'  => flash_pop('settings_saved'),
        ], 'admin');
    }

    public function save(): void {
        \Csrf::require();
        \Rbac::require('settings.manage');
        $all = array_keys(\Setting::defaults());
        foreach (\Setting::groups() as $keys) {
            foreach ($keys as $k) {
                if (!in_array($k, $all, true)) continue;
                $v = $this->input($k, null);
                if ($v !== null) \Setting::set($k, (string)$v);
            }
        }
        flash_set('settings_saved', 'Program settings updated — changes are live across the site.');
        $this->redirect('/admin/settings');
    }
}
