<?php $title = ($calibration ? 'Edit' : 'New') . ' Calibration'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-calendar-check me-2"></i><?= $calibration ? 'Edit Calibration' : 'New Calibration Record' ?></h4>
    <a href="/master/calibrations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="card"><div class="card-body">
    <form method="POST">
        <?= csrf_field() ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Instrument *</label>
                <select name="instrument_id" class="form-select" required>
                    <option value="">Select Instrument</option>
                    <?php foreach ($instruments as $inst): ?>
                    <option value="<?= $inst['id'] ?>" <?= ($calibration['instrument_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>><?= e($inst['instrument_name']) ?> (<?= e($inst['instrument_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Calibration Date *</label>
                <input type="date" name="calibration_date" class="form-control" value="<?= $calibration['calibration_date'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Next Due Date</label>
                <input type="date" name="next_calibration_date" class="form-control" value="<?= $calibration['next_calibration_date'] ?? '' ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Calibrated By</label>
                <input type="text" name="calibrated_by" class="form-control" value="<?= e($calibration['calibrated_by'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Standard Used</label>
                <input type="text" name="calibration_standard" class="form-control" value="<?= e($calibration['calibration_standard'] ?? '') ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Certificate Number</label>
                <input type="text" name="certificate_number" class="form-control" value="<?= e($calibration['certificate_number'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Result</label>
                <select name="result" class="form-select">
                    <option value="Pass" <?= ($calibration['result']??'')=='Pass'?'selected':'' ?>>Pass</option>
                    <option value="Fail" <?= ($calibration['result']??'')=='Fail'?'selected':'' ?>>Fail</option>
                    <option value="Conditional" <?= ($calibration['result']??'')=='Conditional'?'selected':'' ?>>Conditional</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($calibration['notes'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Save</button>
    </form>
</div></div>
