<?php $title = 'Sample: ' . $sample['sample_code']; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-file-text"></i> Sample: <?= htmlspecialchars($sample['sample_code']) ?></h2>
        <?php if (!empty($sample['spec_version_number'])): ?>
            <div class="mt-1">
                <span class="badge bg-primary fs-6"><i class="bi bi-bookmark-check me-1"></i>Spec <?= htmlspecialchars($sample['spec_code'] ?? 'SPEC') ?> v<?= htmlspecialchars((string)$sample['spec_version_number']) ?>.0</span>
                <?php if (!empty($sample['spec_grade'])): ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($sample['spec_grade']) ?></span>
                <?php endif; ?>
                <span class="badge bg-info text-dark"><?= htmlspecialchars($sample['sample_stage'] ?? 'General') ?></span>
                <?php if (!empty($sample['receipt_check_status'])): ?>
                    <span class="badge bg-<?= $sample['receipt_check_status'] === 'Passed' ? 'success' : ($sample['receipt_check_status'] === 'Conditional' ? 'warning text-dark' : 'danger') ?>">
                        <i class="bi bi-shield-check me-1"></i>Receipt: <?= htmlspecialchars($sample['receipt_check_status']) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/samples/<?= $sample['id'] ?>/edit" class="btn btn-warning"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <a href="/samples" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-light fw-bold"><i class="bi bi-info-circle me-2"></i>Sample Details</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Sample Code</th><td><strong><?= htmlspecialchars($sample['sample_code']) ?></strong></td></tr>
                    <tr><th>Product</th><td><?= htmlspecialchars($sample['product_name'] ?? 'N/A') ?> <?= !empty($sample['product_code']) ? '(' . htmlspecialchars($sample['product_code']) . ')' : '' ?></td></tr>
                    <tr><th>Customer</th><td><?= htmlspecialchars($sample['customer_name'] ?? 'Internal') ?></td></tr>
                    <tr><th>Lifecycle Stage</th><td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sample['sample_stage'] ?? 'Finished Good') ?></span></td></tr>
                    <tr><th>Batch / Lot Number</th><td><?= htmlspecialchars($sample['batch_number'] ?? '-') ?></td></tr>
                    <tr><th>Batch Size</th><td><?= htmlspecialchars($sample['batch_size'] ?? '-') ?></td></tr>
                    <tr><th>Priority</th><td><span class="badge bg-<?= ['Urgent'=>'danger','High'=>'warning','Normal'=>'primary','Low'=>'secondary'][$sample['priority']] ?? 'secondary' ?>"><?= $sample['priority'] ?></span></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?= ['Registered'=>'secondary','In Progress'=>'info','Reviewed'=>'primary','Approved'=>'success','COA Released'=>'success','Rejected'=>'danger'][$sample['status']] ?? 'secondary' ?>"><?= $sample['status'] ?></span></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-header bg-light fw-bold"><i class="bi bi-calendar3 me-2"></i>Dates & Personnel</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Sampling Date</th><td><?= $sample['sampling_date'] ?? $sample['received_date'] ?></td></tr>
                    <tr><th>Received Date</th><td><?= $sample['received_date'] ?></td></tr>
                    <tr><th>Target Completion</th><td><?= $sample['target_completion_date'] ?? '-' ?></td></tr>
                    <tr><th>Manufacture Date</th><td><?= $sample['manufacture_date'] ?? '-' ?></td></tr>
                    <tr><th>Expiry Date</th><td><?= $sample['expiry_date'] ?? '-' ?></td></tr>
                    <tr><th>Analyst</th><td><?= htmlspecialchars($sample['analyst_name'] ?? 'Unassigned') ?></td></tr>
                    <tr><th>Reviewer</th><td><?= htmlspecialchars($sample['reviewer_name'] ?? 'Unassigned') ?></td></tr>
                    <tr><th>Approver</th><td><?= htmlspecialchars($sample['approver_name'] ?? 'Unassigned') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($sample['receipt_check_notes'])): ?>
<div class="alert alert-info py-2 mb-3">
    <i class="bi bi-shield-check me-2"></i><strong>Receipt Inspection Note:</strong> <?= htmlspecialchars($sample['receipt_check_notes']) ?>
</div>
<?php endif; ?>

