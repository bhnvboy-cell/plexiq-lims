<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-clipboard-check me-2"></i>Test Master</h4>
        <span class="text-muted small"><?= count($tests) ?> test(s)</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <a href="/master/tests/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Test</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Method</th><th>Unit</th><th>Specification</th><th>Active</th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($t['test_code']) ?></span></td>
                    <td><strong><?= e($t['test_name']) ?></strong></td>
                    <td><?= e($t['method_name'] ?? '-') ?></td>
                    <td><?= e($t['unit_code'] ?? '-') ?></td>
                    <td><small><?= e($t['spec_limit_text'] ?? ($t['min_spec_limit'] . ' - ' . $t['max_spec_limit'])) ?></small></td>
                    <td><?= $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end"><a href="/master/tests/<?= $t['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
