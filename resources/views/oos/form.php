<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $record ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $record ? 'Edit' : 'New' ?> OOS Record</h4>
    <a href="/oos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $record ? '/oos/' . $record['id'] . '/update' : '/oos/store' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">OOS Number <span class="text-danger">*</span></label>
                    <input name="oos_number" class="form-control" required value="<?= e($record['oos_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Test Parameter</label>
                    <input name="test_parameter" class="form-control" value="<?= e($record['test_parameter'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Specification Range</label>
                    <input name="specification_range" class="form-control" placeholder="e.g. 6.5 - 7.5" value="<?= e($record['specification_range'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Result Value</label>
                    <input name="result_value" type="number" step="any" class="form-control" value="<?= e($record['result_value'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Result Text</label>
                    <input name="result_text" class="form-control" value="<?= e($record['result_text'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input name="unit" class="form-control" value="<?= e($record['unit'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="Minor" <?= ($record['severity'] ?? '') === 'Minor' ? 'selected' : '' ?>>Minor</option>
                        <option value="Major" <?= ($record['severity'] ?? '') === 'Major' ? 'selected' : '' ?>>Major</option>
                        <option value="Critical" <?= ($record['severity'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required><?= e($record['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sample</label>
                    <select name="sample_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($samples as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($record['sample_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['sample_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($record['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $record ? 'Update' : 'Create' ?> OOS</button>
            </div>
        </form>
    </div>
</div>
