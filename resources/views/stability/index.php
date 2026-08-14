<?php $title = 'Stability Studies'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-flask me-2"></i>Stability Studies</h4>
    <a href="/stability/create" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> New Study</a>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Studies</h6>
        <span class="badge bg-secondary"><?= count($studies ?? []) ?> studies</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Study Code</th>
                    <th>Study Name</th>
                    <th>Product</th>
                    <th>Batch</th>
                    <th>Condition</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($studies)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No stability studies found.</td></tr>
                <?php else: foreach ($studies as $s): ?>
                <?php
                $statusBadge = match ($s['status']) {
                    'Active' => 'success',
                    'Completed' => 'info',
                    'Closed' => 'secondary',
                    'Terminated' => 'danger',
                    'On Hold' => 'warning',
                    default => 'secondary',
                };
                $condition = '';
                if (!empty($s['storage_condition'])) {
                    $condition = $s['storage_condition'];
                } else {
                    $parts = [];
                    if (($s['condition_temperature'] ?? '') !== '') { $parts[] = $s['condition_temperature'] . '°C'; }
                    if (($s['condition_humidity'] ?? '') !== '') { $parts[] = $s['condition_humidity'] . '% RH'; }
                    if (!empty($s['condition_light'])) { $parts[] = $s['condition_light']; }
                    $condition = implode(' / ', $parts);
                }
                ?>
                <tr>
                    <td class="fw-bold"><?= e($s['study_code']) ?></td>
                    <td><?= e($s['study_name'] ?? '') ?></td>
                    <td><?= e($s['product_name'] ?? '—') ?></td>
                    <td><?= e($s['batch_number'] ?? '—') ?></td>
                    <td><small><?= e($condition ?: '—') ?></small></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($s['study_type'] ?? '—') ?></span></td>
                    <td><span class="badge bg-<?= $statusBadge ?>"><?= e($s['status']) ?></span></td>
                    <td><small class="text-muted"><?= e($s['created_by_name'] ?? '—') ?></small></td>
                    <td>
                        <a href="/stability/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
