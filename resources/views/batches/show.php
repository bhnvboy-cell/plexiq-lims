<?php
$title = 'Batch: ' . e($batch['batch_number']);
layout('app');
$steps = ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released'];
$currentIdx = array_search($batch['status'], $steps);
$stepIcons = ['bi-box', 'bi-gear', 'bi-check2-all', 'bi-check-circle', 'bi-file-earmark-check'];
$stepColors = ['secondary', 'info', 'primary', 'success', 'success'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0">
        <i class="bi bi-boxes me-2"></i>Batch: <?= e($batch['batch_number']) ?>
        <small class="text-muted fs-6 ms-2"><?= e($batch['product_name'] ?? '') ?></small>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/batches/<?= $batch['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="/labels/batch/<?= $batch['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank"><i class="bi bi-tag"></i> Print Labels</a>
        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addSampleModal"><i class="bi bi-plus-circle"></i> Add Sample</button>
        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#addTestsModal"><i class="bi bi-plus-square"></i> Add Tests</button>
        <?php
        $transitions = [
            'Registered' => ['In Progress'],
            'In Progress' => ['Reviewed', 'Rejected'],
            'Reviewed' => ['Approved', 'Rejected'],
            'Approved' => ['COA Released'],
        ];
        $allowed = $transitions[$batch['status']] ?? [];
        foreach ($allowed as $next):
        ?>
        <form method="POST" action="/batches/<?= $batch['id'] ?>/workflow" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= $next ?>">
            <button class="btn btn-sm btn-<?= $next === 'Rejected' ? 'danger' : 'success' ?>">
                <i class="bi bi-arrow-right"></i> <?= $next === 'Rejected' ? 'Reject' : 'Mark ' . $next ?>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<!-- Workflow Progress Stepper -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-4">
        <div class="row g-0 position-relative">
            <?php foreach ($steps as $i => $step):
                $isComplete = $i <= $currentIdx;
                $isCurrent = $i === $currentIdx;
                $isFuture = $i > $currentIdx;
                $lineClass = $i < $currentIdx ? 'bg-success' : 'bg-light';
                if ($i === 0) $lineClass .= ' ms-0';
            ?>
            <div class="col d-flex flex-column align-items-center position-relative">
                <?php if ($i > 0): ?>
                <div class="position-absolute top-0 start-0 translate-middle-y" style="width:100%;height:4px;top:24px!important;left:50%!important;z-index:0;">
                    <div class="<?= $lineClass ?>" style="height:4px;width:100%;border-radius:2px;"></div>
                </div>
                <?php endif; ?>
                <div class="d-flex flex-column align-items-center" style="z-index:1;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-2
                        <?= $isComplete ? 'bg-' . $stepColors[$i] : 'bg-light border' ?>
                        <?= $isCurrent ? 'shadow-sm border-' . $stepColors[$i] : '' ?>"
                        style="width:48px;height:48px;border:<?= $isComplete ? 'none' : '2px solid #dee2e6' ?>">
                        <i class="bi <?= $stepIcons[$i] ?> fs-5 <?= $isComplete ? 'text-white' : 'text-muted' ?>"></i>
                    </div>
                    <span class="small text-center fw-<?= $isCurrent ? 'bold' : 'normal' ?>
                        <?= $isComplete ? 'text-dark' : 'text-muted' ?>"
                        style="max-width:90px;line-height:1.2">
                        <?= $step ?>
                    </span>
                    <?php if ($isCurrent): ?>
                    <span class="badge bg-<?= $stepColors[$i] ?> mt-1" style="font-size:9px">CURRENT</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Batch Info -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-1"></i>Batch Details</h6></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Batch #</td><td class="fw-bold"><?= e($batch['batch_number']) ?></td></tr>
                    <tr><td class="text-muted">Product</td><td><?= e($batch['product_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Category</td><td><span class="badge bg-info bg-opacity-10 text-info"><?= e($batch['category'] ?? '—') ?></span></td></tr>
                    <tr><td class="text-muted">Size</td><td><?= e($batch['batch_size'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Mfg Date</td><td><?= $batch['manufacture_date'] ? date('d M Y', strtotime($batch['manufacture_date'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Expiry Date</td><td><?= $batch['expiry_date'] ? date('d M Y', strtotime($batch['expiry_date'])) : '—' ?></td></tr>
                    <tr><td class="text-muted">Created By</td><td><?= e($batch['created_by_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Created At</td><td><?= date('d M Y H:i', strtotime($batch['created_at'])) ?></td></tr>
                </table>
                <?php if ($batch['notes']): ?>
                <hr><small class="text-muted d-block mb-1">Notes:</small>
                <p class="mb-0 small"><?= nl2br(e($batch['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Analysis Parameters -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clipboard-data me-1"></i>Analysis Parameters</h6>
                <span class="badge bg-secondary"><?= count($batch['tests']) ?> tests</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Test Parameter</th><th>Method</th><th>Unit</th>
                            <th>Specification</th><th>Result</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batch['tests'])): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No tests mapped to this product. <a href="/master/product-tests">Configure in Product-Test Mapping</a>.</td></tr>
                        <?php else: $idx = 0; foreach ($batch['tests'] as $test):
                            $idx++;
                            $results = $batch['testResults'][$test['test_id']] ?? [];
                            $latestResult = null;
                            $overallStatus = 'Pending';
                            $sampleTestId = null;
                            foreach ($results as $r) {
                                $overallStatus = $r['test_status'];
                                $sampleTestId = $r['sample_test_id'] ?? null;
                                if ($r['result_value'] !== null || $r['result_text'] !== null) {
                                    $latestResult = $r;
                                }
                            }
                            $specText = $test['spec_limit_text']
                                ?? ($test['min_spec_limit'] !== null ? $test['min_spec_limit'] . ' - ' . $test['max_spec_limit'] : '—');
                        ?>
                        <tr>
                            <td class="text-muted"><?= $idx ?></td>
                            <td><strong><?= e($test['test_name']) ?></strong></td>
                            <td><?= e($test['method_name'] ?? '—') ?></td>
                            <td><?= e($test['unit_code'] ?? '—') ?></td>
                            <td><code><?= e($specText) ?></code></td>
                            <td>
                                <?php if ($latestResult): ?>
                                    <span class="<?= $latestResult['is_within_spec'] ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
                                        <?= $latestResult['result_value'] !== null ? e($latestResult['result_value']) : e($latestResult['result_text'] ?? '—') ?>
                                    </span>
                                    <?php if (!$latestResult['is_within_spec']): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Out of Spec"></i>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= status_badge($overallStatus) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($overallStatus === 'Completed' || $overallStatus === 'Reviewed' || $overallStatus === 'Approved'): ?>
                                    <form method="POST" action="/batches/retest/<?= $sampleTestId ?>" class="d-inline" onsubmit="return confirm('Reset this test for re-analysis? Results will be cleared.')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline-warning btn-sm" title="Recheck / Retest"><i class="bi bi-arrow-counterclockwise"></i> Recheck</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($overallStatus === 'Pending' || $overallStatus === 'In Progress'): ?>
                                    <a href="/tests/<?= $sampleTestId ?>/result" class="btn btn-outline-primary btn-sm" title="Enter Result"><i class="bi bi-pencil-square"></i> Enter</a>
                                    <form method="POST" action="/batches/remove-test/<?= $sampleTestId ?>" class="d-inline" onsubmit="return confirm('Remove this test from the sample?')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline-danger btn-sm" title="Remove"><i class="bi bi-x"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Samples in this batch -->
        <div class="card shadow-sm mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-collection me-1"></i>Samples in this Batch</h6>
                <span class="badge bg-secondary"><?= count($batch['samples']) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($batch['samples'])): ?>
                <div class="text-center text-muted py-3">No samples in this batch.</div>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Sample Code</th><th>Customer</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($batch['samples'] as $s): ?>
                        <tr>
                            <td><a href="/samples/<?= $s['id'] ?>" class="text-decoration-none fw-bold"><?= e($s['sample_code']) ?></a></td>
                            <td><?= e($s['customer_name'] ?? '—') ?></td>
                            <td><?= status_badge($s['status']) ?></td>
                            <td>
                                <a href="/samples/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <a href="/labels/sample/<?= $s['id'] ?>" class="btn btn-sm btn-outline-success" target="_blank" title="Print Label"><i class="bi bi-tag"></i> Label</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Sample Modal -->
<div class="modal fade" id="addSampleModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/batches/<?= $batch['id'] ?>/add-sample">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add Sample to Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="text-muted mb-0">A new sample will be created in this batch with <strong><?= count($batch['tests']) ?></strong> tests auto-assigned from the product specification.</p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create Sample</button>
    </div>
</form>
</div></div></div>

<!-- Add Tests Modal -->
<div class="modal fade" id="addTestsModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST" action="/batches/<?= $batch['id'] ?>/add-tests">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-square me-1"></i>Add Tests to Batch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="text-muted">Select additional tests to assign to <strong>all samples</strong> in this batch.</p>
        <div class="row g-2">
            <?php
            $allTests = \App\Models\TestItem::allWithDetails();
            $existingTestIds = array_column($batch['tests'], 'test_id');
            $unused = array_filter($allTests, function($t) use ($existingTestIds) {
                return !in_array($t['id'], $existingTestIds);
            });
            if (empty($unused)):
            ?>
            <div class="col-12 text-center text-muted py-3">All available tests are already assigned to this batch.</div>
            <?php else:
                foreach ($unused as $t):
            ?>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="test_ids[]" value="<?= $t['id'] ?>" id="t<?= $t['id'] ?>">
                    <label class="form-check-label" for="t<?= $t['id'] ?>">
                        <?= e($t['test_name']) ?>
                        <small class="text-muted">(<?= e($t['test_code']) ?>)</small>
                    </label>
                </div>
            </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-info"><i class="bi bi-plus-square"></i> Add Tests</button>
    </div>
</form>
</div></div></div>
