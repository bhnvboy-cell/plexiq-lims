<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-calendar-plus me-2"></i><?= $schedule ? 'Edit Schedule' : 'New Calibration Schedule' ?></h4>
    <a href="/calibrations/schedules" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $schedule ? '/calibrations/schedules/' . $schedule['id'] : '/calibrations/schedules' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Instrument <span class="text-danger">*</span></label>
                    <select name="instrument_id" class="form-select" required>
                        <option value="">-- Select Instrument --</option>
                        <?php foreach ($instruments as $inst): ?>
                        <option value="<?= $inst['id'] ?>" <?= ($schedule['instrument_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>><?= e($inst['instrument_name']) ?> (<?= e($inst['instrument_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Frequency <span class="text-danger">*</span></label>
                    <select name="frequency" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($frequencies as $f): ?>
                        <option value="<?= e($f) ?>" <?= ($schedule['frequency'] ?? '') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Next Due Date <span class="text-danger">*</span></label>
                    <input name="next_due_date" type="date" class="form-control" required value="<?= e($schedule['next_due_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Due Date</label>
                    <input name="last_due_date" type="date" class="form-control" value="<?= e($schedule['last_due_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned To</label>
                    <input name="assigned_to" class="form-control" value="<?= e($schedule['assigned_to'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($schedule['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $schedule ? 'Update' : 'Create' ?> Schedule</button>
            </div>
        </form>
    </div>
</div>
