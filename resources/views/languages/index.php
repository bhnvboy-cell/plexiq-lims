<?php $title = 'Language Management'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-translate me-2"></i>Language Management</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLanguageModal"><i class="bi bi-plus-lg"></i> Add Language</button>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-globe me-1"></i>Languages</h6></div>
            <div class="list-group list-group-flush">
                <?php foreach ($languages as $lang): ?>
                <a href="?lang=<?= e($lang['language_code']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($selectedLang ?? 'en') === $lang['language_code'] ? 'active' : '' ?>">
                    <div>
                        <span class="fw-bold"><?= e($lang['language_name']) ?></span>
                        <small class="d-block text-muted"><?= e($lang['language_code']) ?></small>
                    </div>
                    <div>
                        <?php if ($lang['is_default']): ?><span class="badge bg-success me-1">Default</span><?php endif; ?>
                        <?php if ($lang['is_active']): ?><span class="badge bg-info">Active</span><?php else: ?><span class="badge bg-secondary">Inactive</span><?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-pencil me-1"></i>Translations: <?= e($selectedLangName ?? 'English') ?></h6>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="saveTranslations()"><i class="bi bi-save"></i> Save</button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($translations)): ?>
                <div class="text-center text-muted py-4">No translations available for this language.</div>
                <?php else: ?>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:35%">Key</th>
                                <th style="width:65%">Translation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($translations as $key => $value): ?>
                            <tr>
                                <td><code class="small"><?= e($key) ?></code></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm translation-input" data-key="<?= e($key) ?>" value="<?= e($value) ?>" placeholder="Enter translation...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Language Modal -->
<div class="modal fade" id="addLanguageModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/languages">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Language</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Language Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. French, German, Spanish">
        </div>
        <div class="mb-3">
            <label class="form-label">Locale Code <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control" required placeholder="e.g. fr, de, es">
            <div class="form-text">ISO 639-1 two-letter code.</div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <label class="form-check-label">Active</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default" value="1">
            <label class="form-check-label">Set as Default</label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i> Add Language</button>
    </div>
</form>
</div></div></div>

<script>
function saveTranslations() {
    const lang = '<?= e($selectedLang ?? 'en') ?>';
    const inputs = document.querySelectorAll('.translation-input');
    const data = {};
    inputs.forEach(inp => { data[inp.dataset.key] = inp.value; });

    fetch('/languages/' + lang + '/translations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: JSON.stringify({ translations: data })
    })
    .then(r => r.json())
    .then(d => { alert(d.success ? 'Translations saved!' : 'Error: ' + d.message); })
    .catch(() => alert('Failed to save translations.'));
}
</script>
