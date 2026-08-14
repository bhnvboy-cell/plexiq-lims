<?php $title = $study ? 'Edit Stability Study' : 'New Stability Study'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clipboard-pulse me-2"></i><?= $study ? 'Edit' : 'New' ?> Stability Study</h4>
    <a href="/stability" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php $error = session_flash('error'); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row justify-content-center">
<div class="col-lg-10">
<div class="card shadow-sm">
<div class="card-header"><h5 class="mb-0"><?= $study ? 'Edit Study' : 'Create New Study' ?></h5></div>
<div class="card-body">
<form method="POST" action="<?= $study ? '/stability/' . $study['id'] : '/stability' ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Study Code <span class="text-danger">*</span></label>
            <input type="text" name="study_code" class="form-control" required value="<?= e($study['study_code'] ?? '') ?>" placeholder="e.g. STB-2026-001">
        </div>
        <div class="col-md-8">
            <label class="form-label">Study Name <span class="text-danger">*</span></label>
            <input type="text" name="study_name" class="form-control" required value="<?= e($study['study_name'] ?? '') ?>" placeholder="e.g. Accelerated Stability Study - Product X">
        </div>
        <div class="col-md-4">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select">
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($study['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['product_code']) ?> — <?= e($p['product_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Batch</label>
            <select name="batch_id" class="form-select">
                <option value="">— Select Batch —</option>
                <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= ($study['batch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['batch_number']) ?><?= !empty($b['product_name']) ? ' (' . e($b['product_name']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Study Type</label>
            <select name="study_type" class="form-select">
                <?php foreach ($studyTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= ($study['study_type'] ?? 'Long Term') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Temperature (°C)</label>
            <input type="number" name="condition_temperature" class="form-control" step="0.1" value="<?= e($study['condition_temperature'] ?? '') ?>" placeholder="e.g. 25">
        </div>
        <div class="col-md-3">
            <label class="form-label">Humidity (% RH)</label>
            <input type="number" name="condition_humidity" class="form-control" step="0.1" value="<?= e($study['condition_humidity'] ?? '') ?>" placeholder="e.g. 60">
        </div>
        <div class="col-md-3">
            <label class="form-label">Light Condition</label>
            <input type="text" name="condition_light" class="form-control" value="<?= e($study['condition_light'] ?? '') ?>" placeholder="e.g. Dark / 500 lux">
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Scheduled" <?= ($study['status'] ?? 'Scheduled') === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="Active" <?= ($study['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="On Hold" <?= ($study['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="Completed" <?= ($study['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Closed" <?= ($study['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option>
                <option value="Terminated" <?= ($study['status'] ?? '') === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Storage Condition (free text)</label>
            <input type="text" name="storage_condition" class="form-control" value="<?= e($study['storage_condition'] ?? '') ?>" placeholder="e.g. 25°C / 60% RH, closed container">
        </div>
        <div class="col-md-6">
            <label class="form-label">Protocol Reference</label>
            <input type="text" name="protocol_ref" class="form-control" value="<?= e($study['protocol_ref'] ?? '') ?>" placeholder="e.g. PR-QL-2026-007">
        </div>
        <div class="col-md-6">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($study['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= e($study['end_date'] ?? '') ?>">
        </div>
        <?php if ($study): ?>
        <div class="col-md-6">
            <label class="form-label">Report Conclusion</label>
            <input type="text" name="report_conclusion" class="form-control" value="<?= e($study['report_conclusion'] ?? '') ?>">
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $study ? 'Update Study' : 'Create Study' ?></button>
        <a href="/stability" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>
