<?php
namespace Admin;

/**
 * Admin\FormsController — the form builder's back end.
 *
 * The builder is a single-page editor that talks JSON: it loads a definition,
 * autosaves drafts, and publishes a new version when the operator is happy.
 * Every write goes through FormEngine::normalizeFields, so a definition that
 * would break the public form (missing core field, forward-referencing rule,
 * duplicate key, uncompilable pattern) is refused with a readable reason
 * rather than being stored and discovered by a parent.
 */
class FormsController extends \Controller {

    /** GET /admin/forms — the builder. */
    public function index(): void {
        \Rbac::require('forms.view');
        $key = $this->formKey();

        $draft = \FormDef::draft($key);
        $live  = \FormDef::live($key);

        $this->view('admin/forms/edit', [
            'title'      => 'Form builder · ' . AFT_NAME . ' Ops',
            'formKey'    => $key,
            'registry'   => \FormDef::registry(),
            'draft'      => $draft,
            'live'       => $live,
            'hasDraft'   => ($draft['status'] ?? '') === 'draft' && (int)($draft['id'] ?? 0) > 0,
            'versions'   => \FormDef::versions($key),
            'canManage'  => \Rbac::can('forms.manage'),
            'migrated'   => \Database::available(),
            'saved'      => flash_pop('forms_msg'),
            'symbol'     => \Setting::get('currency_symbol', '₦'),
            'fee'        => \Setting::fee(),
            'pageCss'    => ['form.css', 'builder.css'],
            'pageJs'     => ['form-builder.js'],
        ], 'admin');
    }

    /** POST /admin/forms/draft — autosave (JSON in, JSON out). */
    public function saveDraft(): void {
        \Csrf::require();
        \Rbac::require('forms.manage');
        $key = $this->formKey();

        $payload = $this->payload();
        $v = \FormEngine::normalizeFields($payload['fields']);
        if (empty($v['ok'])) {
            $this->json(['ok' => false, 'error' => $v['error'], 'message' => $v['detail'] ?? 'That form has a problem.'], 422);
        }
        $ok = \FormDef::saveDraft($key, $v['fields'], $payload['name'],
            \FormEngine::normalizeSettings($payload['settings']), \Auth::id());
        $this->json($ok
            ? ['ok' => true, 'message' => 'Draft saved.', 'fields' => $v['fields']]
            : ['ok' => false, 'message' => 'Draft could not be saved — the database is unreachable.'], $ok ? 200 : 503);
    }

    /** POST /admin/forms/publish — make the draft live as a new version. */
    public function publish(): void {
        \Csrf::require();
        \Rbac::require('forms.manage');
        $key = $this->formKey();

        $payload = $this->payload();
        $v = \FormEngine::normalizeFields($payload['fields']);
        if (empty($v['ok'])) {
            $this->json(['ok' => false, 'error' => $v['error'], 'message' => $v['detail'] ?? 'That form has a problem.'], 422);
        }
        $res = \FormDef::publish($key, $v['fields'], $payload['name'],
            \FormEngine::normalizeSettings($payload['settings']), \Auth::id());
        if (empty($res['ok'])) {
            $this->json(['ok' => false, 'message' => $res['error'] === 'db_unavailable'
                ? 'The database is unreachable — nothing was published.'
                : 'Publish failed. Nothing changed; the previous version is still live.'], 503);
        }
        $this->json(['ok' => true, 'version' => $res['version'],
            'message' => 'Published v' . $res['version'] . ' — live on /summer now.']);
    }

    /** POST /admin/forms/discard — throw the working copy away. */
    public function discard(): void {
        \Csrf::require();
        \Rbac::require('forms.manage');
        \FormDef::discardDraft($this->formKey());
        flash_set('forms_msg', 'Draft discarded — you are back to the live version.');
        $this->redirect('/admin/forms?form=' . urlencode($this->formKey()));
    }

    /** POST /admin/forms/rollback — republish an archived version. */
    public function rollback(): void {
        \Csrf::require();
        \Rbac::require('forms.manage');
        $key = $this->formKey();
        $version = (int)$this->input('version', 0);
        $res = \FormDef::rollback($key, $version, \Auth::id());
        flash_set('forms_msg', !empty($res['ok'])
            ? 'Rolled back — v' . $version . ' is live again as v' . $res['version'] . '.'
            : 'That version could not be restored.');
        $this->redirect('/admin/forms?form=' . urlencode($key));
    }

    /** GET /admin/forms/preview — the live form, rendered inert. */
    public function preview(): void {
        \Rbac::require('forms.view');
        $key = $this->formKey();
        $def = ($this->input('which', 'draft') === 'live') ? \FormDef::live($key) : \FormDef::draft($key);
        $this->view('admin/forms/preview', [
            'title'   => 'Preview · ' . AFT_NAME . ' Ops',
            'def'     => $def,
            'formKey' => $key,
            'symbol'  => \Setting::get('currency_symbol', '₦'),
            'fee'     => \Setting::fee(),
            'pageCss' => ['form.css'],
            'pageJs'  => ['form-logic.js'],
        ], 'preview');
    }

    /** GET /admin/forms/export.json — the definition, for backup or transfer. */
    public function export(): void {
        \Rbac::require('forms.view');
        $key = $this->formKey();
        $def = \FormDef::live($key);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $key . '-v' . (int)$def['version'] . '.json"');
        echo json_encode([
            'form_key' => $key,
            'version'  => (int)$def['version'],
            'name'     => $def['name'],
            'settings' => $def['settings'],
            'fields'   => $def['fields'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ── helpers ───────────────────────────────────────────────────── */

    private function formKey(): string {
        $key = (string)$this->input('form', \FormDef::SUMMER);
        return \FormDef::known($key) ? $key : \FormDef::SUMMER;
    }

    /** Normalise the JSON body the builder posts. */
    private function payload(): array {
        $fields = $this->input('fields', []);
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }
        $settings = $this->input('settings', []);
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        return [
            'fields'   => is_array($fields) ? $fields : [],
            'settings' => is_array($settings) ? $settings : [],
            'name'     => (string)$this->input('name', 'Form'),
        ];
    }
}
