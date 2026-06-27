<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= $project ? 'Edit' : 'New' ?> Project</h4>
    <a href="/projects" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="card shadow-sm">
<div class="card-body">
<form method="POST" action="<?= $project ? '/projects/' . $project['id'] . '/update' : '/projects/store' ?>">
<input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Project Code *</label><input name="project_code" class="form-control" required value="<?= e($project['project_code'] ?? '') ?>"></div>
<div class="col-md-8"><label class="form-label">Project Name *</label><input name="project_name" class="form-control" required value="<?= e($project['project_name'] ?? '') ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= e($project['description'] ?? '') ?></textarea></div>
<div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">
<option value="Active" <?= ($project['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
<option value="Completed" <?= ($project['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
<option value="On Hold" <?= ($project['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
<option value="Cancelled" <?= ($project['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
</select></div>
<div class="col-md-3"><label class="form-label">Priority</label><select name="priority" class="form-select">
<option value="Low" <?= ($project['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
<option value="Medium" <?= ($project['priority'] ?? '') === 'Medium' ? 'selected' : '' ?>>Medium</option>
<option value="High" <?= ($project['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
<option value="Critical" <?= ($project['priority'] ?? '') === 'Critical' ? 'selected' : '' ?>>Critical</option>
</select></div>
<div class="col-md-3"><label class="form-label">Start Date</label><input name="start_date" type="date" class="form-control" value="<?= e($project['start_date'] ?? '') ?>"></div>
<div class="col-md-3"><label class="form-label">Target End Date</label><input name="target_end_date" type="date" class="form-control" value="<?= e($project['target_end_date'] ?? '') ?>"></div>
<?php if ($project): ?>
<div class="col-md-3"><label class="form-label">Actual End Date</label><input name="actual_end_date" type="date" class="form-control" value="<?= e($project['actual_end_date'] ?? '') ?>"></div>
<?php endif; ?>
<div class="col-md-4"><label class="form-label">Manager</label><select name="manager_id" class="form-select">
<option value="">-- None --</option>
<?php foreach ($users as $u): ?>
<option value="<?= $u['id'] ?>" <?= ($project['manager_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?> (<?= e($u['role_name'] ?? '') ?>)</option>
<?php endforeach; ?>
</select></div>
</div>
<div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $project ? 'Update' : 'Create' ?> Project</button></div>
</form>
</div>
</div>
