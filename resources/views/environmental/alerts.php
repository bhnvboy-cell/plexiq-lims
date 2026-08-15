<?php $title = 'Environmental Alerts'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Environmental Alerts</h4>
    <a href="/environmental" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Alerts</h6>
        <span class="badge bg-secondary"><?= count($alerts ?? []) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>Point</th>
                    <th>Type</th>
                    <th>Reading</th>
                    <th>Threshold</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alerts)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No alerts recorded.</td></tr>
                <?php else: foreach ($alerts as $a): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></small></td>
                    <td class="fw-bold"><?= e($a['point_name'] ?? '—') ?><br><small class="text-muted"><?= e($a['location_name'] ?? '') ?></small></td>
                    <td><span class="badge bg-<?= $a['alert_type'] === 'max_violation' ? 'danger' : 'warning' ?>"><?= e($a['alert_type']) ?></span></td>
                    <td class="fw-bold text-danger"><?= e($a['reading_value'] ?? '—') ?></td>
                    <td><code><?= e($a['threshold_value'] ?? '—') ?></code></td>
                    <td><?= e($a['message'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($a['is_resolved'])): ?>
                        <span class="badge bg-success">Resolved</span>
                        <?php else: ?>
                        <span class="badge bg-danger">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($a['is_resolved'])): ?>
                        <form method="POST" action="/environmental/alerts/<?= $a['id'] ?>/acknowledge" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i> Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
