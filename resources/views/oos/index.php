<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Out of Specification</h4>
        <span class="text-muted small"><?= count($records) ?> record(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
    <a href="/oos/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New OOS Record</a>
    <?php endif; ?>
</div>

<?php if (empty($records)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-check-circle text-success"></i>
        <h5>No OOS Records</h5>
        <p class="text-muted">All clear! No out-of-specification results recorded.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>OOS #</th>
                    <th>Parameter</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Initiator</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/oos/<?= $r['id'] ?>" class="fw-medium text-decoration-none"><?= e($r['oos_number']) ?></a></td>
                    <td><?= e($r['test_parameter'] ?: '-') ?></td>
                    <td><?php $sbadge = match ($r['severity']) { 'Critical'=>'danger', 'Major'=>'warning', 'Minor'=>'info', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $sbadge ?> bg-opacity-10 text-<?= $sbadge ?>"><?= e($r['severity']) ?></span></td>
                    <td><?php $stbadge = match ($r['status']) { 'Open'=>'danger', 'Under Investigation'=>'warning', 'Closed'=>'success', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $stbadge ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e($r['initiator_name'] ?: 'N/A') ?></td>
                    <td><?= e($r['assigned_name'] ?: 'Unassigned') ?></td>
                    <td><small class="text-muted"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></small></td>
                    <td class="text-end"><a href="/oos/<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
