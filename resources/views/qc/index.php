<?php $title = 'Quality Control'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-shield-check me-2"></i>Quality Control
        <small class="text-muted fs-6 ms-2">Control lots · Levey-Jennings · Westgard rules</small>
    </h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createLotModal">
        <i class="bi bi-plus-lg"></i> New Control Lot
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-box-seam stat-icon"></i>
            <div class="stat-value"><?= $stats['active_lots'] ?? 0 ?></div>
            <div class="stat-label">Active Control Lots</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card stats-card-green">
            <i class="bi bi-list-check stat-icon"></i>
            <div class="stat-value"><?= $stats['total_results'] ?? 0 ?></div>
            <div class="stat-label">Total QC Results</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-value"><?= $stats['expiring_soon'] ?? 0 ?></div>
            <div class="stat-label">Lots Expiring ≤ 90 Days</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-1"></i>Control Lots</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Lot Number</th>
                    <th>Description</th>
                    <th>Manufacturer</th>
                    <th>Material</th>
                    <th>Target Mean ± SD</th>
                    <th>Unit</th>
                    <th>Expiry</th>
                    <th>Results</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lots as $lot): ?>
                <tr>
                    <td><a href="/qc/<?= $lot['id'] ?>" class="fw-bold text-decoration-none"><?= e($lot['lot_number']) ?></a></td>
                    <td><?= e($lot['description'] ?? '-') ?></td>
                    <td><?= e($lot['manufacturer'] ?? '-') ?></td>
                    <td><?= e($lot['material_type'] ?? '-') ?></td>
                    <td>
                        <?= $lot['target_mean'] !== null ? e($lot['target_mean']) : '-' ?>
                        <?= $lot['target_sd'] !== null ? '± ' . e($lot['target_sd']) : '' ?>
                    </td>
                    <td><?= e($lot['unit'] ?? '-') ?></td>
                    <td>
                        <?php if ($lot['expiry_date']): ?>
                            <span class="<?= strtotime($lot['expiry_date']) < time() ? 'text-danger' : '' ?>">
                                <?= date('Y-m-d', strtotime($lot['expiry_date'])) ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= $lot['reading_count'] ?></td>
                    <td>
                        <?php if (!$lot['is_active']): ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php elseif ($lot['expiry_date'] && strtotime($lot['expiry_date']) < time()): ?>
                            <span class="badge bg-danger">Expired</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/qc/<?= $lot['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i></a>
                        <form method="POST" action="/qc/<?= $lot['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this control lot and all its results?');">
                            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lots)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No QC control lots yet. Create one to start daily control monitoring.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createLotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/qc/create">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">New Control Lot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Lot Number *</label>
                        <input type="text" name="lot_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Material Type</label>
                        <input type="text" name="material_type" class="form-control" placeholder="e.g. Certified Reference Material">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Mean</label>
                            <input type="number" step="any" name="target_mean" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target SD</label>
                            <input type="number" step="any" name="target_sd" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" placeholder="e.g. mg/mL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="datetime-local" name="expiry_date" class="form-control">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="lotActive" checked>
                        <label class="form-check-label" for="lotActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
