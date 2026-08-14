<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $parameter ? 'pencil' : 'clipboard2-plus' ?> me-2"></i><?= $parameter ? 'Edit Parameter' : 'New Parameter' ?></h4>
    <a href="/analysis-parameters" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/analysis-parameters<?= $parameter ? '/' . $parameter['id'] : '' ?>">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Parameter Code <span class="text-danger">*</span></label>
                    <input type="text" name="parameter_code" class="form-control" value="<?= e($parameter['parameter_code'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Parameter Name <span class="text-danger">*</span></label>
                    <input type="text" name="parameter_name" class="form-control" value="<?= e($parameter['parameter_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" value="<?= e($parameter['unit'] ?? '') ?>" placeholder="mg/L">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= e($parameter['category'] ?? 'General') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Type</label>
                    <select name="data_type" class="form-select">
                        <?php foreach (['numeric', 'text', 'boolean'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($parameter['data_type'] ?? 'numeric') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Decimal Places</label>
                    <input type="number" min="0" max="6" name="decimal_places" class="form-control" value="<?= (int)($parameter['decimal_places'] ?? 2) ?>">
                </div>
                <div class="col-md-7">
                    <label class="form-label">Specification Text</label>
                    <input type="text" name="specification_text" class="form-control" value="<?= e($parameter['specification_text'] ?? '') ?>" placeholder="e.g. 98.0% - 102.0% of label claim">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Spec Min</label>
                    <input type="number" step="any" name="spec_min" class="form-control" value="<?= e($parameter['spec_min'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Spec Max</label>
                    <input type="number" step="any" name="spec_max" class="form-control" value="<?= e($parameter['spec_max'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Spec Target</label>
                    <input type="number" step="any" name="spec_target" class="form-control" value="<?= e($parameter['spec_target'] ?? '') ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Method / Reference</label>
                    <input type="text" name="method" class="form-control" value="<?= e($parameter['method'] ?? '') ?>">
                </div>
                <?php if ($parameter): ?>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ($parameter['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $parameter ? 'Update' : 'Create' ?> Parameter</button>
            </div>
        </form>
    </div>
</div>
