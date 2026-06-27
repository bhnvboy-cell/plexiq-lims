<?php $title = 'My Workspace'; layout('app'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-grid-3x3-gap-fill me-2"></i>My Workspace</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addShortcutModal">
        <i class="bi bi-plus"></i> Add Shortcut
    </button>
</div>

<div class="row sortable-grid" id="shortcutGrid">
    <?php if (empty($shortcuts)): ?>
    <div class="col-12">
        <div class="text-center py-5 text-muted">
            <i class="bi bi-hand-index-thumb" style="font-size:3rem;"></i>
            <p class="mt-2">No shortcuts yet. Add your first one!</p>
        </div>
    </div>
    <?php else: foreach ($shortcuts as $s): ?>
    <div class="col-6 col-md-4 col-lg-3 col-xl-2 shortcut-tile" data-id="<?= $s['id'] ?>">
        <a href="<?= e($s['url']) ?>" class="card h-100 text-decoration-none shortcut-card">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center py-4">
                <div class="shortcut-icon rounded-circle d-flex align-items-center justify-content-center mb-3"
                     style="width:56px;height:56px;background:<?= e($s['color']) ?>15;color:<?= e($s['color']) ?>;">
                    <i class="bi <?= e($s['icon']) ?> fs-3"></i>
                </div>
                <h6 class="card-title mb-0 small fw-semibold"><?= e($s['title']) ?></h6>
            </div>
            <div class="shortcut-actions position-absolute top-0 end-0 p-1 opacity-0" style="transition:opacity .2s;">
                <button class="btn btn-sm btn-outline-danger border-0" onclick="removeShortcut(<?= $s['id'] ?>, event)" title="Remove">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </a>
    </div>
    <?php endforeach; endif; ?>
</div>

<form method="POST" id="removeForm" style="display:none;"><input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>"></form>

<!-- Add Shortcut Modal -->
<div class="modal fade" id="addShortcutModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/workspace/shortcuts">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Shortcut</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Create Batch">
        </div>
        <div class="mb-3">
            <label class="form-label">URL</label>
            <input type="text" name="url" class="form-control" required placeholder="/batches/create">
        </div>
        <div class="row g-2 mb-3">
            <div class="col-8">
                <label class="form-label">Icon</label>
                <select name="icon" class="form-select" id="iconSelect">
                    <option value="bi-box-seam">📦 Create Batch</option>
                    <option value="bi-clipboard-data">📋 Result Entry</option>
                    <option value="bi-check-circle">✓ Review</option>
                    <option value="bi-boxes">📊 Batches</option>
                    <option value="bi-collection">📁 Samples</option>
                    <option value="bi-file-text">📄 COA</option>
                    <option value="bi-bar-chart-steps">📈 SPC</option>
                    <option value="bi-cpu">🔧 Instruments</option>
                    <option value="bi-exclamation-triangle">⚠ OOS</option>
                    <option value="bi-shield-check">🛡 CAPA</option>
                    <option value="bi-sliders">⚙ Master Data</option>
                    <option value="bi-people">👥 Users</option>
                    <option value="bi-link">🔗 Custom</option>
                    <option value="bi-star">⭐ Favorite</option>
                    <option value="bi-graph-up">📈 Trend</option>
                </select>
            </div>
            <div class="col-4">
                <label class="form-label">Color</label>
                <input type="color" name="color" class="form-control form-control-color" value="#0d6efd" style="height:38px;">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i> Add Shortcut</button>
    </div>
</form>
</div></div></div>

<style>
.shortcut-card {
    transition: transform .15s, box-shadow .15s;
    position: relative;
    border: 1px solid rgba(0,0,0,.08);
}
.shortcut-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,.1);
}
.shortcut-card:hover .shortcut-actions {
    opacity: 1 !important;
}
.sortable-ghost {
    opacity: .4;
    border: 2px dashed #0d6efd !important;
}
.shortcut-tile {
    min-height: 140px;
}
</style>

<script>
let sortable = new Sortable(document.getElementById('shortcutGrid'), {
    animation: 150,
    ghostClass: 'sortable-ghost',
    onEnd: function() {
        const items = [];
        document.querySelectorAll('.shortcut-tile').forEach((el, i) => {
            items.push({ id: parseInt(el.dataset.id), sort_order: i });
        });
        fetch('/workspace/shortcuts/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[name=_csrf_token]').value },
            body: JSON.stringify(items)
        });
    }
});

function removeShortcut(id, e) {
    e.preventDefault();
    if (confirm('Remove this shortcut?')) {
        const form = document.getElementById('removeForm');
        form.action = '/workspace/shortcuts/' + id + '/delete';
        form.submit();
    }
}
</script>
