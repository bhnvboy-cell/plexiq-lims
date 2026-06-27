<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $record ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $record ? 'Edit' : 'New' ?> CAPA Record</h4>
    <a href="/capa" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $record ? '/capa/' . $record['id'] . '/update' : '/capa/store' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">CAPA Number <span class="text-danger">*</span></label>
                    <input name="capa_number" class="form-control" required value="<?= e($record['capa_number'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input name="title" class="form-control" required value="<?= e($record['title'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required><?= e($record['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source Type</label>
                    <select name="source_type" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="OOS" <?= ($record['source_type'] ?? '') === 'OOS' ? 'selected' : '' ?>>OOS</option>
                        <option value="Audit" <?= ($record['source_type'] ?? '') === 'Audit' ? 'selected' : '' ?>>Audit</option>
                        <option value="Customer Complaint" <?= ($record['source_type'] ?? '') === 'Customer Complaint' ? 'selected' : '' ?>>Customer Complaint</option>
                        <option value="Deviation" <?= ($record['source_type'] ?? '') === 'Deviation' ? 'selected' : '' ?>>Deviation</option>
                        <option value="Other" <?= ($record['source_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="Low" <?= ($record['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($record['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($record['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Critical" <?= ($record['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($record['due_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($record['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Root Cause</label>
                    <textarea name="root_cause" class="form-control" rows="2"><?= e($record['root_cause'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Corrective Action Plan</label>
                    <textarea name="corrective_action_plan" class="form-control" rows="2"><?= e($record['corrective_action_plan'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Preventive Action Plan</label>
                    <textarea name="preventive_action_plan" class="form-control" rows="2"><?= e($record['preventive_action_plan'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Effectiveness Check</label>
                    <textarea name="effectiveness_check" class="form-control" rows="2"><?= e($record['effectiveness_check'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $record ? 'Update' : 'Create' ?> CAPA</button>
            </div>
        </form>
    </div>
</div>
