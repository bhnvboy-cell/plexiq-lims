<?php $title = 'Samples'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-collection"></i> Samples</h2>
    <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
    <a href="/samples/create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Sample</a>
    <?php endif; ?>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="Registered" <?= ($filters['status'] ?? '') === 'Registered' ? 'selected' : '' ?>>Registered</option>
            <option value="In Progress" <?= ($filters['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="Reviewed" <?= ($filters['status'] ?? '') === 'Reviewed' ? 'selected' : '' ?>>Reviewed</option>
            <option value="Approved" <?= ($filters['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="COA Released" <?= ($filters['status'] ?? '') === 'COA Released' ? 'selected' : '' ?>>COA Released</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="priority" class="form-select">
            <option value="">All Priority</option>
            <option value="Low" <?= ($filters['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
            <option value="Normal" <?= ($filters['priority'] ?? '') === 'Normal' ? 'selected' : '' ?>>Normal</option>
            <option value="High" <?= ($filters['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
            <option value="Urgent" <?= ($filters['priority'] ?? '') === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
        </select>
    </div>
    <div class="col-md-3">
        <input type="text" name="search" class="form-control" placeholder="Search sample code, batch, customer..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Sample Code</th><th>Customer</th><th>Product</th><th>Batch</th><th>Priority</th><th>Status</th><th>Analyst</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($items as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['sample_code']) ?></strong></td>
                    <td><?= htmlspecialchars($s['customer_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($s['product_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($s['batch_number'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= ['Urgent'=>'danger','High'=>'warning','Normal'=>'primary','Low'=>'secondary'][$s['priority']] ?? 'secondary' ?>"><?= $s['priority'] ?></span></td>
                    <td><span class="badge bg-<?= ['Registered'=>'secondary','In Progress'=>'info','Reviewed'=>'primary','Approved'=>'success','COA Released'=>'success','Rejected'=>'danger'][$s['status']] ?? 'secondary' ?>"><?= $s['status'] ?></span></td>
                    <td><?= htmlspecialchars($s['analyst_name'] ?? 'Unassigned') ?></td>
                    <td><?= date('Y-m-d', strtotime($s['created_at'])) ?></td>
                    <td><a href="/samples/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No samples found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($lastPage > 1): ?>
<nav class="mt-3"><ul class="pagination justify-content-center">
    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $currentPage - 1 ?>">Previous</a></li>
    <?php for ($i = 1; $i <= $lastPage; $i++): ?>
    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $currentPage + 1 ?>">Next</a></li>
</ul></nav>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
