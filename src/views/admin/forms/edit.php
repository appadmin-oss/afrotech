<?php
/**
 * Form builder — the editing surface for the public registration form.
 *
 * The page is a thin shell: it hands the current definition, the field-type
 * catalogue and the operator catalogue to form-builder.js as JSON and lets
 * that render the editor. Publishing posts back through Admin\FormsController,
 * which re-validates everything server-side.
 *
 * @var string $formKey @var array $registry @var array $draft @var array $live
 * @var bool $hasDraft @var array $versions @var bool $canManage @var bool $migrated
 * @var string|null $saved @var string $symbol @var int $fee
 */
$meta = $registry[$formKey] ?? ['name' => 'Form', 'note' => ''];

$bootstrap = [
    'formKey'   => $formKey,
    'name'      => (string)($draft['name'] ?? $meta['name']),
    'fields'    => $draft['fields'] ?? [],
    'settings'  => $draft['settings'] ?? FormEngine::normalizeSettings([]),
    'liveVersion' => (int)($live['version'] ?? 1),
    'hasDraft'  => (bool)$hasDraft,
    'canManage' => (bool)$canManage,
    'symbol'    => $symbol,
    'fee'       => (int)$fee,
    'csrf'      => Csrf::token(),
    'endpoints' => [
        'draft'   => url('/admin/forms/draft'),
        'publish' => url('/admin/forms/publish'),
        // The preview is the real page in an iframe, not a second renderer —
        // so it cannot drift from what /summer actually serves.
        'preview' => url('/admin/forms/preview?form=' . urlencode($formKey)),
    ],
    'catalogue' => [
        'types'      => array_map(
            fn($t) => ['value' => $t, 'label' => FormEngine::TYPE_LABELS[$t] ?? $t],
            array_merge(FormEngine::INPUT_TYPES, FormEngine::LAYOUT_TYPES)
        ),
        'layoutTypes' => FormEngine::LAYOUT_TYPES,
        'optionTypes' => FormEngine::OPTION_TYPES,
        'operators'   => FormEngine::OPERATORS,
        'sources'     => FormEngine::OPTION_SOURCES,
        'mappable'    => FormEngine::MAPPABLE,
        'requiredMaps' => FormEngine::REQUIRED_MAPS,
    ],
];
?>
<div class="topbar">
  <div>
    <h1><?= e($meta['name']) ?></h1>
    <p class="muted" style="font-size:var(--fs-sm);max-width:70ch"><?= e($meta['note']) ?></p>
  </div>
  <div class="filters">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/summer')) ?>" target="_blank" rel="noopener">Open /summer ↗</a>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/forms/export.json?form=' . urlencode($formKey))) ?>">Export JSON</a>
  </div>
</div>

<?php if ($saved): ?><div class="alert alert--ok" style="margin-bottom:var(--sp-5)"><?= e($saved) ?></div><?php endif; ?>

<?php if (!$migrated): ?>
  <div class="alert alert--err" style="margin-bottom:var(--sp-5)">
    <strong>The database is unreachable.</strong> You are looking at the built-in default form. Edits cannot be
    saved until the connection is restored and <code>database/migrations/2026-07-form-builder.sql</code> has been imported.
  </div>
<?php elseif (!$canManage): ?>
  <div class="alert alert--ok" style="margin-bottom:var(--sp-5)">
    You have read-only access to the builder. Editing and publishing the live public form needs the
    <code>forms.manage</code> permission.
  </div>
<?php endif; ?>

