<?php $title = 'Batch Management'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-boxes me-2"></i>Batch Management</h4>
    <a href="/batches/create" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> New Batch</a>
</div>
<?php if (empty($batches)): ?>
<div class="alert alert-info">No batches registered yet. <a href="/batches/create" class="alert-link">Create your first batch</a>.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="table-light"><tr>
    <th>Batch #</th><th>Product</th><th>Category</th><th>Size</th><th>Mfg Date</th><th>Samples</th><th>Status</th><th>Created</th><th>By</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($batches as $b): ?>
<tr>
    <td><a href="/batches/<?= $b['id'] ?>" class="fw-bold text-decoration-none"><?= e($b['batch_number']) ?></a></td>
    <td><?= e($b['product_name'] ?? '—') ?></td>
    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($b['category'] ?? '—') ?></span></td>
    <td><?= e($b['batch_size'] ?? '—') ?></td>
    <td><?= $b['manufacture_date'] ? date('d M Y', strtotime($b['manufacture_date'])) : '—' ?></td>
    <td><?= $b['sample_count'] ?? 0 ?></td>
    <td><?= status_badge($b['status']) ?></td>
    <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
    <td><?= e($b['created_by_name'] ?? '—') ?></td>
    <td><a href="/batches/<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
