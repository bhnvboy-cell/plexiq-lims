<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $standard ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $standard ? 'Edit Standard' : 'New Calibration Standard' ?></h4>
    <a href="/calibrations/standards" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $standard ? '/calibrations/standards/' . $standard['id'] : '/calibrations/standards' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Standard Code <span class="text-danger">*</span></label>
                    <input name="standard_code" class="form-control" required value="<?= e($standard['standard_code'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Standard Name <span class="text-danger">*</span></label>
                    <input name="standard_name" class="form-control" required value="<?= e($standard['standard_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Standard Type</label>
                    <select name="standard_type" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Reference" <?= ($standard['standard_type'] ?? '') === 'Reference' ? 'selected' : '' ?>>Reference</option>
                        <option value="Working" <?= ($standard['standard_type'] ?? '') === 'Working' ? 'selected' : '' ?>>Working</option>
                        <option value="Check" <?= ($standard['standard_type'] ?? '') === 'Check' ? 'selected' : '' ?>>Check</option>
                        <option value="Primary" <?= ($standard['standard_type'] ?? '') === 'Primary' ? 'selected' : '' ?>>Primary</option>
                        <option value="Secondary" <?= ($standard['standard_type'] ?? '') === 'Secondary' ? 'selected' : '' ?>>Secondary</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input name="serial_number" class="form-control" value="<?= e($standard['serial_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Calibration Interval (days)</label>
                    <input name="calibration_interval_days" type="number" class="form-control" value="<?= e($standard['calibration_interval_days'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Calibration Date</label>
                    <input name="last_calibration_date" type="date" class="form-control" value="<?= e($standard['last_calibration_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Next Calibration Date</label>
                    <input name="next_calibration_date" type="date" class="form-control" value="<?= e($standard['next_calibration_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($standard['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ($standard['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $standard ? 'Update' : 'Create' ?> Standard</button>
            </div>
        </form>
    </div>
</div>
