<?php $title = 'Saved Filters'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-funnel me-2"></i>Saved Filters</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#saveFilterModal"><i class="bi bi-plus-lg"></i> Save Current Filter</button>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-list me-1"></i>Your Saved Filters</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Filter Name</th>
                    <th>Entity Type</th>
                    <th>Criteria</th>
                    <th>Created</th>
                    <th>Shared</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filters)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No saved filters. Use the filter bar on any list page and save your filter for quick access.</td></tr>
                <?php else: foreach ($filters as $f): ?>
                <tr>
                    <td class="fw-bold"><i class="bi bi-funnel me-1 text-primary"></i><?= e($f['filter_name']) ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($f['entity_type']) ?></span></td>
                    <td><small class="text-muted"><code><?= e(mb_substr(json_encode($f['criteria'] ?? []), 0, 60)) ?>...</code></small></td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($f['created_at'])) ?></small></td>
                    <td>
                        <?php if ($f['is_shared']): ?><span class="badge bg-success">Shared</span>
                        <?php else: ?><span class="badge bg-secondary">Private</span><?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= e($f['apply_url'] ?? '#') ?>" class="btn btn-outline-primary" title="Apply"><i class="bi bi-check-lg"></i> Apply</a>
                            <?php if ($f['user_id'] == ($auth['user']['id'] ?? 0) || $auth['role'] === 'Admin'): ?>
                            <form method="POST" action="/dashboard/filters/<?= $f['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this saved filter?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Save Filter Modal -->
<div class="modal fade" id="saveFilterModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/dashboard/filters">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-bookmark-plus me-1"></i>Save Current Filter</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Filter Name <span class="text-danger">*</span></label>
            <input type="text" name="filter_name" class="form-control" required placeholder="e.g. High Priority OOS">
        </div>
        <div class="mb-3">
            <label class="form-label">Entity Type <span class="text-danger">*</span></label>
            <select name="entity_type" class="form-select" required>
                <option value="">— Select —</option>
                <option value="Sample">Samples</option>
                <option value="Batch">Batches</option>
                <option value="Test">Tests</option>
                <option value="OOS">OOS</option>
                <option value="CAPA">CAPA</option>
                <option value="Project">Projects</option>
                <option value="Notebook">ELN Notebooks</option>
                <option value="Stability">Stability Studies</option>
            </select>
        </div>
        <div class="alert alert-info py-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>The current URL query parameters will be saved as filter criteria.
        </div>
    </div>
    <div class="modal-footer">
        <div class="form-check me-auto">
            <input class="form-check-input" type="checkbox" name="is_shared" value="1">
            <label class="form-check-label">Share with all users</label>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-bookmark"></i> Save Filter</button>
    </div>
</form>
</div></div></div>
