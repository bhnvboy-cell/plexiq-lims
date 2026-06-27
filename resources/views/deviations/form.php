<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $deviation ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $deviation ? 'Edit Deviation' : 'New Deviation' ?></h4>
    <a href="/deviations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $deviation ? '/deviations/' . $deviation['id'] : '/deviations' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Deviation Number <span class="text-danger">*</span></label>
                    <input name="deviation_number" class="form-control" required value="<?= e($deviation['deviation_number'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input name="title" class="form-control" required value="<?= e($deviation['title'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required><?= e($deviation['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deviation Type</label>
                    <select name="deviation_type" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Process" <?= ($deviation['deviation_type'] ?? '') === 'Process' ? 'selected' : '' ?>>Process</option>
                        <option value="Procedure" <?= ($deviation['deviation_type'] ?? '') === 'Procedure' ? 'selected' : '' ?>>Procedure</option>
                        <option value="Equipment" <?= ($deviation['deviation_type'] ?? '') === 'Equipment' ? 'selected' : '' ?>>Equipment</option>
                        <option value="Material" <?= ($deviation['deviation_type'] ?? '') === 'Material' ? 'selected' : '' ?>>Material</option>
                        <option value="Environmental" <?= ($deviation['deviation_type'] ?? '') === 'Environmental' ? 'selected' : '' ?>>Environmental</option>
                        <option value="Documentation" <?= ($deviation['deviation_type'] ?? '') === 'Documentation' ? 'selected' : '' ?>>Documentation</option>
                        <option value="Other" <?= ($deviation['deviation_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Severity <span class="text-danger">*</span></label>
                    <select name="severity" class="form-select" required>
                        <option value="Minor" <?= ($deviation['severity'] ?? '') === 'Minor' ? 'selected' : '' ?>>Minor</option>
                        <option value="Major" <?= ($deviation['severity'] ?? '') === 'Major' ? 'selected' : '' ?>>Major</option>
                        <option value="Critical" <?= ($deviation['severity'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Open" <?= ($deviation['status'] ?? '') === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="Under Investigation" <?= ($deviation['status'] ?? '') === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
                        <option value="Under Review" <?= ($deviation['status'] ?? '') === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Closed" <?= ($deviation['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Internal" <?= ($deviation['source'] ?? '') === 'Internal' ? 'selected' : '' ?>>Internal</option>
                        <option value="Customer" <?= ($deviation['source'] ?? '') === 'Customer' ? 'selected' : '' ?>>Customer</option>
                        <option value="Audit" <?= ($deviation['source'] ?? '') === 'Audit' ? 'selected' : '' ?>>Audit</option>
                        <option value="Regulatory" <?= ($deviation['source'] ?? '') === 'Regulatory' ? 'selected' : '' ?>>Regulatory</option>
                        <option value="Supplier" <?= ($deviation['source'] ?? '') === 'Supplier' ? 'selected' : '' ?>>Supplier</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Source ID / Reference</label>
                    <input name="source_id" class="form-control" placeholder="e.g. OOS-2026-001" value="<?= e($deviation['source_id'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($deviation['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($deviation['due_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Impact Assessment</label>
                    <textarea name="impact_assessment" class="form-control" rows="2"><?= e($deviation['impact_assessment'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $deviation ? 'Update' : 'Create' ?> Deviation</button>
            </div>
        </form>
    </div>
</div>
