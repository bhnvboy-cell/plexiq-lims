<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $assignment ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $assignment ? 'Edit Assignment' : 'New Training Assignment' ?></h4>
    <a href="/training/assignments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $assignment ? '/training/assignments/' . $assignment['id'] : '/training/assignments' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Employee <span class="text-danger">*</span></label>
                    <select name="user_id" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($assignment['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name'] ?: $u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($assignment['course_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Assigned" <?= ($assignment['status'] ?? '') === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="In Progress" <?= ($assignment['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Completed" <?= ($assignment['status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Overdue" <?= ($assignment['status'] ?? '') === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($assignment['due_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Score (%)</label>
                    <input name="score" type="number" step="0.1" min="0" max="100" class="form-control" value="<?= e($assignment['score'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Completed Date</label>
                    <input name="completed_date" type="date" class="form-control" value="<?= e($assignment['completed_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($assignment['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $assignment ? 'Update' : 'Create' ?> Assignment</button>
            </div>
        </form>
    </div>
</div>
