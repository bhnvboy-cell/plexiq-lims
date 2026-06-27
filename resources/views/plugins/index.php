<?php $title = 'Plugins'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-puzzle me-2"></i>Plugin Manager</h4>
    <div class="d-flex gap-2">
        <span class="badge bg-primary bg-opacity-10 text-primary align-self-center"><?= count($plugins) ?> plugins</span>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('installInput').click()"><i class="bi bi-upload"></i> Install Plugin</button>
        <input type="file" id="installInput" accept=".zip" style="display:none" onchange="installPlugin(this.files[0])">
    </div>
</div>

<?php if (empty($plugins)): ?>
<div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-puzzle display-4 d-block mb-3"></i><p>No plugins installed.</p></div></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($plugins as $p): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 <?= $p['is_active'] ? 'border-success border-opacity-25' : 'border-secondary border-opacity-10' ?>">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-1"><?= e($p['name']) ?></h5>
                        <small class="text-muted">v<?= e($p['version'] ?? '1.0.0') ?></small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" <?= $p['is_active'] ? 'checked' : '' ?> onchange="togglePlugin(<?= $p['id'] ?>, this.checked)">
                    </div>
                </div>
                <p class="card-text small text-muted flex-grow-1"><?= e($p['description'] ?? 'No description available.') ?></p>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                    <div>
                        <span class="badge bg-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span>
                        <span class="badge bg-info bg-opacity-10 text-info ms-1"><?= e($p['author'] ?? 'Unknown') ?></span>
                    </div>
                    <form method="POST" action="/plugins/<?= $p['id'] ?>/uninstall" class="d-inline" onsubmit="return confirm('Uninstall <?= e($p['name']) ?>? This will remove all plugin data.')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function togglePlugin(id, active) {
    fetch('/plugins/' + id + '/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: JSON.stringify({ is_active: active })
    })
    .then(r => r.json())
    .then(d => { if (!d.success) { alert('Failed to toggle plugin.'); location.reload(); } })
    .catch(() => { alert('Error toggling plugin.'); location.reload(); });
}

function installPlugin(file) {
    if (!file) return;
    const form = new FormData();
    form.append('plugin', file);
    fetch('/plugins/install', {
        method: 'POST',
        headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: form
    })
    .then(r => r.json())
    .then(d => { alert(d.success ? 'Plugin installed successfully!' : 'Install failed: ' + d.message); location.reload(); })
    .catch(() => { alert('Install error.'); location.reload(); });
}
</script>
