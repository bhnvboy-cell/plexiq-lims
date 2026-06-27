<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-building me-2"></i>Customers</h4>
        <span class="text-muted small"><?= count($items) ?> customer(s)</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <a href="/master/customers/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Customer</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Country</th><th>Contact</th><th>Email</th><th>Active</th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $c): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($c['customer_code']) ?></span></td>
                    <td><strong><?= e($c['customer_name']) ?></strong></td>
                    <td><?= e($c['city'] ?? '-') ?></td>
                    <td><?= e($c['country'] ?? '-') ?></td>
                    <td><?= e($c['contact_person'] ?? '-') ?></td>
                    <td><?= e($c['email'] ?? '-') ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end"><a href="/master/customers/<?= $c['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
