<?php $title = 'Data Retention Policies'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-archive me-2"></i>Data Retention Policies</h4>
    <a href="/compliance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Policies</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Entity Type</th>
                    <th>Retention Period</th>
                    <th>Action on Expiry</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($policies)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No retention policies defined.</td></tr>
                <?php else: foreach ($policies as $p): ?>
                <tr>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($p['entity_type']) ?></span></td>
                    <td class="fw-bold"><?= (int)$p['retention_days'] ?> days</td>
                    <td><?= e($p['action_on_expiry'] ?? 'Archive') ?></td>
                    <td><?= !empty($p['is_active']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></small></td>
                    <td>
                        <form method="POST" action="/compliance/retention/<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this policy?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
