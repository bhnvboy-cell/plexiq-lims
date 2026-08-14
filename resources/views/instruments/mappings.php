<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-diagram-3 me-2"></i>Column Mapping &mdash; <?= e($instrument['instrument_name']) ?></h4>
        <span class="text-muted small"><?= e($instrument['instrument_code']) ?> &middot; Interface: <?= e($instrument['interface_type']) ?></span>
    </div>
    <a href="/instruments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-plus-lg me-1"></i>Add Mapping</div>
            <div class="card-body">
                <form method="POST" action="/instruments/<?= $instrument['id'] ?>/mappings">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Source Column <span class="text-danger">*</span></label>
                        <input type="text" name="source_column" class="form-control" placeholder="e.g. pH" required>
                        <div class="form-text">Header in the instrument file (case-insensitive).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Analysis Parameter <span class="text-danger">*</span></label>
                        <select name="parameter_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($parameters as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['parameter_code']) ?> &mdash; <?= e($p['parameter_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Conversion Factor</label>
                            <input type="number" step="any" name="conversion_factor" class="form-control" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unit (optional)</label>
                            <input type="text" name="unit" class="form-control" placeholder="mg/L">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Add Mapping</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-list me-1"></i>Active Mappings</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr><th>Source Column</th><th>Parameter</th><th>Factor</th><th>Unit</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mappings as $m): ?>
                        <tr>
                            <td><span class="fw-medium"><?= e($m['source_column']) ?></span></td>
                            <td><small><?= e($m['parameter_code']) ?> &mdash; <?= e($m['parameter_name']) ?></small></td>
                            <td><small><?= e($m['conversion_factor']) ?></small></td>
                            <td><small><?= e($m['unit'] ?? '-') ?></small></td>
                            <td class="text-end">
                                <form method="POST" action="/instruments/mappings/<?= $m['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Remove this mapping?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($mappings)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No mappings yet. Map a file column to an analysis parameter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
