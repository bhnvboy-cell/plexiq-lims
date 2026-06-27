<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="page-title mb-0"><i class="bi bi-shield-check me-2"></i><?= e($record['capa_number']) ?></h4>
            <span class="badge bg-<?= match ($record['status']) { 'Open'=>'danger', 'In Progress'=>'warning', 'Under Review'=>'info', 'Completed'=>'primary', 'Closed'=>'success', default=>'secondary' } ?> fs-6"><?= e($record['status']) ?></span>
        </div>
        <span class="text-muted small">Created by <?= e($record['created_name'] ?? 'N/A') ?> &middot; Due <?= e($record['due_date'] ?? 'No due date') ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/capa" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
        <a href="/capa/<?= $record['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <?php if ($auth['role'] === 'Admin'): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="if(confirm('Delete this CAPA?')){document.getElementById('delete-form').submit()}"><i class="bi bi-trash me-1"></i>Delete</button>
        <form id="delete-form" method="POST" action="/capa/<?= $record['id'] ?>/delete" class="d-none"><input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>"></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-info-circle me-1"></i>Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Title</div>
                        <div class="detail-value"><?= e($record['title']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Source</div>
                        <div class="detail-value"><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($record['source_type'] ?: 'N/A') ?></span></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Priority</div>
                        <div class="detail-value"><?php $p = $record['priority']; echo "<span class=\"badge bg-" . match($p){'Critical'=>'danger','High'=>'warning','Medium'=>'info','Low'=>'secondary',default=>'secondary'} . " bg-opacity-10 text-" . match($p){'Critical'=>'danger','High'=>'warning','Medium'=>'info','Low'=>'secondary',default=>'secondary'} . "\">" . e($p) . '</span>'; ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value"><?= e($record['assigned_name'] ?? 'Unassigned') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Created By</div>
                        <div class="detail-value"><?= e($record['created_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Due Date</div>
                        <div class="detail-value"><?= e($record['due_date'] ?? '-') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Description</div>
                        <div class="detail-value"><?= nl2br(e($record['description'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-gear me-1"></i>Actions</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Root Cause</div>
                        <div class="detail-value"><?= nl2br(e($record['root_cause'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Corrective Action Plan</div>
                        <div class="detail-value"><?= nl2br(e($record['corrective_action_plan'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Preventive Action Plan</div>
                        <div class="detail-value"><?= nl2br(e($record['preventive_action_plan'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Effectiveness Check</div>
                        <div class="detail-value"><?= nl2br(e($record['effectiveness_check'] ?? 'Not entered')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($record['status'] !== 'Closed' && in_array($auth['role'], ['Admin','Reviewer'])): ?>
        <div class="card border-info">
            <div class="card-header bg-info text-white"><strong><i class="bi bi-arrow-repeat me-1"></i>Update Status</strong></div>
            <div class="card-body">
                <form method="POST" action="/capa/<?= $record['id'] ?>/status">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-select mb-3" required>
                        <option value="Open" <?= $record['status']==='Open'?'selected':'' ?>>Open</option>
                        <option value="In Progress" <?= $record['status']==='In Progress'?'selected':'' ?>>In Progress</option>
                        <option value="Under Review" <?= $record['status']==='Under Review'?'selected':'' ?>>Under Review</option>
                        <option value="Completed" <?= $record['status']==='Completed'?'selected':'' ?>>Completed</option>
                        <option value="Closed" <?= $record['status']==='Closed'?'selected':'' ?>>Closed</option>
                    </select>
                    <label class="form-label">Effectiveness Results</label>
                    <textarea name="effectiveness_results" class="form-control mb-3" rows="2"><?= e($record['effectiveness_results'] ?? '') ?></textarea>
                    <button class="btn btn-info w-100"><i class="bi bi-arrow-repeat me-1"></i>Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($record['effectiveness_results']): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-check2-circle me-1"></i>Effectiveness Results</strong></div>
            <div class="card-body"><?= nl2br(e($record['effectiveness_results'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($record['reviewed_by'] || $record['closed_by']): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-journal-text me-1"></i>Audit Trail</strong></div>
            <div class="card-body small">
                <?php if ($record['reviewed_name']): ?><p class="mb-2"><strong>Reviewed By:</strong> <?= e($record['reviewed_name']) ?></p><?php endif; ?>
                <?php if ($record['closed_at']): ?><p class="mb-0"><strong>Closed:</strong> <?= e($record['closed_at']) ?></p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
