<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-truck me-2"></i>Suppliers</h4>
        <span class="text-muted small"><?= count($suppliers) ?> supplier(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin'])): ?>
    <a href="/suppliers/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Supplier</a>
    <?php endif; ?>
</div>

<?php if (empty($suppliers)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-truck"></i>
        <h5>No Suppliers Registered</h5>
        <p class="text-muted">Add your first supplier to the database.</p>
        <?php if (in_array($auth['role'], ['Admin'])): ?>
        <a href="/suppliers/create" class="btn btn-primary mt-2"><i class="bi bi-plus-lg me-1"></i>New Supplier</a>
        <?php endif; ?>
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
                    <th>Status</th>
                    <th>Rating</th>
                    <th>Approval</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($s['supplier_code']) ?></span></td>
                    <td><a href="/suppliers/<?= $s['id'] ?>" class="text-decoration-none"><strong><?= e($s['supplier_name']) ?></strong></a></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($s['supplier_type'] ?? '-') ?></span></td>
                    <td><?= !empty($s['is_approved']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td><?php if ($s['rating']): ?><span class="badge bg-warning bg-opacity-10 text-warning"><?= e($s['rating']) ?>/5</span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    <td><?php $abadge = match ($s['status'] ?? '') { 'Approved'=>'success', 'Pending'=>'warning', 'Rejected'=>'danger', 'Under Review'=>'info', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $abadge ?>"><?= e($s['status'] ?? 'N/A') ?></span>
                    </td>
                    <td class="text-end">
                        <a href="/suppliers/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                        <?php if (in_array($auth['role'], ['Admin'])): ?>
                        <a href="/suppliers/<?= $s['id'] ?>/edit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
