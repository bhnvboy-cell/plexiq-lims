<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $test ? 'pencil' : 'clipboard2-plus' ?> me-2"></i><?= $test ? 'Edit Test' : 'New Test' ?></h4>
    <a href="/master/tests" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/master/tests<?= $test ? '/' . $test['id'] : '' ?>">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Test Code <span class="text-danger">*</span></label>
                    <input type="text" name="test_code" class="form-control" value="<?= e($test['test_code'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Test Name <span class="text-danger">*</span></label>
                    <input type="text" name="test_name" class="form-control" value="<?= e($test['test_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Method</label>
                    <select name="method_id" class="form-select">
                        <option value="">Select Method</option>
                        <?php foreach ($methods as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($test['method_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e($m['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Select Unit</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($test['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['unit_name']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Spec Limit Text</label>
                    <input type="text" name="spec_limit_text" class="form-control" value="<?= e($test['spec_limit_text'] ?? '') ?>" placeholder="e.g. 98.0% - 102.0%">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Min Spec Limit</label>
                    <input type="number" step="any" name="min_spec_limit" class="form-control" value="<?= e($test['min_spec_limit'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Max Spec Limit</label>
                    <input type="number" step="any" name="max_spec_limit" class="form-control" value="<?= e($test['max_spec_limit'] ?? '') ?>">
                </div>
                <?php if ($test): ?>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ($test['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $test ? 'Update' : 'Create' ?> Test</button>
            </div>
        </form>
    </div>
</div>
