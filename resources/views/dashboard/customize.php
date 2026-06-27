<?php $title = 'Customize Dashboard'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Customize Dashboard</h4>
    <div>
        <button class="btn btn-outline-primary btn-sm me-2" onclick="resetLayout()"><i class="bi bi-arrow-counterclockwise"></i> Reset Layout</button>
        <button class="btn btn-primary btn-sm" onclick="saveLayout()"><i class="bi bi-save"></i> Save Layout</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>Add Widget</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Widget</label>
                    <select id="widgetKey" class="form-select form-select-sm">
                        <?php foreach ($available as $a): ?>
                        <option value="<?= e($a['widget_key']) ?>" data-icon="<?= e($a['icon']) ?>"><?= e($a['widget_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Title</label>
                    <input type="text" id="widgetTitle" class="form-control form-control-sm" placeholder="Widget title">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Size</label>
                    <select id="widgetSize" class="form-select form-select-sm">
                        <option value="col-md-4">Small (1/3)</option>
                        <option value="col-md-6" selected>Medium (1/2)</option>
                        <option value="col-md-8">Large (2/3)</option>
                        <option value="col-md-12">Full Width</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-sm w-100" onclick="addWidget()"><i class="bi bi-plus"></i> Add to Dashboard</button>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Tips</h6></div>
            <div class="card-body small text-muted">
                <p class="mb-1"><i class="bi bi-arrows-move me-1"></i>Drag widgets to reorder</p>
                <p class="mb-1"><i class="bi bi-x-circle me-1"></i>Click &times; to remove</p>
                <p class="mb-0"><i class="bi bi-pencil me-1"></i>Click title to rename</p>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-layout-widget me-1"></i>Dashboard Layout</h6>
                <small class="text-muted">Drag to reorder</small>
            </div>
            <div class="card-body">
                <div class="row g-3" id="widgetContainer">
                    <?php if (empty($widgets)): ?>
                    <div class="col-12">
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-grid-3x3-gap display-5 d-block mb-2"></i>
                            <p>No widgets added yet. Add widgets from the left panel.</p>
                        </div>
                    </div>
                    <?php else: foreach ($widgets as $w): ?>
                    <div class="col-md-<?= e($w['width'] ?? 6) ?> widget-item" data-id="<?= $w['id'] ?>">
                        <div class="card h-100 border-primary border-opacity-25">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold small widget-title" contenteditable="true"><?= e($w['widget_type'] ?? 'Widget') ?></span>
                                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeWidget(<?= $w['id'] ?>)"><i class="bi bi-x"></i></button>
                            </div>
                            <div class="card-body widget-preview text-muted small d-flex align-items-center justify-content-center" style="min-height:120px;">
                                <i class="bi bi-grid me-2"></i><?= e($w['widget_type'] ?? 'Widget') ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addWidget() {
    const key = document.getElementById('widgetKey').value;
    const title = document.getElementById('widgetTitle').value.trim() || key;
    const size = document.getElementById('widgetSize').value;
    const container = document.getElementById('widgetContainer');

    if (container.querySelector('.col-12 > .text-center')) {
        container.innerHTML = '';
    }

    const col = document.createElement('div');
    col.className = size + ' widget-item';
    col.innerHTML = `
        <div class="card h-100 border-primary border-opacity-25">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold small widget-title" contenteditable="true">${e_html(title)}</span>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="this.closest('.widget-item').remove()"><i class="bi bi-x"></i></button>
            </div>
            <div class="card-body widget-preview text-muted small d-flex align-items-center justify-content-center" style="min-height:120px;">
                <i class="bi bi-grid me-2"></i>${e_html(title)}
            </div>
        </div>`;
    container.appendChild(col);
    document.getElementById('widgetTitle').value = '';
}

function saveLayout() {
    const items = [];
    document.querySelectorAll('#widgetContainer .widget-item').forEach(el => {
        items.push({ widget_key: el.dataset.key || 'stats', title: el.querySelector('.widget-title').textContent.trim(), size: el.className.split(' ').find(c => c.startsWith('col-')) || 'col-md-6' });
    });
    fetch('/dashboard/widgets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: JSON.stringify({ widgets: items })
    }).then(r => r.json()).then(d => alert(d.success ? 'Layout saved!' : 'Error: ' + (d.message || 'unknown'))).catch(() => alert('Failed to save layout.'));
}

function resetLayout() {
    if (!confirm('Reset dashboard layout to default?')) return;
}

function removeWidget(id) {
    if (!confirm('Remove this widget?')) return;
}

function e_html(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
