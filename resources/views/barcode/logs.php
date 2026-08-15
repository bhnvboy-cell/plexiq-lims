<?php $title = 'Barcode Scan Logs'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clock-history me-2"></i>Barcode Scan Logs</h4>
    <a href="/barcode" class="btn btn-outline-primary btn-sm"><i class="bi bi-upc-scan"></i> Scanner</a>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Barcode</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search barcode...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Entity Type</label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Sample" <?= ($filters['entity_type'] ?? '') === 'Sample' ? 'selected' : '' ?>>Sample</option>
                    <option value="Batch" <?= ($filters['entity_type'] ?? '') === 'Batch' ? 'selected' : '' ?>>Batch</option>
                    <option value="Instrument" <?= ($filters['entity_type'] ?? '') === 'Instrument' ? 'selected' : '' ?>>Instrument</option>
                    <option value="Chemical" <?= ($filters['entity_type'] ?? '') === 'Chemical' ? 'selected' : '' ?>>Chemical</option>
                    <option value="Location" <?= ($filters['entity_type'] ?? '') === 'Location' ? 'selected' : '' ?>>Location</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-1">
                <a href="/barcode/logs" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Barcode</th>
                    <th>Entity Type</th>
                    <th>Entity ID</th>
                    <th>Location</th>
                    <th>Scanned By</th>
                    <th>Scanned At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No scan logs found.</td></tr>
                <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><code><?= e($l['barcode_value']) ?></code></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($l['entity_type'] ?? '—') ?></span></td>
                    <td><?= e($l['entity_id'] ?? '—') ?></td>
                    <td><?= e($l['location'] ?? '—') ?></td>
                    <td><?= e($l['scanned_by_name'] ?? '—') ?></td>
                    <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($l['scanned_at'])) ?></small></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
