<?php $title = 'Privacy Access Logs'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-eye-slash me-2"></i>Privacy Access Logs</h4>
    <a href="/compliance" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Events</h6>
        <span class="badge bg-secondary"><?= count($logs ?? []) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No privacy access events logged.</td></tr>
                <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                    <td><?= e($l['user_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge bg-<?= match($l['action_type']) { 'Access'=>'info', 'Export'=>'warning', 'Delete'=>'danger', 'Rectify'=>'primary', default=>'secondary' } ?>">
                            <?= e($l['action_type'] ?? '—') ?>
                        </span>
                    </td>
                    <td><?= e($l['description'] ?? '—') ?></td>
                    <td><small class="text-muted"><?= e($l['ip_address'] ?? '—') ?></small></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
