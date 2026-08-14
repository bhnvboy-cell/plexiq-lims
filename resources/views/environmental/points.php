<?php $title = 'Monitoring Points'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-geo-alt me-2"></i>Monitoring Points</h4>
    <a href="/environmental/points/create" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> New Point</a>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Points</h6>
        <span class="badge bg-secondary"><?= count($points ?? []) ?> points</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Point</th>
                    <th>Location</th>
                    <th>Monitoring Type</th>
                    <th>Unit</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($points)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No monitoring points configured.</td></tr>
                <?php else: foreach ($points as $p): ?>
                <tr>
                    <td class="fw-bold"><?= e($p['point_name']) ?></td>
                    <td><?= e($p['location_name'] ?? '—') ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($p['monitoring_type'] ?? '—') ?></span></td>
                    <td><?= e($p['unit'] ?? '—') ?></td>
                    <td><?= e($p['min_threshold'] ?? '—') ?></td>
                    <td><?= e($p['max_threshold'] ?? '—') ?></td>
                    <td><?= !empty($p['is_active']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td><a href="/environmental/points/<?= $p['id'] ?>/readings" class="btn btn-sm btn-outline-primary"><i class="bi bi-clock-history"></i> Readings</a></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