<div class="fbx" data-builder>
  <div class="fbx__bar">
    <div class="fbx__bar-main">
      <input class="fbx__name" data-fbx-name value="<?= e((string)($draft['name'] ?? $meta['name'])) ?>"
             aria-label="Form name" <?= $canManage ? '' : 'disabled' ?>>
      <div class="fbx__meta mono">
        Live v<?= (int)($live['version'] ?? 1) ?>
        · <span data-fbx-count><?= count($draft['fields'] ?? []) ?></span> blocks
        · <span data-fbx-state><?= $hasDraft ? 'unpublished draft' : 'in sync with live' ?></span>
      </div>
    </div>
    <div class="fbx__bar-actions">
      <button class="btn btn--ghost btn--sm" data-fbx-tab="build">Build</button>
      <button class="btn btn--ghost btn--sm" data-fbx-tab="logic">Logic map</button>
      <button class="btn btn--ghost btn--sm" data-fbx-tab="settings">Settings</button>
      <button class="btn btn--ghost btn--sm" data-fbx-tab="preview">Preview</button>
      <?php if ($canManage): ?>
        <button class="btn btn--ink btn--sm" data-fbx-save>Save draft</button>
        <button class="btn btn--primary btn--sm" data-fbx-publish>Publish</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="fbx__msg" data-fbx-msg hidden></div>

  <!-- Build ------------------------------------------------------- -->
  <section class="fbx__pane" data-fbx-pane="build">
    <div class="fbx__list" data-fbx-list></div>
    <div class="fbx__add">
      <span class="mono muted">Add a block:</span>
      <div class="fbx__add-grid" data-fbx-add></div>
    </div>
  </section>

  <!-- Logic map --------------------------------------------------- -->
  <section class="fbx__pane" data-fbx-pane="logic" hidden>
    <p class="admin-hint muted">
      Every conditional rule in the form, in order. A rule can only look at a field that appears
      <em>above</em> it — that's enforced on save, because a rule about a later answer could never be true.
    </p>
    <div data-fbx-logicmap></div>
  </section>

  <!-- Settings ---------------------------------------------------- -->
  <section class="fbx__pane" data-fbx-pane="settings" hidden>
    <div class="fbx__settings" data-fbx-settings></div>
  </section>

  <!-- Preview ----------------------------------------------------- -->
  <section class="fbx__pane" data-fbx-pane="preview" hidden>
    <p class="admin-hint muted">
      The real renderer with the real logic engine — conditional fields appear and disappear, steps
      advance, and the total moves exactly as a parent will see it. Submitting is disabled.
    </p>
    <div class="fbx__preview">
      <div class="fbx__preview-note mono muted" data-fbx-preview-note>Saving the draft, then loading it…</div>
      <iframe class="fbx__frame" data-fbx-frame title="Form preview" loading="lazy"></iframe>
    </div>
  </section>
</div>

<!-- Version history ---------------------------------------------- -->
<div class="panel" style="margin-top:var(--sp-6)">
  <div class="panel__head">
    <h2>Version history</h2>
    <?php if ($hasDraft && $canManage): ?>
      <form method="post" action="<?= e(url('/admin/forms/discard')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="form" value="<?= e($formKey) ?>">
        <button class="btn btn--ghost btn--sm">Discard draft</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$versions): ?>
    <p class="muted">No published versions recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Version</th><th>Name</th><th>Status</th><th>Published</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($versions as $v): ?>
          <tr>
            <td class="mono">v<?= (int)$v['version'] ?></td>
            <td><?= e($v['name']) ?></td>
            <td><span class="chip chip--<?= $v['status'] === 'live' ? 'confirmed' : 'unpaid' ?>"><?= e($v['status']) ?></span></td>
            <td class="mono" style="font-size:var(--fs-xs)"><?= e(datetime_pretty($v['created_at'])) ?></td>
            <td style="text-align:right">
              <?php if ($v['status'] !== 'live' && $canManage): ?>
                <form method="post" action="<?= e(url('/admin/forms/rollback')) ?>"
                      onsubmit="return confirm('Republish v<?= (int)$v['version'] ?> as the live form?')">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="form" value="<?= e($formKey) ?>">
                  <input type="hidden" name="version" value="<?= (int)$v['version'] ?>">
                  <button class="btn btn--ghost btn--sm">Roll back to this</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script type="application/json" id="fbx-bootstrap"><?= json_encode($bootstrap,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
