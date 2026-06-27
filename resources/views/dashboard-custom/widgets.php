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
                    <label class="form-label small">Widget Type</label>
                    <select id="widgetType" class="form-select form-select-sm">
                        <option value="stats">Statistics Card</option>
                        <option value="chart">Chart</option>
                        <option value="table">Recent Items Table</option>
                        <option value="list">Activity Feed</option>
                        <option value="alert">Alerts</option>
                        <option value="kpi">KPI Gauge</option>
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
                    <div class="<?= e($w['size'] ?? 'col-md-6') ?> widget-item" data-id="<?= $w['id'] ?>" data-type="<?= e($w['type']) ?>">
                        <div class="card h-100 border-primary border-opacity-25">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold small widget-title" contenteditable="true"><?= e($w['title']) ?></span>
                                <div>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeWidget(<?= $w['id'] ?>)"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                            <div class="card-body widget-preview text-muted small d-flex align-items-center justify-content-center" style="min-height:120px;">
                                <i class="bi bi-<?= match($w['type']) { 'stats'=>'speedometer2', 'chart'=>'bar-chart', 'table'=>'table', 'list'=>'list-ul', 'alert'=>'bell', 'kpi'=>'speedometer', default=>'square' } ?> me-2"></i>
                                <?= ucfirst(e($w['type'])) ?> Widget
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-sliders me-1"></i>Layout Settings</h6></div>
            <div class="card-body">
                <form method="POST" action="/dashboard/widgets">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Default Refresh Interval</label>
                            <select name="refresh_interval" class="form-select form-select-sm">
                                <option value="0" <?= ($settings['refresh_interval'] ?? '0') == '0' ? 'selected' : '' ?>>No auto-refresh</option>
                                <option value="30" <?= ($settings['refresh_interval'] ?? '') == '30' ? 'selected' : '' ?>>30 seconds</option>
                                <option value="60" <?= ($settings['refresh_interval'] ?? '') == '60' ? 'selected' : '' ?>>1 minute</option>
                                <option value="300" <?= ($settings['refresh_interval'] ?? '') == '300' ? 'selected' : '' ?>>5 minutes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Default Date Range</label>
                            <select name="date_range" class="form-select form-select-sm">
                                <option value="today" <?= ($settings['date_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= ($settings['date_range'] ?? '') === 'week' ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= ($settings['date_range'] ?? 'month') === 'month' ? 'selected' : '' ?>>This Month</option>
                                <option value="quarter" <?= ($settings['date_range'] ?? '') === 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Theme</label>
                            <select name="theme" class="form-select form-select-sm">
                                <option value="light" <?= ($settings['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                <option value="auto" <?= ($settings['theme'] ?? '') === 'auto' ? 'selected' : '' ?>>Auto (System)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let widgetCounter = <?= count($widgets) ?>;

function addWidget() {
    const type = document.getElementById('widgetType').value;
    const title = document.getElementById('widgetTitle').value.trim() || ucfirst(type) + ' Widget';
    const size = document.getElementById('widgetSize').value;
    const container = document.getElementById('widgetContainer');

    if (container.querySelector('.col-12 > .text-center')) {
        container.innerHTML = '';
    }

    const col = document.createElement('div');
    col.className = size + ' widget-item';
    col.dataset.type = type;
    col.innerHTML = `
        <div class="card h-100 border-primary border-opacity-25">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-bold small widget-title" contenteditable="true">${e_html(title)}</span>
                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="this.closest('.widget-item').remove()"><i class="bi bi-x"></i></button>
            </div>
            <div class="card-body widget-preview text-muted small d-flex align-items-center justify-content-center" style="min-height:120px;">
                <i class="bi bi-${iconForType(type)} me-2"></i>${ucfirst(type)} Widget
            </div>
        </div>`;
    container.appendChild(col);
    widgetCounter++;
    document.getElementById('widgetTitle').value = '';
}

function iconForType(type) {
    const map = {stats:'speedometer2', chart:'bar-chart', table:'table', list:'list-ul', alert:'bell', kpi:'speedometer'};
    return map[type] || 'square';
}

function ucfirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

function saveLayout() {
    const items = [];
    document.querySelectorAll('#widgetContainer .widget-item').forEach(el => {
        items.push({
            type: el.dataset.type,
            title: el.querySelector('.widget-title').textContent.trim(),
            size: el.className.split(' ').find(c => c.startsWith('col-')) || 'col-md-6',
            sort_order: items.length
        });
    });
    fetch('/dashboard/widgets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: JSON.stringify({ widgets: items })
    })
    .then(r => r.json())
    .then(d => alert(d.success ? 'Layout saved!' : 'Error: ' + d.message))
    .catch(() => alert('Failed to save layout.'));
}

function resetLayout() {
    if (!confirm('Reset dashboard layout to default?')) return;
    fetch('/dashboard/widgets/reset', { method: 'POST', headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' } })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); })
        .catch(() => alert('Failed to reset layout.'));
}

function removeWidget(id) {
    if (!confirm('Remove this widget?')) return;
    fetch('/dashboard/widgets/' + id + '/remove', { method: 'POST', headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' } })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); })
        .catch(() => alert('Failed to remove widget.'));
}

function e_html(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
