<?php
layout('app');
$title = 'Lab Analytical Batch: ' . $batch['batch_code'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-flask me-2"></i>Run: <?= htmlspecialchars($batch['batch_code']) ?>
                <?php
                $statusClass = match ($batch['status']) {
                    'QC_Passed' => 'bg-success',
                    'QC_Failed' => 'bg-danger',
                    'Prepared' => 'bg-info text-dark',
                    'Running' => 'bg-primary',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $statusClass ?> fs-6 align-middle ms-2"><?= $batch['status'] ?></span>
                <?php if ($batch['qc_status'] === 'Passed'): ?>
                    <span class="badge bg-success fs-6 align-middle"><i class="bi bi-shield-check me-1"></i>QC PASSED</span>
                <?php elseif ($batch['qc_status'] === 'Failed'): ?>
                    <span class="badge bg-danger fs-6 align-middle"><i class="bi bi-shield-x me-1"></i>QC FAILED</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0">Analysis: <strong><?= htmlspecialchars($batch['analysis_code'] ?? '-') ?></strong> &bull; Instrument: <strong><?= htmlspecialchars($batch['instrument_name'] ?? '-') ?></strong> &bull; Analyst: <?= htmlspecialchars($batch['analyst_name'] ?? '-') ?></p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#addControlModal">
                <i class="bi bi-plus-circle me-1"></i> Add Control
            </button>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addSamplesModal">
                <i class="bi bi-collection me-1"></i> Add Samples
            </button>
            <a href="/lab-batches/<?= $batch['id'] ?>/export-worklist" class="btn btn-outline-success me-2">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Export Sequence
            </a>
            <form method="POST" action="/lab-batches/<?= $batch['id'] ?>/evaluate-qc" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-calculator me-1"></i> Evaluate Batch QC
                </button>
            </form>
            <a href="/lab-batches" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <?php if (!empty($batch['qc_evaluation_notes'])): ?>
        <div class="alert <?= $batch['qc_status'] === 'Passed' ? 'alert-success' : 'alert-danger' ?> py-2 mb-4">
            <i class="bi <?= $batch['qc_status'] === 'Passed' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <strong>QC Evaluation Outcome:</strong> <?= htmlspecialchars($batch['qc_evaluation_notes']) ?>
        </div>
    <?php endif; ?>

    <!-- Sequence Items Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-ol me-2"></i>Analytical Sequence & Injections</span>
            <span class="badge bg-secondary"><?= count($items) ?> item(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Seq #</th>
                            <th>Vial</th>
                            <th>Type</th>
                            <th>Identifier / Control Name</th>
                            <th>Inj Vol (&mu;L)</th>
                            <th>Expected / Tolerance</th>
                            <th>Actual Reading</th>
                            <th>QC Pass</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Sequence is empty. Add Controls (blank, standards) and Samples using the buttons above.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= $item['sequence_index'] ?></strong></td>
                                    <td><code><?= htmlspecialchars($item['vial_position'] ?? (string)$item['sequence_index']) ?></code></td>
                                    <td>
                                        <?php
                                        $typeBadge = match ($item['item_type']) {
                                            'blank' => 'bg-secondary',
                                            'calibration_standard' => 'bg-info text-dark',
                                            'qc_control' => 'bg-primary',
                                            'duplicate' => 'bg-warning text-dark',
                                            'spike' => 'bg-dark',
                                            default => 'bg-light text-dark border'
                                        };
                                        ?>
                                        <span class="badge <?= $typeBadge ?>"><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($item['item_type'] === 'sample'): ?>
                                            <strong><?= htmlspecialchars($item['sample_code'] ?? 'Sample') ?></strong>
                                            <span class="badge bg-light text-muted border ms-1"><?= htmlspecialchars($item['sample_stage'] ?? '') ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= htmlspecialchars($item['control_name'] ?? ucfirst($item['item_type'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($item['injection_volume_ul'] ?? 10.0)) ?></td>
                                    <td>
                                        <?php if ($item['item_type'] === 'blank'): ?>
                                            <small class="text-muted">Max: <?= $item['tolerance_max'] ?? '0.00' ?></small>
                                        <?php elseif ($item['tolerance_min'] !== null || $item['tolerance_max'] !== null): ?>
                                            <small class="text-muted"><?= $item['tolerance_min'] ?? '-' ?> &ndash; <?= $item['tolerance_max'] ?? '-' ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['actual_value'] !== null): ?>
                                            <strong><?= htmlspecialchars((string)$item['actual_value']) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['item_type'] === 'sample'): ?>
                                            <span class="badge bg-light text-muted border"><?= htmlspecialchars($item['test_status'] ?? 'Pending') ?></span>
                                        <?php elseif ($item['qc_pass'] === true): ?>
                                            <span class="badge bg-success"><i class="bi bi-check"></i> PASS</span>
                                        <?php elseif ($item['qc_pass'] === false): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x"></i> FAIL</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($item['item_type'] !== 'sample'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recordReadingModal_<?= $item['id'] ?>">
                                                <i class="bi bi-pencil"></i> Reading
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Control Item -->
<div class="modal fade" id="addControlModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/lab-batches/<?= $batch['id'] ?>/add-sequence">
                <?= csrf_field() ?>
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> Add Analytical Control to Sequence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Control Type *</label>
                        <select name="item_type" class="form-select" required>
                            <option value="blank">Solvent Blank (Reagent / Method Blank)</option>
                            <option value="calibration_standard" selected>Calibration Standard</option>
                            <option value="qc_control">Quality Control Standard (QC Check)</option>
                            <option value="duplicate">Analytical Duplicate</option>
                            <option value="spike">Matrix Spike</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Control Description / Lot Name *</label>
                        <input type="text" name="control_name" class="form-control" placeholder="e.g. Standard 100% Cal-Check Lot #QC-99" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vial / Carousel Position</label>
                            <input type="text" name="vial_position" class="form-control" placeholder="e.g. 1 or A:1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Injection Vol (&mu;L)</label>
                            <input type="number" step="0.1" name="injection_volume_ul" class="form-control" value="10.0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expected Value</label>
                            <input type="number" step="0.0001" name="expected_value" class="form-control" placeholder="100.0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Tolerance</label>
                            <input type="number" step="0.0001" name="tolerance_min" class="form-control" placeholder="98.0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Tolerance</label>
                            <input type="number" step="0.0001" name="tolerance_max" class="form-control" placeholder="102.0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i> Insert into Sequence</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Samples to Sequence -->
<div class="modal fade" id="addSamplesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="/lab-batches/<?= $batch['id'] ?>/add-sequence">
                <?= csrf_field() ?>
                <input type="hidden" name="item_type" value="sample">
                <div class="modal-header bg-light">
                    <h5 class="modal-title"><i class="bi bi-collection me-1"></i> Add Samples to Analytical Sequence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($candidateTests)): ?>
                        <div class="alert alert-info py-3 mb-0">
                            <i class="bi bi-info-circle me-2"></i>No pending tests matching analysis <strong><?= htmlspecialchars($batch['analysis_code'] ?? '') ?></strong> are currently waiting for sequencing.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th width="40"></th>
                                        <th>Sample Code</th>
                                        <th>Product</th>
                                        <th>Stage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidateTests as $cand): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="sample_test_ids[]" value="<?= $cand['sample_test_id'] ?>" id="cand_<?= $cand['sample_test_id'] ?>" checked>
                                            </td>
                                            <td><label for="cand_<?= $cand['sample_test_id'] ?>" class="fw-bold"><?= htmlspecialchars($cand['sample_code']) ?></label></td>
                                            <td><?= htmlspecialchars($cand['product_name'] ?? '-') ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cand['sample_stage'] ?? '-') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($candidateTests)): ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i> Append Selected to Sequence</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals for Recording Actual Readings on Controls -->
<?php foreach ($items as $item): ?>
    <?php if ($item['item_type'] !== 'sample'): ?>
        <div class="modal fade" id="recordReadingModal_<?= $item['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="/lab-batches/<?= $batch['id'] ?>/record-reading">
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title"><i class="bi bi-pencil me-1"></i> Record Control Reading</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= htmlspecialchars($item['control_name'] ?? ucfirst($item['item_type'])) ?></label>
                                <div class="text-muted small mb-2">Tolerance Range: <?= $item['tolerance_min'] ?? '-' ?> &ndash; <?= $item['tolerance_max'] ?? '-' ?></div>
                                <input type="number" step="0.0001" name="actual_value" class="form-control form-control-lg" value="<?= $item['actual_value'] ?? '' ?>" placeholder="Enter instrument reading..." required autofocus>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Reading</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
