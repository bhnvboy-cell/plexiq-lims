<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-journal-text me-2"></i>Audit Trail</h4>
        <span class="text-muted small"><?= $total ?> total records</span>
    </div>
    <a href="/audit/login-history" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>Login History</a>
</div>

<div class="filter-section">
    <form method="GET" class="row g-2">
        <div class="col-md-3">
            <label class="form-label small mb-1">Action</label>
            <select name="action" class="form-select">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= ($filters['action'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Entity</label>
            <select name="entity_type" class="form-select">
                <option value="">All Entities</option>
                <?php foreach ($entities as $e): ?>
                <option value="<?= e($e) ?>" <?= ($filters['entity_type'] ?? '') === $e ? 'selected' : '' ?>><?= e($e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Entity ID</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td><small class="text-muted"><?= e($l['created_at']) ?></small></td>
                    <td><?= e($l['full_name'] ?? $l['username'] ?? 'System') ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($l['action']) ?></span></td>
                    <td><?= e($l['entity_type'] ?? '-') ?></td>
                    <td><?= e($l['entity_id'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= e($l['ip_address'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No audit records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
