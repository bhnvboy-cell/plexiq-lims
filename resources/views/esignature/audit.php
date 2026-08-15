<?php $title = 'E-Signature Audit Log'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-pen me-2"></i>E-Signature Audit Log</h4>
    <span class="badge bg-secondary"><?= $total ?? count($signatures ?? []) ?> records</span>
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
                    <?php foreach (['approval' => 'Approval', 'review' => 'Review', 'reject' => 'Reject', 'witness' => 'Witness', 'release' => 'Release'] as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($filters['action_type'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Entity Type</label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['sample_test', 'coa', 'deviation', 'capa', 'oos', 'batch_record'] as $code): ?>
                    <option value="<?= $code ?>" <?= ($filters['entity_type'] ?? '') === $code ? 'selected' : '' ?>><?= e($code) ?></option>
                    <?php endforeach; ?>
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
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($signatures)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No e-signature records found.</td></tr>
                <?php else: foreach ($signatures as $l): ?>
                <?php
                    $meta = json_decode($l['signed_data'] ?? '{}', true) ?: [];
                    $sigStatus = '';
                    if (function_exists('hash_equals')) {
                        $sd = $meta['signed_data'] ?? [];
                        $ch = hash('sha256', json_encode($sd));
                        $re = hash('sha256', json_encode([
                            'entity_type' => $l['entity_type'], 'entity_id' => $l['entity_id'],
                            'user_id' => $l['user_id'], 'action_type' => $l['action_type'],
                            'reason' => $meta['reason'] ?? '', 'ip_address' => $l['ip_address'],
                            'user_agent' => $meta['user_agent'] ?? '', 'content_hash' => $ch,
                            'signed_at' => $meta['signed_at'] ?? '',
                        ]));
                        $sigStatus = hash_equals($l['signature_hash'] ?? '', $re) ? 'ok' : 'tampered';
                    }
                ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                    <td><?= e($l['full_name'] ?? $l['username'] ?? '—') ?></td>
                    <td>
                        <?php $actionBadges = ['approval'=>'success','review'=>'info','reject'=>'danger','witness'=>'warning','release'=>'primary']; ?>
                        <span class="badge bg-<?= $actionBadges[$l['action_type']] ?? 'secondary' ?>"><?= e($l['action_type']) ?></span>
                    </td>
                    <td><?= e($l['entity_type'] ?? '—') ?></td>
                    <td><?= e($l['entity_id'] ?? '—') ?></td>
                    <td><code class="small"><?= e(substr($l['signature_hash'] ?? '', 0, 16)) ?>...</code></td>
                    <td><small class="text-muted"><?= e($meta['reason'] ?? '—') ?></small></td>
                    <td>
                        <?php if ($sigStatus === 'ok'): ?>
                        <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Valid</span>
                        <?php elseif ($sigStatus === 'tampered'): ?>
                        <span class="badge bg-danger"><i class="bi bi-shield-x me-1"></i>Tampered</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Unknown</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($lastPage ?? 1) > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Page <?= $currentPage ?? 1 ?> of <?= $lastPage ?? 1 ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($currentPage ?? 1) <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters ?? [], ['page' => ($currentPage ?? 1) - 1])) ?>">Prev</a>
                </li>
                <li class="page-item <?= ($currentPage ?? 1) >= ($lastPage ?? 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($filters ?? [], ['page' => ($currentPage ?? 1) + 1])) ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
