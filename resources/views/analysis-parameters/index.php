<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-sliders2 me-2"></i>Analysis Parameters</h4>
        <span class="text-muted small"><?= number_format($pagination['total']) ?> parameter(s)</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <a href="/analysis-parameters/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Parameter</a>
    <?php endif; ?>
</div>

<form method="GET" action="/analysis-parameters" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:360px;">
        <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search code or name...">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th><th>Name</th><th>Unit</th><th>Category</th><th>Data Type</th>
                    <th>Specification</th><th>Target</th><th>In Use</th><th>Active</th><th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parameters as $p): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($p['parameter_code']) ?></span></td>
                    <td><strong><?= e($p['parameter_name']) ?></strong></td>
                    <td><?= e($p['unit'] ?? '-') ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($p['category']) ?></span></td>
                    <td><small class="text-muted"><?= e($p['data_type']) ?><?= $p['data_type'] === 'numeric' ? ' (' . (int)$p['decimal_places'] . ' dp)' : '' ?></small></td>
                    <td><small><?= e($p['specification_text'] ?? (($p['spec_min'] ?? '') . ' - ' . ($p['spec_max'] ?? ''))) ?></small></td>
                    <td><small><?= $p['spec_target'] !== null ? e($p['spec_target']) : '-' ?></small></td>
                    <td><small><?= $p['sample_count'] ?> sample(s), <?= $p['mapping_count'] ?> map(s)</small></td>
                    <td><?= $p['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end">
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <a href="/analysis-parameters/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="/analysis-parameters/<?= $p['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this parameter? Assignments will be removed.');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($parameters)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No parameters found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $paginator = $pagination; include __DIR__ . '/../partials/pagination.php'; ?>
