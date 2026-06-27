<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-box me-2"></i>Products</h4>
        <span class="text-muted small"><?= count($items) ?> product(s)</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <a href="/master/products/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Product</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Active</th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $p): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($p['product_code']) ?></span></td>
                    <td><strong><?= e($p['product_name']) ?></strong></td>
                    <td><?= e($p['category'] ?? '-') ?></td>
                    <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end"><a href="/master/products/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
