<?php $title = 'Plugin Settings: ' . e($plugin['plugin_name']); layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-gear me-2"></i>Plugin Settings</h4>
    <a href="/plugins" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Plugins</a>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i><?= e($plugin['plugin_name']) ?></h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="detail-label small text-muted">Code</div>
                        <div class="fw-bold"><?= e($plugin['plugin_code'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label small text-muted">Version</div>
                        <div class="fw-bold"><?= e($plugin['version'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label small text-muted">Author</div>
                        <div class="fw-bold"><?= e($plugin['author'] ?? '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label small text-muted">Status</div>
                        <div><?= !empty($plugin['is_active']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></div>
                    </div>
                    <?php if (!empty($plugin['description'])): ?>
                    <div class="col-12">
                        <div class="detail-label small text-muted">Description</div>
                        <div><?= e($plugin['description']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-sliders me-1"></i>Configuration</h6></div>
            <div class="card-body">
                <?php if (empty($config)): ?>
                <div class="text-center text-muted py-4">This plugin has no configuration options.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Key</th><th>Value</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($config as $key => $value): ?>
                            <tr>
                                <td><code><?= e($key) ?></code></td>
                                <td><?= is_scalar($value) ? e((string)$value) : '<code>' . e(json_encode($value)) . '</code>' ?></td>
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
