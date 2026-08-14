<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-exclamation-octagon me-2"></i>Deviations</h4>
        <span class="text-muted small"><?= count($deviations) ?> record(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
    <a href="/deviations/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Deviation</a>
    <?php endif; ?>
</div>

<?php if (empty($deviations)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-exclamation-octagon"></i>
        <h5>No Deviations</h5>
        <p class="text-muted">No deviation records found.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Deviation #</th>
                    <th>Title</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Reported By</th>
                    <th>Date</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deviations as $d): ?>
                <tr>
                    <td><a href="/deviations/<?= $d['id'] ?>" class="fw-medium text-decoration-none"><?= e($d['deviation_number']) ?></a></td>
                    <td><?= e(substr($d['title'], 0, 50)) . (strlen($d['title']) > 50 ? '…' : '') ?></td>
                    <td><?php $sebadge = match ($d['severity']) { 'Critical'=>'danger', 'Major'=>'warning', 'Minor'=>'info', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $sebadge ?> bg-opacity-10 text-<?= $sebadge ?>"><?= e($d['severity']) ?></span>
                    </td>
                    <td><?php $stbadge = match ($d['status']) { 'Open'=>'danger', 'Under Investigation'=>'warning', 'Under Review'=>'info', 'Closed'=>'success', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $stbadge ?>"><?= e($d['status']) ?></span>
                    </td>
                    <td><?= e($d['reporter_name'] ?? 'N/A') ?></td>
                    <td><small class="text-muted"><?= e(date('Y-m-d', strtotime($d['created_at']))) ?></small></td>
                    <td class="text-end"><a href="/deviations/<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
