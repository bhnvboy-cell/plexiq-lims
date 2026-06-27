<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $record ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $record ? 'Edit Calibration Record' : 'New Calibration Record' ?></h4>
    <a href="/calibrations/records/" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $record ? '/calibrations/records/' . $record['id'] : '/calibrations/records' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Instrument <span class="text-danger">*</span></label>
                    <select name="instrument_id" class="form-select" required>
                        <option value="">-- Select Instrument --</option>
                        <?php foreach ($instruments as $inst): ?>
                        <option value="<?= $inst['id'] ?>" <?= ($record['instrument_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>><?= e($inst['instrument_name']) ?> (<?= e($inst['instrument_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard</label>
                    <select name="standard_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($standards as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= ($record['standard_id'] ?? '') == $st['id'] ? 'selected' : '' ?>><?= e($st['standard_code']) ?> - <?= e($st['standard_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calibration Date <span class="text-danger">*</span></label>
                    <input name="calibration_date" type="date" class="form-control" required value="<?= $record['calibration_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Calibration Type</label>
                    <select name="calibration_type" class="form-select">
                        <option value="Internal" <?= ($record['calibration_type'] ?? '') === 'Internal' ? 'selected' : '' ?>>Internal</option>
                        <option value="External" <?= ($record['calibration_type'] ?? '') === 'External' ? 'selected' : '' ?>>External</option>
                        <option value="Factory" <?= ($record['calibration_type'] ?? '') === 'Factory' ? 'selected' : '' ?>>Factory</option>
                        <option value="Verification" <?= ($record['calibration_type'] ?? '') === 'Verification' ? 'selected' : '' ?>>Verification</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Calibrated By</label>
                    <input name="calibrated_by" class="form-control" value="<?= e($record['calibrated_by'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Result</label>
                    <select name="result" class="form-select">
                        <option value="Pass" <?= ($record['result']??'')==='Pass'?'selected':'' ?>>Pass</option>
                        <option value="Fail" <?= ($record['result']??'')==='Fail'?'selected':'' ?>>Fail</option>
                        <option value="Conditional" <?= ($record['result']??'')==='Conditional'?'selected':'' ?>>Conditional</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">As-Found Value</label>
                    <input name="as_found_value" class="form-control" value="<?= e($record['as_found_value'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">As-Left Value</label>
                    <input name="as_left_value" class="form-control" value="<?= e($record['as_left_value'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Certificate Number</label>
                    <input name="certificate_number" class="form-control" value="<?= e($record['certificate_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($record['due_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($record['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $record ? 'Update' : 'Create' ?> Record</button>
            </div>
        </form>
    </div>
</div>
