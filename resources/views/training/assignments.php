<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-person-check me-2"></i>Training Assignments</h4>
        <span class="text-muted small"><?= count($assignments) ?> assignment(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal"><i class="bi bi-plus-lg me-1"></i>New Assignment</button>
    <?php endif; ?>
</div>

<?php if (empty($assignments)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-person-check"></i>
        <h5>No Assignments</h5>
        <p class="text-muted">No training assignments have been created yet.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Score</th>
                    <th>Completed Date</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= e($a['user_name'] ?? 'N/A') ?></td>
                    <td><strong><?= e($a['course_name'] ?? 'N/A') ?></strong></td>
                    <td><?php $sbadge = match ($a['status']) { 'Assigned'=>'secondary', 'In Progress'=>'info', 'Completed'=>'success', 'Overdue'=>'danger', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $sbadge ?>"><?= e($a['status']) ?></span>
                    </td>
                    <td><small class="text-muted"><?= e($a['due_date'] ?? '-') ?></small></td>
                    <td><?= $a['score'] !== null ? e($a['score']) . '%' : '-' ?></td>
                    <td><small class="text-muted"><?= e($a['completed_date'] ?? '-') ?></small></td>
                    <td class="text-end">
                        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
                        <a href="/training/assignments?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/training/assignments">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-1"></i>New Training Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input name="due_date" type="date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
