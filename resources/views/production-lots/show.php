<?php
layout('app');
$title = 'Production Lot: ' . $lot['lot_number'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-box-seam me-2"></i>Lot: <?= htmlspecialchars($lot['lot_number']) ?>
                <?php
                $badgeClass = match ($lot['disposition']) {
                    'Released' => 'bg-success',
                    'Rejected' => 'bg-danger',
                    'On Hold' => 'bg-warning text-dark',
                    'Conditionally Released' => 'bg-info text-dark',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $badgeClass ?> fs-6 align-middle ms-2"><?= $lot['disposition'] ?></span>
            </h1>
            <p class="text-muted mb-0">Product: <strong><?= htmlspecialchars($lot['product_name']) ?></strong> (<?= htmlspecialchars($lot['product_code']) ?>)</p>
        </div>
        <div>
            <?php if (in_array($auth['role'], ['Admin', 'Approver'])): ?>
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#dispositionModal">
                    <i class="bi bi-check-circle me-1"></i> Quality Disposition (Sign)
                </button>
                <form method="POST" action="/production-lots/<?= $lot['id'] ?>/sync-erp" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Sync to ERP
                    </button>
                </form>
            <?php endif; ?>
            <a href="/production-lots" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Release Readiness Assessment Card -->
    <div class="card shadow-sm border-0 mb-4 <?= $readiness['can_release'] ? 'border-start border-success border-4' : 'border-start border-danger border-4' ?>">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-shield-check me-2"></i>GxP Lot Release Readiness Assessment</span>
            <?php if ($readiness['can_release']): ?>
                <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>READY FOR RELEASE</span>
            <?php else: ?>
                <span class="badge bg-danger fs-6"><i class="bi bi-slash-circle me-1"></i>RELEASE BLOCKED (<?= count($readiness['blockers']) ?> Blockers)</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if ($readiness['can_release']): ?>
                        <div class="text-success fw-bold mb-1"><i class="bi bi-check2-all me-1"></i>All quality criteria satisfied:</div>
                        <p class="text-muted mb-0">All associated samples are approved, no open Out-Of-Specification (OOS) investigations exist, and specification limits are validated.</p>
                    <?php else: ?>
                        <div class="text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Mandatory Quality Release Blockers:</div>
                        <ul class="mb-0 text-danger ps-3">
                            <?php foreach ($readiness['blockers'] as $blocker): ?>
                                <li><?= htmlspecialchars($blocker) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-inline-block text-center px-3 border-end">
                        <div class="h4 mb-0"><?= $readiness['sample_count'] ?></div>
                        <small class="text-muted">Total Samples</small>
                    </div>
                    <div class="d-inline-block text-center px-3 border-end">
                        <div class="h4 mb-0 <?= $readiness['unapproved_samples_count'] > 0 ? 'text-warning' : 'text-success' ?>"><?= $readiness['unapproved_samples_count'] ?></div>
                        <small class="text-muted">Pending Samples</small>
                    </div>
                    <div class="d-inline-block text-center px-3">
                        <div class="h4 mb-0 <?= $readiness['open_oos_count'] > 0 ? 'text-danger' : 'text-success' ?>"><?= $readiness['open_oos_count'] ?></div>
                        <small class="text-muted">Open OOS</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metadata Details -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-light fw-bold"><i class="bi bi-card-text me-2"></i>Lot Specifications & ERP</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Lot Number</th><td><strong><?= htmlspecialchars($lot['lot_number']) ?></strong></td></tr>
                        <tr><th>ERP Batch Ref</th><td><code><?= htmlspecialchars($lot['erp_batch_number'] ?? '-') ?></code></td></tr>
                        <tr><th>Product</th><td><?= htmlspecialchars($lot['product_name']) ?></td></tr>
                        <tr><th>Approved Spec Version</th><td><span class="badge bg-primary">Spec <?= htmlspecialchars($lot['spec_code'] ?? 'SPEC') ?> v<?= htmlspecialchars((string)($lot['spec_version_number'] ?? '1')) ?>.0</span></td></tr>
                        <tr><th>Lot Size</th><td><?= $lot['lot_size'] !== null ? htmlspecialchars((string)$lot['lot_size']) . ' ' . htmlspecialchars($lot['lot_unit']) : '-' ?></td></tr>
                        <tr><th>Mfg / Expiry Dates</th><td><?= $lot['manufacturing_date'] ?? '-' ?> &nbsp;/&nbsp; <?= $lot['expiry_date'] ?? '-' ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-light fw-bold"><i class="bi bi-pen me-2"></i>Disposition & Electronic Signature</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Current Disposition</th><td><span class="badge <?= $badgeClass ?>"><?= $lot['disposition'] ?></span></td></tr>
                        <tr><th>Authorized By</th><td><?= htmlspecialchars($lot['disposition_by_name'] ?? 'Pending Authorization') ?></td></tr>
                        <tr><th>Authorization Timestamp</th><td><?= $lot['disposition_at'] ? date('Y-m-d H:i:s', strtotime($lot['disposition_at'])) : '-' ?></td></tr>
                        <tr><th>Disposition Justification</th><td><?= htmlspecialchars($lot['disposition_reason'] ?? '-') ?></td></tr>
                        <tr><th>Part 11 Signature Hash</th><td><small class="text-muted text-break"><code><?= $lot['disposition_signature_hash'] ?? 'Unsigned' ?></code></small></td></tr>
                        <tr><th>ERP Sync Status</th><td>
                            <?php if ($lot['erp_sync_status'] === 'Synced'): ?>
                                <span class="badge bg-success">Synced</span> (<?= date('Y-m-d H:i', strtotime($lot['erp_sync_at'])) ?>)
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= $lot['erp_sync_status'] ?></span>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">Idempotency Key: <code><?= htmlspecialchars($lot['erp_sync_idempotency_key'] ?? 'N/A') ?></code></div>
                        </td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Associated Quality Samples -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-collection me-2"></i>Quality Control Samples Linked to this Lot</span>
            <span class="badge bg-secondary"><?= count($samples) ?> sample(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Sample Code</th>
                            <th>Stage</th>
                            <th>Sampling Date</th>
                            <th>Analyst</th>
                            <th>Tests Complete</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($samples)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No samples linked to this lot yet. Register a sample with this lot number.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($samples as $s): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['sample_code']) ?></strong></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['sample_stage'] ?? 'General') ?></span></td>
                                    <td><?= $s['sampling_date'] ?? $s['received_date'] ?></td>
                                    <td><?= htmlspecialchars($s['analyst_name'] ?? 'Unassigned') ?></td>
                                    <td><?= $s['completed_tests'] ?> / <?= $s['total_tests'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $s['status'] === 'Approved' || $s['status'] === 'COA Released' ? 'success' : ($s['status'] === 'Rejected' ? 'danger' : 'secondary') ?>">
                                            <?= $s['status'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="/samples/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Sample
                                        </a>
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

<!-- Modal: Quality Disposition & E-Signature -->
<div class="modal fade" id="dispositionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/production-lots/<?= $lot['id'] ?>/disposition">
                <?= csrf_field() ?>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pen me-1"></i> Quality Lot Disposition Authorization</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$readiness['can_release']): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i><strong>Warning:</strong> Releasing this lot is currently blocked by quality criteria. You may place the lot On Hold or Reject it, but cannot Release it until blockers are cleared.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Quality Disposition Decision *</label>
                        <select name="disposition" class="form-select" required>
                            <option value="Released" <?= $readiness['can_release'] ? '' : 'disabled' ?>>Released (Commercial Distribution Approved)</option>
                            <option value="On Hold">On Hold (Pending Further Investigation)</option>
                            <option value="Rejected">Rejected (Out of Specification / Defective)</option>
                            <option value="Conditionally Released">Conditionally Released (Authorized Deviation / Reprocessing)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Mandatory Justification Reason (21 CFR Part 11) *</label>
                        <textarea name="disposition_reason" class="form-control" rows="3" placeholder="Provide quality justification for this lot disposition decision..." required></textarea>
                    </div>

                    <div class="alert alert-light border small text-muted">
                        <i class="bi bi-shield-lock me-1"></i><strong>Electronic Signature Manifestation:</strong> By clicking "Sign & Authorize", you apply your legally binding digital signature under 21 CFR Part 11 and EU Annex 11.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-pen me-1"></i> Sign & Authorize Disposition</button>
                </div>
            </form>
        </div>
    </div>
</div>
