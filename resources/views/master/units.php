<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-rulers me-2"></i>Units of Measure</h4>
        <span class="text-muted small"><?= count($items) ?> unit(s)</span>
    </div>
    <form method="POST" action="/master/units" class="d-inline-flex gap-2 align-items-end">
        <?= csrf_field() ?>
        <div><label class="form-label small mb-1">Code</label><input name="unit_code" class="form-control form-control-sm" required></div>
        <div><label class="form-label small mb-1">Name</label><input name="unit_name" class="form-control form-control-sm" required></div>
        <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i></button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Code</th><th>Name</th></tr></thead>
            <tbody>
                <?php foreach ($items as $u): ?>
                <tr><td><span class="fw-medium"><?= e($u['unit_code']) ?></span></td><td><strong><?= e($u['unit_name']) ?></strong></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
