<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-list-check me-2"></i><?= $action ? 'Edit Action' : 'Add Action' ?></h4>
    <a href="/deviations/<?= $deviation_id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Deviation</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $action ? '/deviations/' . $deviation_id . '/actions/' . $action['id'] : '/deviations/' . $deviation_id . '/actions' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="3" required><?= e($action['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($action['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($action['due_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="Low" <?= ($action['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($action['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($action['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Critical" <?= ($action['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Open" <?= ($action['status'] ?? '') === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="In Progress" <?= ($action['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Completed" <?= ($action['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $action ? 'Update' : 'Add' ?> Action</button>
            </div>
        </form>
    </div>
</div>
