<?php
layout('app');
$title = 'Result Lineage & GxP Traceability';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2"></i>Result Lineage & Data Pedigree</h1>
            <p class="text-muted mb-0">21 CFR Part 11 & ALCOA+ End-to-End Audit Trail: Raw Instrument File &bull; Formula &bull; Spec Snapshot &bull; Approvals &bull; COA</p>
        </div>
        <div>
            <?php if (!empty($lineage['result_id']) && in_array($auth['role'], ['Admin', 'Reviewer', 'Approver'])): ?>
                <button type="button" class="btn btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#invalidateModal">
                    <i class="bi bi-x-octagon me-1"></i> Invalidate Result
                </button>
            <?php endif; ?>
            <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <?php if (empty($lineage)): ?>
        <div class="alert alert-info py-4 text-center">
            <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
            <h5>Lineage Record Not Found</h5>
            <p class="mb-0 text-muted">No automated lineage snapshot has been recorded for Result ID #<?= (int)$resultId ?> yet.</p>
        </div>
    <?php else: ?>
        <?php if (!empty($lineage['is_invalidated'])): ?>
            <div class="alert alert-danger py-3 mb-4">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                <strong>RESULT INVALIDATED:</strong> <?= htmlspecialchars($lineage['invalidation_reason'] ?? 'Invalidated under quality protocol') ?>
            </div>
        <?php endif; ?>

        <!-- Provenance Timeline -->
        <div class="row">
            <!-- 1. Raw Data Vault & Instrument Origin -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white fw-bold">
                        <i class="bi bi-hdd-network me-2"></i>1. Raw Instrument Acquisition & Vault
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Original File</th>
                                <td><code><?= htmlspecialchars($lineage['raw_file_name'] ?? 'Direct Bench Entry') ?></code></td>
                            </tr>
                            <tr>
                                <th>SHA-256 Vault Hash</th>
                                <td>
                                    <?php if (!empty($lineage['raw_file_hash'])): ?>
                                        <small class="text-muted text-break"><code><?= htmlspecialchars($lineage['raw_file_hash']) ?></code></small>
                                        <span class="badge bg-success ms-1"><i class="bi bi-shield-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="text-muted">Manual / Edge Agent Direct</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Instrument</th>
                                <td><?= htmlspecialchars($lineage['instrument_name'] ?? 'Analytical Balance / Manual Bench') ?></td>
                            </tr>
                            <tr>
                                <th>Analytical Run / Batch</th>
                                <td>
                                    <?php if (!empty($lineage['lab_batch_code'])): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($lineage['lab_batch_code']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Ad-hoc Run</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. Analytical Parameter & Calculation -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="bi bi-calculator me-2"></i>2. Method & Calculation Engine
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Analysis Code</th>
                                <td><strong><?= htmlspecialchars($lineage['analysis_code'] ?? '-') ?></strong> &bull; <?= htmlspecialchars($lineage['analysis_name'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Calculation Formula</th>
                                <td>
                                    <?php if (!empty($lineage['formula_expression'])): ?>
                                        <code><?= htmlspecialchars($lineage['formula_expression']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Direct Instrumental Signal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Reportable Value</th>
                                <td>
                                    <span class="h5 mb-0 fw-bold"><?= htmlspecialchars((string)($lineage['reportable_value'] ?? $lineage['result_value'] ?? '-')) ?></span>
                                    <span class="text-muted"><?= htmlspecialchars($lineage['unit_snapshot'] ?? '') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Specification Status</th>
                                <td>
                                    <?php if ($lineage['is_within_spec'] === true): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Within Spec</span>
                                    <?php elseif ($lineage['is_within_spec'] === false): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Out-of-Specification (OOS)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. Frozen Specification Snapshot -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-info text-dark fw-bold">
                        <i class="bi bi-bookmark-check me-2"></i>3. Specification Limits Snapshot (Frozen at Login)
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Spec Code & Version</th>
                                <td><span class="badge bg-primary">Spec <?= htmlspecialchars($lineage['spec_code'] ?? 'SPEC') ?> v<?= htmlspecialchars((string)($lineage['spec_version_number'] ?? '1')) ?>.0</span></td>
                            </tr>
                            <tr>
                                <th>Snapshot Minimum</th>
                                <td><?= $lineage['spec_snapshot_min'] !== null ? htmlspecialchars((string)$lineage['spec_snapshot_min']) : 'None' ?></td>
                            </tr>
                            <tr>
                                <th>Snapshot Maximum</th>
                                <td><?= $lineage['spec_snapshot_max'] !== null ? htmlspecialchars((string)$lineage['spec_snapshot_max']) : 'None' ?></td>
                            </tr>
                            <tr>
                                <th>Immutability Guarantee</th>
                                <td><small class="text-success"><i class="bi bi-lock me-1"></i>Snapshot frozen at sample registration; unaffected by future master specification updates.</small></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. Review, Approval & Certificate of Analysis -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-success text-white fw-bold">
                        <i class="bi bi-award me-2"></i>4. Quality Verification, Approval & COA Release
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>Analyst (Tested By)</th>
                                <td><?= htmlspecialchars($lineage['analyst_name'] ?? 'Unassigned') ?></td>
                            </tr>
                            <tr>
                                <th>Reviewer (Verified By)</th>
                                <td><?= htmlspecialchars($lineage['reviewer_name'] ?? 'Pending Review') ?></td>
                            </tr>
                            <tr>
                                <th>Approver (Authorized By)</th>
                                <td><?= htmlspecialchars($lineage['approver_name'] ?? 'Pending Approval') ?></td>
                            </tr>
                            <tr>
                                <th>Certificate of Analysis</th>
                                <td>
                                    <?php if (!empty($lineage['coa_number'])): ?>
                                        <span class="badge bg-success"><i class="bi bi-file-earmark-pdf me-1"></i><?= htmlspecialchars($lineage['coa_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not yet released on COA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Invalidate Result -->
<div class="modal fade" id="invalidateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/results/<?= (int)$resultId ?>/invalidate">
                <?= csrf_field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-octagon me-1"></i> Invalidate Result (Part 11 GxP)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">Warning: Invalidating a result flags the record permanently in the audit chain and requeues the test for re-execution.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mandatory Justification Reason *</label>
                        <textarea name="invalidation_reason" class="form-control" rows="3" placeholder="Provide full justification for invalidation (e.g. verified instrument baseline drift per Investigation INV-26)..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Invalidate Result</button>
                </div>
            </form>
        </div>
    </div>
</div>
