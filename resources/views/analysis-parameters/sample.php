<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-clipboard2-check me-2"></i>Analysis Results &mdash; <?= e($sample['sample_code']) ?></h4>
        <span class="text-muted small">Batch <?= e($sample['batch_number'] ?? '-') ?></span>
    </div>
    <div>
        <a href="/samples/<?= $sample['id'] ?>" class="btn btn-outline-secondary btn-sm me-1"><i class="bi bi-arrow-left me-1"></i>Sample</a>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/samples/<?= $sample['id'] ?>/parameters" class="btn btn-primary btn-sm"><i class="bi bi-clipboard2-plus me-1"></i>Assign Parameters</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Parameter</th><th>Specification</th><th>Result</th><th>Unit</th>
                    <th>Within Spec</th><th>Status</th><th>Source</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <?php
                    $editable = in_array($row['status'], ['Pending', 'In Progress']) && in_array($auth['role'], ['Admin', 'Analyst']);
                    $reviewable = $row['status'] === 'Completed' && in_array($auth['role'], ['Admin', 'Reviewer']);
                    $approvable = $row['status'] === 'Reviewed' && in_array($auth['role'], ['Admin', 'Approver']);
                    $badge = ['Pending' => 'secondary', 'In Progress' => 'info', 'Completed' => 'primary', 'Reviewed' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
                ?>
                <tr>
                    <td>
                        <div class="fw-medium"><?= e($row['parameter_name']) ?></div>
                        <small class="text-muted"><?= e($row['parameter_code']) ?></small>
                    </td>
                    <td><small><?= e($row['specification_text'] ?? ($row['spec_min'] . ' - ' . $row['spec_max'])) ?></small></td>
                    <td>
                        <strong><?= $row['result_value'] !== null ? e($row['result_value']) : e($row['result_text'] ?? '-') ?></strong>
                    </td>
                    <td><small><?= e($row['unit'] ?? '-') ?></small></td>
                    <td>
                        <?php if ($row['result_value'] !== null || $row['result_text'] !== null): ?>
                            <?php if ($row['is_within_spec'] === null || $row['is_within_spec'] === true): ?>
                                <span class="badge bg-success">Pass</span>
                            <?php else: ?>
                                <span class="badge bg-danger">OOS</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $badge[$row['status']] ?? 'secondary' ?>"><?= e($row['status']) ?></span></td>
                    <td><small><?= e(ucfirst($row['source'] ?? 'manual')) ?></small></td>
                    <td class="text-end">
                        <?php if ($editable): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#result-<?= $row['id'] ?>"><i class="bi bi-pencil"></i> Enter Result</button>
                        <?php elseif ($reviewable): ?>
                        <form method="POST" action="/analysis-results/<?= $row['id'] ?>/review" class="d-inline" onsubmit="return confirm('Mark as reviewed?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sample_id" value="<?= $sample['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning">Review</button>
                        </form>
                        <?php elseif ($approvable): ?>
                        <form method="POST" action="/analysis-results/<?= $row['id'] ?>/approve" class="d-inline" onsubmit="return confirm('Approve and feed to SPC?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sample_id" value="<?= $sample['id'] ?>">
                            <button class="btn btn-sm btn-outline-success">Approve</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($editable): ?>
                <tr class="collapse" id="result-<?= $row['id'] ?>">
                    <td colspan="8" class="bg-light">
                        <form method="POST" action="/analysis-results/<?= $row['id'] ?>/record" class="row g-3 align-items-end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sample_id" value="<?= $sample['id'] ?>">
                            <?php if ($row['data_type'] === 'numeric'): ?>
                            <div class="col-md-3">
                                <label class="form-label small">Value</label>
                                <input type="number" step="any" name="result_value" class="form-control form-control-sm" required>
                            </div>
                            <?php else: ?>
                            <div class="col-md-3">
                                <label class="form-label small"><?= $row['data_type'] === 'boolean' ? 'Pass / Fail' : 'Result' ?></label>
                                <?php if ($row['data_type'] === 'boolean'): ?>
                                <select name="result_text" class="form-select form-select-sm">
                                    <option value="Pass">Pass</option><option value="Fail">Fail</option>
                                </select>
                                <?php else: ?>
                                <input type="text" name="result_text" class="form-control form-control-sm" required>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-5">
                                <label class="form-label small">Analyst Notes</label>
                                <input type="text" name="analyst_notes" class="form-control form-control-sm" placeholder="Optional">
                            </div>
                            <div class="col-md-auto">
                                <button class="btn btn-sm btn-primary">Save Result</button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No parameters assigned to this sample yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