<?php if ($sample['notes']): ?>
<div class="card mb-3 shadow-sm border-0">
    <div class="card-header bg-light fw-bold"><i class="bi bi-chat-left-text me-2"></i>Notes</div>
    <div class="card-body"><?= nl2br(htmlspecialchars($sample['notes'])) ?></div>
</div>
<?php endif; ?>

<!-- Workflow Transitions with Quality Guards -->
<?php if (in_array($auth['role'], ['Admin', 'Reviewer', 'Approver', 'Analyst'])): ?>
<div class="card mb-3 shadow-sm border-0">
    <div class="card-header bg-light fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Lifecycle Transition (Quality Guarded)</div>
    <div class="card-body">
        <form method="POST" action="/samples/<?= $sample['id'] ?>/transition" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <?php
            $allowed = [
                'Registered' => ['In Progress'],
                'In Progress' => ['Reviewed', 'Rejected'],
                'Reviewed' => ['Approved', 'Rejected'],
                'Approved' => ['COA Released']
            ];
            $transitions = $allowed[$sample['status']] ?? [];
            ?>
            <?php if ($transitions): ?>
            <div class="col-auto">
                <label class="form-label small text-muted">Next Lifecycle Target:</label>
                <select name="target_status" class="form-select">
                    <?php foreach ($transitions as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1"></i> Transition Lifecycle</button>
            </div>
            <div class="col-12 mt-2">
                <small class="text-muted"><i class="bi bi-shield-lock me-1"></i>Automated Quality Guards: Verifies receipt check, ensures all required tests are completed, and checks for open OOS before approval.</small>
            </div>
            <?php else: ?>
            <div class="col-auto"><span class="text-muted">No further workflow actions available (Terminal State: <?= htmlspecialchars($sample['status']) ?>).</span></div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($sample['status'] === 'Approved' && in_array($auth['role'], ['Admin', 'Approver'])): ?>
<form method="POST" action="/coa/generate/<?= $sample['id'] ?>" class="mb-3">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-file-earmark-pdf me-1"></i> Generate GxP COA</button>
</form>
<?php endif; ?>

<!-- Assigned Tests & Results Table -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <span class="fw-bold"><i class="bi bi-clipboard-check me-2"></i>Assigned Analyses & Frozen Limits Snapshots</span>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst']) && $sample['status'] !== 'COA Released'): ?>
        <div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAdHocTestModal">
                <i class="bi bi-plus-circle me-1"></i> Add Ad-hoc Analysis
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Analysis Code</th>
                        <th>Analysis Name</th>
                        <th>Method / SOP</th>
                        <th>Specification Snapshot</th>
                        <th>Result Value</th>
                        <th>Unit</th>
                        <th>Compliance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sampleTests as $st): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($st['test_code'] ?? '-') ?></strong>
                            <?php if (!empty($st['is_mandatory'])): ?>
                                <span class="badge bg-danger ms-1">Req</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border ms-1">Ad-hoc</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($st['test_name'] ?? '-') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($st['method_name'] ?? '-') ?></small></td>
                        <td><code><?= htmlspecialchars($st['spec_limit_text'] ?? '-') ?></code></td>
                        <td>
                            <?php if ($st['reportable_value'] !== null): ?>
                                <strong><?= htmlspecialchars((string)$st['reportable_value']) ?></strong>
                            <?php elseif ($st['result_value'] !== null): ?>
                                <strong><?= htmlspecialchars((string)$st['result_value']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted"><?= htmlspecialchars($st['result_text'] ?? '-') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($st['unit_code'] ?? '-') ?></td>
                        <td>
                            <?php if ($st['is_within_spec'] === true): ?><span class="badge bg-success">In Spec</span>
                            <?php elseif ($st['is_within_spec'] === false): ?><span class="badge bg-danger">OOS</span>
                            <?php else: ?><span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= \App\Models\TestItem::getStatusBadge($st['status']) ?></td>
                        <td class="text-end">
                            <?php if ($st['status'] === 'Pending' || $st['status'] === 'In Progress'): ?>
                            <a href="/tests/<?= $st['id'] ?>/result" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Enter</a>
                            <?php endif; ?>
                            <?php if ($st['status'] === 'Completed' && in_array($auth['role'], ['Admin', 'Reviewer'])): ?>
                            <a href="/tests/review" class="btn btn-sm btn-warning"><i class="bi bi-check"></i> Review</a>
                            <?php endif; ?>
                            <?php if (in_array($auth['role'], ['Admin', 'Analyst']) && in_array($st['status'], ['Pending', 'In Progress'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeTestModal_<?= $st['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sampleTests)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No analyses assigned to this sample.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chain of Custody -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <span class="fw-bold"><i class="bi bi-signpost-2 me-2"></i>Chain of Custody & Traceability</span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#cocModal">
            <i class="bi bi-plus"></i> Record Transfer
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($custody)): ?>
        <p class="text-center text-muted py-4 mb-0">No custody transfers recorded for this sample.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>From</th><th>To</th><th>Transferred</th><th>Received</th><th>Location</th><th>Seal</th><th>Reason</th></tr></thead>
            <tbody>
                <?php foreach ($custody as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($c['transfer_from'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['transfer_to'] ?? '-') ?></td>
                    <td>
                        <small class="text-muted"><?= date('Y-m-d H:i', strtotime($c['transferred_at'])) ?></small>
                        <div class="small"><?= htmlspecialchars($c['transferred_by_name'] ?? '-') ?></div>
                    </td>
                    <td>
                        <?php if ($c['received_at']): ?>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($c['received_at'])) ?></small>
                            <div class="small"><?= htmlspecialchars($c['received_by_name'] ?? '-') ?></div>
                        <?php else: ?>
                            <form method="POST" action="/coc/<?= $c['id'] ?>/receive" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i> Receive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['location'] ?? '-') ?></td>
                    <td>
                        <?php if ($c['sealed']): ?>
                            <span class="badge bg-info"><?= htmlspecialchars($c['seal_number'] ?? 'Sealed') ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($c['custody_reason'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add Ad-Hoc Test with Mandatory Reason -->
<div class="modal fade" id="addAdHocTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/samples/<?= $sample['id'] ?>/add-test">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> Add Ad-hoc Analysis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Analysis *</label>
                        <select name="analysis_id" class="form-select" required>
                            <option value="">-- Choose Analysis --</option>
                            <?php if (!empty($analyses)): ?>
                                <?php foreach ($analyses as $ana): ?>
                                <option value="<?= $ana['id'] ?>"><?= htmlspecialchars($ana['analysis_code']) ?> - <?= htmlspecialchars($ana['analysis_name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Mandatory Justification Reason *</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Provide justification for adding an unplanned analysis (21 CFR Part 11 / GxP audit required)..." required></textarea>
                        <div class="form-text">This action will be permanently recorded in the ALCOA+ audit hash chain.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Add & Freeze Snapshot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals: Remove Test with Mandatory Reason -->
<?php foreach ($sampleTests as $st): ?>
<div class="modal fade" id="removeTestModal_<?= $st['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/samples/tests/<?= $st['id'] ?>/remove">
                <?= csrf_field() ?>
                <input type="hidden" name="sample_id" value="<?= $sample['id'] ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-1"></i> Remove Analysis</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove <strong><?= htmlspecialchars($st['test_code'] ?? 'Test') ?></strong> from this sample?</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Mandatory De-selection Reason *</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain why this test is being removed or canceled (GxP audit mandatory)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Confirm Removal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Chain of Custody Transfer Modal -->
<div class="modal fade" id="cocModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/samples/<?= $sample['id'] ?>/coc">
                <?= csrf_field() ?>
                <div class="modal-header"><h5 class="modal-title">Record Custody Transfer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transfer From</label>
                            <input type="text" name="transfer_from" class="form-control" placeholder="e.g. Sample Receiving">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transfer To</label>
                            <input type="text" name="transfer_to" class="form-control" placeholder="e.g. Analytical Lab">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transferred At</label>
                            <input type="datetime-local" name="transferred_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Storage Room A">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason / Purpose</label>
                        <input type="text" name="custody_reason" class="form-control" placeholder="e.g. Analysis, Storage, External testing">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condition Note</label>
                        <textarea name="condition_note" class="form-control" rows="2" placeholder="e.g. Sample intact, seal present"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="sealed" id="cocSealed">
                                <label class="form-check-label" for="cocSealed">Sealed</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Seal Number</label>
                            <input type="text" name="seal_number" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
