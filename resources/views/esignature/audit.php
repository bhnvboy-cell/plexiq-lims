<?php $title = 'E-Signature Audit Log'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-pen me-2"></i>E-Signature Audit Log</h4>
    <span class="badge bg-secondary"><?= $total ?? count($logs) ?> records</span>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach ($users ?? [] as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($filters['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?? $u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Action Type</label>
                <select name="action_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Sign" <?= ($filters['action_type'] ?? '') === 'Sign' ? 'selected' : '' ?>>Sign</option>
                    <option value="Approve" <?= ($filters['action_type'] ?? '') === 'Approve' ? 'selected' : '' ?>>Approve</option>
                    <option value="Review" <?= ($filters['action_type'] ?? '') === 'Review' ? 'selected' : '' ?>>Review</option>
                    <option value="Reject" <?= ($filters['action_type'] ?? '') === 'Reject' ? 'selected' : '' ?>>Reject</option>
                    <option value="Witness" <?= ($filters['action_type'] ?? '') === 'Witness' ? 'selected' : '' ?>>Witness</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Entity Type</label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Sample" <?= ($filters['entity_type'] ?? '') === 'Sample' ? 'selected' : '' ?>>Sample</option>
                    <option value="Batch" <?= ($filters['entity_type'] ?? '') === 'Batch' ? 'selected' : '' ?>>Batch</option>
                    <option value="Test" <?= ($filters['entity_type'] ?? '') === 'Test' ? 'selected' : '' ?>>Test</option>
                    <option value="COA" <?= ($filters['entity_type'] ?? '') === 'COA' ? 'selected' : '' ?>>COA</option>
                    <option value="OOS" <?= ($filters['entity_type'] ?? '') === 'OOS' ? 'selected' : '' ?>>OOS</option>
                    <option value="CAPA" <?= ($filters['entity_type'] ?? '') === 'CAPA' ? 'selected' : '' ?>>CAPA</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-md-1">
                <a href="/esign/audit" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action Type</th>
                    <th>Entity Type</th>
                    <th>Entity ID</th>
                    <th>Signature Hash</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No e-signature records found.</td></tr>
                <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                    <td><?= e($l['full_name'] ?? $l['username'] ?? '—') ?></td>
                    <td>
                        <?php $actionBadges = ['Sign'=>'primary','Approve'=>'success','Review'=>'info','Reject'=>'danger','Witness'=>'warning']; ?>
                        <span class="badge bg-<?= $actionBadges[$l['action_type']] ?? 'secondary' ?>"><?= e($l['action_type']) ?></span>
                    </td>
                    <td><?= e($l['entity_type'] ?? '—') ?></td>
                    <td><?= e($l['entity_id'] ?? '—') ?></td>
                    <td><code class="small"><?= e(substr($l['signature_hash'], 0, 16)) ?>...</code></td>
                    <td><small class="text-muted"><?= e($l['comment'] ?? '—') ?></small></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
