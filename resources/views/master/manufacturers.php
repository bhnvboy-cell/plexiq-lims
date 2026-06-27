<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-building-gear me-2"></i>Manufacturers</h4>
        <span class="text-muted small"><?= count($items) ?> manufacturer(s)</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <a href="/master/manufacturers/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Manufacturer</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Company</th><th>City</th><th>Country</th><th>Phone</th><th>Email</th><th>Logo</th><th>Active</th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $m): ?>
                <tr>
                    <td><strong><?= e($m['company_name']) ?></strong></td>
                    <td><?= e($m['city'] ?? '-') ?></td>
                    <td><?= e($m['country'] ?? '-') ?></td>
                    <td><?= e($m['phone'] ?? '-') ?></td>
                    <td><?= e($m['email'] ?? '-') ?></td>
                    <td><?= $m['logo_path'] ? '<span class="badge bg-info">Uploaded</span>' : '<span class="text-muted">None</span>' ?></td>
                    <td><?= $m['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end">
                        <a href="/master/manufacturers/<?= $m['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
