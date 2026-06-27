<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="page-title mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i><?= e($record['oos_number']) ?></h4>
            <span class="badge bg-<?= match ($record['status']) { 'Open'=>'danger', 'Under Investigation'=>'warning', 'Closed'=>'success', default=>'secondary' } ?> fs-6"><?= e($record['status']) ?></span>
        </div>
        <span class="text-muted small">Initiated by <?= e($record['initiator_name'] ?? 'N/A') ?> on <?= e(date('Y-m-d', strtotime($record['created_at']))) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/oos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
        <a href="/oos/<?= $record['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <?php if ($auth['role'] === 'Admin'): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="if(confirm('Delete this OOS record?')){document.getElementById('delete-form').submit()}"><i class="bi bi-trash me-1"></i>Delete</button>
        <form id="delete-form" method="POST" action="/oos/<?= $record['id'] ?>/delete" class="d-none"><input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>"></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-info-circle me-1"></i>Record Details</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Test Parameter</div>
                        <div class="detail-value"><?= e($record['test_parameter'] ?: '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Severity</div>
                        <div class="detail-value"><?php $s = $record['severity']; ?><span class="badge bg-<?= match($s){'Critical'=>'danger','Major'=>'warning','Minor'=>'info',default=>'secondary'} ?>"><?= e($s) ?></span></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Disposition</div>
                        <div class="detail-value"><?php if ($record['disposition']): ?><span class="badge bg-<?= match($record['disposition']){'Approved'=>'success','Rejected'=>'danger','Rerun'=>'warning','Retest'=>'info',default=>'secondary'} ?>"><?= e($record['disposition']) ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Specification Range</div>
                        <div class="detail-value"><?= e($record['specification_range'] ?: '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Result</div>
                        <div class="detail-value"><?= e($record['result_value'] ?? $record['result_text'] ?? '-') ?> <?= e($record['unit'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Initiator</div>
                        <div class="detail-value"><?= e($record['initiator_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value"><?= e($record['assigned_name'] ?? 'Unassigned') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Description</div>
                        <div class="detail-value"><?= nl2br(e($record['description'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($record['status'] !== 'Closed' && in_array($auth['role'], ['Admin','Analyst','Reviewer'])): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-search me-1"></i>Investigation</strong></div>
            <div class="card-body">
                <form method="POST" action="/oos/<?= $record['id'] ?>/investigate">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Root Cause</label>
                            <textarea name="root_cause" class="form-control" rows="2"><?= e($record['investigation']['root_cause'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Immediate Action</label>
                            <textarea name="immediate_action" class="form-control" rows="2"><?= e($record['investigation']['immediate_action'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Corrective Action</label>
                            <textarea name="corrective_action" class="form-control" rows="2"><?= e($record['investigation']['corrective_action'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preventive Action</label>
                            <textarea name="preventive_action" class="form-control" rows="2"><?= e($record['investigation']['preventive_action'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Investigation Notes</label>
                            <textarea name="investigation_notes" class="form-control" rows="2"><?= e($record['investigation']['investigation_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-warning"><i class="bi bi-search me-1"></i>Save Investigation</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if ($record['status'] !== 'Closed' && $auth['role'] === 'Reviewer'): ?>
        <div class="card border-primary">
            <div class="card-header bg-primary text-white"><strong><i class="bi bi-check-circle me-1"></i>Review</strong></div>
            <div class="card-body">
                <form method="POST" action="/oos/<?= $record['id'] ?>/review">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <label class="form-label">Review Notes</label>
                    <textarea name="review_notes" class="form-control mb-3" rows="3"></textarea>
                    <button class="btn btn-primary w-100"><i class="bi bi-check-circle me-1"></i>Submit Review</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($record['status'] !== 'Closed' && in_array($auth['role'], ['Admin','Reviewer'])): ?>
        <div class="card border-success">
            <div class="card-header bg-success text-white"><strong><i class="bi bi-check2-square me-1"></i>Close OOS</strong></div>
            <div class="card-body">
                <form method="POST" action="/oos/<?= $record['id'] ?>/close">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <label class="form-label">Disposition</label>
                    <select name="disposition" class="form-select mb-3" required>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Rerun">Rerun</option>
                        <option value="Retest">Retest</option>
                    </select>
                    <label class="form-label">Disposition Notes</label>
                    <textarea name="disposition_notes" class="form-control mb-3" rows="2"></textarea>
                    <button class="btn btn-success w-100"><i class="bi bi-check2-square me-1"></i>Close OOS</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($record['investigation']): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-journal-text me-1"></i>Investigation Summary</strong></div>
            <div class="card-body small">
                <?php if ($record['investigation']['root_cause']): ?>
                <div class="mb-2"><strong>Root Cause:</strong><br><?= nl2br(e($record['investigation']['root_cause'])) ?></div>
                <?php endif; ?>
                <?php if ($record['investigation']['corrective_action']): ?>
                <div class="mb-2"><strong>Corrective Action:</strong><br><?= nl2br(e($record['investigation']['corrective_action'])) ?></div>
                <?php endif; ?>
                <?php if ($record['investigation']['review_notes']): ?>
                <div class="mb-2"><strong>Review Notes:</strong><br><?= nl2br(e($record['investigation']['review_notes'])) ?></div>
                <?php endif; ?>
                <?php if ($record['closed_at']): ?>
                <div class="text-muted mt-2 pt-2 border-top">Closed: <?= e($record['closed_at']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
