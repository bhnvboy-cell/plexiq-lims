<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-rulers me-2"></i>Calibration Standards</h4>
        <span class="text-muted small"><?= count($standards) ?> standard(s)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/calibrations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/calibrations/standards/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Standard</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($standards)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-rulers"></i>
        <h5>No Standards</h5>
        <p class="text-muted">No calibration standards have been registered yet.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Serial #</th>
                    <th>Interval (days)</th>
                    <th>Last Calibration</th>
                    <th>Next Due</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($standards as $s): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($s['standard_code']) ?></span></td>
                    <td><strong><?= e($s['standard_name']) ?></strong></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($s['standard_type'] ?? '-') ?></span></td>
                    <td><?= e($s['serial_number'] ?? '-') ?></td>
                    <td><?= e($s['calibration_interval_days'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= e($s['last_calibration_date'] ?? '-') ?></small></td>
                    <td><?php
                        $next = $s['next_calibration_date'] ?? null;
                        if ($next):
                            $days = (strtotime($next) - time()) / 86400;
                            $badge = $days < 0 ? 'danger' : ($days < 30 ? 'warning' : 'success');
                        ?><span class="badge bg-<?= $badge ?>"><?= e($next) ?></span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td><?= ($s['is_active'] ?? true) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end">
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <a href="/calibrations/standards/<?= $s['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
