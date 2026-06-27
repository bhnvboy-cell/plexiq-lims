<?php $title = $study ? 'Edit Stability Study' : 'New Stability Study'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clipboard-pulse me-2"></i><?= $study ? 'Edit' : 'New' ?> Stability Study</h4>
    <a href="/stability" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>
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
            <label class="form-label">Study Name</label>
            <input type="text" name="study_name" class="form-control" value="<?= e($study['study_name'] ?? '') ?>" placeholder="e.g. Accelerated Stability Study - Product X">
        </div>
        <div class="col-md-4">
            <label class="form-label">Product <span class="text-danger">*</span></label>
            <select name="product_id" class="form-select" required>
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?? $p->id ?>" <?= (($study['product_id'] ?? '') == ($p['id'] ?? $p->id)) ? 'selected' : '' ?>><?= e($p['product_code'] ?? $p->product_code) ?> — <?= e($p['product_name'] ?? $p->product_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Batch <span class="text-danger">*</span></label>
            <select name="batch_id" class="form-select" required>
                <option value="">— Select Batch —</option>
                <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?? $b->id ?>" <?= (($study['batch_id'] ?? '') == ($b['id'] ?? $b->id)) ? 'selected' : '' ?>><?= e($b['batch_number'] ?? $b->batch_number) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Scheduled" <?= ($study['status'] ?? 'Scheduled') === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="Active" <?= ($study['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="On Hold" <?= ($study['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="Completed" <?= ($study['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Terminated" <?= ($study['status'] ?? '') === 'Terminated' ? 'selected' : '' ?>>Terminated</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-bold">Storage Conditions</label>
            <div id="conditionsContainer">
                <?php
                $conds = !empty($study['conditions']) ? (is_array($study['conditions']) ? $study['conditions'] : [['condition_name'=>$study['conditions']]]) : [['condition_name'=>'','temperature'=>'','humidity'=>'']];
                foreach ($conds as $i => $cond):
                ?>
                <div class="row g-2 mb-2 condition-row">
                    <div class="col-md-4">
                        <input type="text" name="conditions[<?= $i ?>][condition_name]" class="form-control" placeholder="Condition name" value="<?= e($cond['condition_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="conditions[<?= $i ?>][temperature]" class="form-control" placeholder="Temperature (°C)" value="<?= e($cond['temperature'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="conditions[<?= $i ?>][humidity]" class="form-control" placeholder="Humidity (% RH)" value="<?= e($cond['humidity'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.condition-row').remove()"><i class="bi bi-x"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addCondition()"><i class="bi bi-plus"></i> Add Condition</button>
        </div>

        <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($study['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= e($study['end_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Responsible Person</label>
            <select name="responsible_id" class="form-select">
                <option value="">— Select —</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?? $u->id ?>" <?= (($study['responsible_id'] ?? '') == ($u['id'] ?? $u->id)) ? 'selected' : '' ?>><?= e($u['full_name'] ?? $u->full_name ?? $u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Description / Notes</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Optional study description..."><?= e($study['description'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $study ? 'Update Study' : 'Create Study' ?></button>
        <a href="/stability" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>

<script>
let conditionIndex = <?= count($conds ?? [1]) ?>;

function addCondition() {
    const container = document.getElementById('conditionsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 condition-row';
    row.innerHTML = `
        <div class="col-md-4">
            <input type="text" name="conditions[${conditionIndex}][condition_name]" class="form-control" placeholder="Condition name">
        </div>
        <div class="col-md-3">
            <input type="text" name="conditions[${conditionIndex}][temperature]" class="form-control" placeholder="Temperature (°C)">
        </div>
        <div class="col-md-3">
            <input type="text" name="conditions[${conditionIndex}][humidity]" class="form-control" placeholder="Humidity (% RH)">
        </div>
        <div class="col-md-2 d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.condition-row').remove()"><i class="bi bi-x"></i></button>
        </div>`;
    container.appendChild(row);
    conditionIndex++;
}
</script>
