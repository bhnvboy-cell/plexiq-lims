<?php $title = 'Sample: ' . $sample['sample_code']; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-text"></i> Sample: <?= htmlspecialchars($sample['sample_code']) ?></h2>
    <div>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/samples/<?= $sample['id'] ?>/edit" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <a href="/samples" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">Sample Details</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Sample Code</th><td><?= htmlspecialchars($sample['sample_code']) ?></td></tr>
                    <tr><th>Customer</th><td><?= htmlspecialchars($sample['customer_name'] ?? 'N/A') ?></td></tr>
                    <tr><th>Product</th><td><?= htmlspecialchars($sample['product_name'] ?? 'N/A') ?></td></tr>
                    <tr><th>Batch Number</th><td><?= htmlspecialchars($sample['batch_number'] ?? '-') ?></td></tr>
                    <tr><th>Batch Size</th><td><?= htmlspecialchars($sample['batch_size'] ?? '-') ?></td></tr>
                    <tr><th>Priority</th><td><span class="badge bg-<?= ['Urgent'=>'danger','High'=>'warning','Normal'=>'primary','Low'=>'secondary'][$sample['priority']] ?>"><?= $sample['priority'] ?></span></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?= ['Registered'=>'secondary','In Progress'=>'info','Reviewed'=>'primary','Approved'=>'success','COA Released'=>'success','Rejected'=>'danger'][$sample['status']] ?>"><?= $sample['status'] ?></span></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">Dates & Personnel</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Received Date</th><td><?= $sample['received_date'] ?></td></tr>
                    <tr><th>Target Completion</th><td><?= $sample['target_completion_date'] ?? '-' ?></td></tr>
                    <tr><th>Manufacture Date</th><td><?= $sample['manufacture_date'] ?? '-' ?></td></tr>
                    <tr><th>Expiry Date</th><td><?= $sample['expiry_date'] ?? '-' ?></td></tr>
                    <tr><th>Analyst</th><td><?= htmlspecialchars($sample['analyst_name'] ?? 'Unassigned') ?></td></tr>
                    <tr><th>Reviewer</th><td><?= htmlspecialchars($sample['reviewer_name'] ?? 'Unassigned') ?></td></tr>
                    <tr><th>Approver</th><td><?= htmlspecialchars($sample['approver_name'] ?? 'Unassigned') ?></td></tr>
                    <tr><th>Registered By</th><td><?= htmlspecialchars($sample['registered_by_name'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($sample['notes']): ?>
<div class="card mb-3">
    <div class="card-header">Notes</div>
    <div class="card-body"><?= nl2br(htmlspecialchars($sample['notes'])) ?></div>
</div>
<?php endif; ?>

<?php if (in_array($auth['role'], ['Admin', 'Reviewer', 'Approver'])): ?>
<div class="card mb-3">
    <div class="card-header">Workflow Actions</div>
    <div class="card-body">
        <form method="POST" action="/samples/<?= $sample['id'] ?>/workflow" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <?php
            $allowed = ['Registered'=>['In Progress'], 'In Progress'=>['Reviewed','Rejected'], 'Reviewed'=>['Approved','Rejected'], 'Approved'=>['COA Released']];
            $transitions = $allowed[$sample['status']] ?? [];
            ?>
            <?php if ($transitions): ?>
            <div class="col-auto">
                <select name="status" class="form-select">
                    <?php foreach ($transitions as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> Update Status</button>
            </div>
            <?php else: ?>
            <div class="col-auto"><span class="text-muted">No further workflow actions available.</span></div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($sample['status'] === 'Approved' && in_array($auth['role'], ['Admin', 'Approver'])): ?>
<form method="POST" action="/coa/generate/<?= $sample['id'] ?>" class="mb-3">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-file-earmark-pdf"></i> Generate COA</button>
</form>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-check"></i> Assigned Tests & Results</span>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst']) && $sample['status'] !== 'COA Released'): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignTestsModal">
            <i class="bi bi-plus"></i> Assign Tests
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Test Code</th><th>Test Name</th><th>Method</th><th>Specification</th><th>Result</th><th>Unit</th><th>Spec Status</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($sampleTests as $st): ?>
                <tr>
                    <td><?= htmlspecialchars($st['test_code']) ?></td>
                    <td><?= htmlspecialchars($st['test_name']) ?></td>
                    <td><?= htmlspecialchars($st['method_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($st['spec_limit_text'] ?? ($st['min_spec_limit'] . ' - ' . $st['max_spec_limit'])) ?></td>
                    <td><strong><?= $st['result_value'] !== null ? htmlspecialchars((string)$st['result_value']) : ($st['result_text'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($st['unit_code'] ?? '-') ?></td>
                    <td>
                        <?php if ($st['is_within_spec'] === true): ?><span class="badge bg-success">Pass</span>
                        <?php elseif ($st['is_within_spec'] === false): ?><span class="badge bg-danger">Fail</span>
                        <?php else: ?><span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?= \App\Models\TestItem::getStatusBadge($st['status']) ?></td>
                    <td>
                        <?php if ($st['status'] === 'Pending' || $st['status'] === 'In Progress'): ?>
                        <a href="/tests/<?= $st['id'] ?>/result" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Enter Result</a>
                        <?php endif; ?>
                        <?php if ($st['status'] === 'Completed' && in_array($auth['role'], ['Admin', 'Reviewer'])): ?>
                        <a href="/tests/review" class="btn btn-sm btn-warning"><i class="bi bi-check"></i> Review</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sampleTests)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">No tests assigned to this sample.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Tests Modal -->
<div class="modal fade" id="assignTestsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/samples/<?= $sample['id'] ?>/assign-tests">
                <?= csrf_field() ?>
                <div class="modal-header"><h5 class="modal-title">Assign Tests</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assign To (Analyst)</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Select Analyst</option>
                            <?php foreach ($analysts as $a): ?>
                            <option value="<?= $a->id ?>"><?= htmlspecialchars($a->full_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <?php foreach (\App\Models\TestItem::allWithDetails() as $t): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="test_ids[]" value="<?= $t['id'] ?>" id="modal_test_<?= $t['id'] ?>">
                                <label class="form-check-label" for="modal_test_<?= $t['id'] ?>"><?= htmlspecialchars($t['test_code']) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Tests</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
