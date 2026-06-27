<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="page-title mb-0"><i class="bi bi-exclamation-octagon me-2 text-warning"></i><?= e($deviation['deviation_number']) ?></h4>
            <span class="badge bg-<?= match ($deviation['status']) { 'Open'=>'danger', 'Under Investigation'=>'warning', 'Under Review'=>'info', 'Closed'=>'success', default=>'secondary' } ?> fs-6"><?= e($deviation['status']) ?></span>
        </div>
        <span class="text-muted small">Reported by <?= e($deviation['reporter_name'] ?? 'N/A') ?> on <?= e(date('Y-m-d', strtotime($deviation['created_at']))) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/deviations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
        <a href="/deviations/<?= $deviation['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <?php if ($auth['role'] === 'Admin'): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="if(confirm('Delete this deviation?')){document.getElementById('delete-form').submit()}"><i class="bi bi-trash me-1"></i>Delete</button>
        <form id="delete-form" method="POST" action="/deviations/<?= $deviation['id'] ?>" class="d-none"><input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>"></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-info-circle me-1"></i>Description</strong></div>
            <div class="card-body">
                <h5><?= e($deviation['title']) ?></h5>
                <p><?= nl2br(e($deviation['description'])) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-search me-1"></i>Impact & Root Cause</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Impact Assessment</div>
                        <div class="detail-value"><?= nl2br(e($deviation['impact_assessment'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Root Cause</div>
                        <div class="detail-value"><?= nl2br(e($deviation['root_cause'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Immediate Action</div>
                        <div class="detail-value"><?= nl2br(e($deviation['immediate_action'] ?? 'Not entered')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Corrective Action</div>
                        <div class="detail-value"><?= nl2br(e($deviation['corrective_action'] ?? 'Not entered')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-list-check me-1"></i>Actions</strong>
                <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#actionModal"><i class="bi bi-plus-lg me-1"></i>Add Action</button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Description</th><th>Assigned To</th><th>Due Date</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($actions as $a): ?>
                        <tr>
                            <td><?= e(substr($a['description'], 0, 60)) ?></td>
                            <td><?= e($a['assigned_name'] ?? 'Unassigned') ?></td>
                            <td><small class="text-muted"><?= e($a['due_date'] ?? '-') ?></small></td>
                            <td><?php $pbadge = match ($a['priority']) { 'Critical'=>'danger', 'High'=>'warning', 'Medium'=>'info', 'Low'=>'secondary', default=>'secondary' }; ?>
                                <span class="badge bg-<?= $pbadge ?> bg-opacity-10 text-<?= $pbadge ?>"><?= e($a['priority']) ?></span>
                            </td>
                            <td><?php $sbadge = match ($a['status'] ?? 'Open') { 'Open'=>'danger', 'In Progress'=>'warning', 'Completed'=>'success', default=>'secondary' }; ?>
                                <span class="badge bg-<?= $sbadge ?>"><?= e($a['status'] ?? 'Open') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($actions)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No actions defined.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-info-circle me-1"></i>Details</strong></div>
            <div class="card-body">
                <div class="detail-label">Deviation Type</div>
                <div class="detail-value mb-3"><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($deviation['deviation_type'] ?? 'N/A') ?></span></div>
                <div class="detail-label">Severity</div>
                <div class="detail-value mb-3"><?php $se = $deviation['severity']; ?><span class="badge bg-<?= match($se){'Critical'=>'danger','Major'=>'warning','Minor'=>'info',default=>'secondary'} ?>"><?= e($se) ?></span></div>
                <div class="detail-label">Source</div>
                <div class="detail-value mb-3"><?= e($deviation['source'] ?? 'N/A') ?></div>
                <div class="detail-label">Source ID</div>
                <div class="detail-value mb-3"><?= e($deviation['source_id'] ?? '-') ?></div>
                <div class="detail-label">Assigned To</div>
                <div class="detail-value mb-3"><?= e($deviation['assigned_name'] ?? 'Unassigned') ?></div>
                <div class="detail-label">Due Date</div>
                <div class="detail-value"><?= e($deviation['due_date'] ?? '-') ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-clock-history me-1"></i>Timeline</strong></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($timeline as $t): ?>
                    <div class="list-group-item border-0 py-2">
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-<?= match($t['type']){'created'=>'secondary','status_change'=>'primary','action_added'=>'info','comment'=>'success',default=>'secondary'} ?> rounded-pill mt-1" style="width:8px;height:8px;padding:0">&nbsp;</span>
                            <div class="small">
                                <div><?= nl2br(e($t['description'] ?? $t['message'] ?? '')) ?></div>
                                <div class="text-muted f-xs"><?= e($t['created_at'] ?? '') ?> &middot; <?= e($t['user_name'] ?? '') ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($timeline)): ?>
                    <div class="list-group-item text-muted small py-3 text-center">No timeline entries.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($deviation['status'] !== 'Closed' && in_array($auth['role'], ['Admin','Reviewer'])): ?>
        <div class="card border-primary">
            <div class="card-header bg-primary text-white"><strong><i class="bi bi-arrow-repeat me-1"></i>Update Status</strong></div>
            <div class="card-body">
                <form method="POST" action="/deviations/<?= $deviation['id'] ?>">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-select mb-3" required>
                        <option value="Open" <?= $deviation['status']==='Open'?'selected':'' ?>>Open</option>
                        <option value="Under Investigation" <?= $deviation['status']==='Under Investigation'?'selected':'' ?>>Under Investigation</option>
                        <option value="Under Review" <?= $deviation['status']==='Under Review'?'selected':'' ?>>Under Review</option>
                        <option value="Closed" <?= $deviation['status']==='Closed'?'selected':'' ?>>Closed</option>
                    </select>
                    <label class="form-label">Closing Notes</label>
                    <textarea name="closing_notes" class="form-control mb-3" rows="2"><?= e($deviation['closing_notes'] ?? '') ?></textarea>
                    <button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i>Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/deviations/<?= $deviation['id'] ?>/actions">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-1"></i>Add Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input name="due_date" type="date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Action</button>
                </div>
            </form>
        </div>
    </div>
</div>
