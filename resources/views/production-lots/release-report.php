<?php
layout('app');
$title = 'Lot Release Certificate & Report: ' . $lot['lot_number'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-file-earmark-check me-2"></i>Lot Release Report</h1>
            <p class="text-muted mb-0">Official Quality Assurance Disposition Certificate & Monograph Release Summary</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
            <a href="/production-lots/<?= $lot['id'] ?>" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Printable Official Lot Release Document -->
    <div class="card shadow-sm border p-4 bg-white" style="max-width: 900px; margin: 0 auto;">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h2 class="h4 fw-bold text-primary mb-1">PLEXIQ LIMS &bull; QUALITY ASSURANCE</h2>
                <div class="text-muted small">FORMAL LOT DISPOSITION & RELEASE CERTIFICATE</div>
            </div>
            <div class="text-end">
                <span class="badge bg-<?= $lot['disposition'] === 'Released' ? 'success' : 'secondary' ?> fs-5 px-3 py-2">
                    <?= strtoupper($lot['disposition']) ?>
                </span>
                <div class="small text-muted mt-1">Generated: <?= date('Y-m-d H:i') ?> UTC</div>
            </div>
        </div>

        <!-- Lot Header Details -->
        <div class="row mb-4">
            <div class="col-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th width="140" class="text-muted">Production Lot:</th><td><strong class="fs-6"><?= htmlspecialchars($lot['lot_number']) ?></strong></td></tr>
                    <tr><th class="text-muted">ERP Material / Batch:</th><td><code><?= htmlspecialchars($lot['erp_batch_number'] ?? '-') ?></code></td></tr>
                    <tr><th class="text-muted">Product Name:</th><td><strong><?= htmlspecialchars($lot['product_name']) ?></strong></td></tr>
                    <tr><th class="text-muted">Product Code:</th><td><?= htmlspecialchars($lot['product_code']) ?></td></tr>
                </table>
            </div>
            <div class="col-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th width="140" class="text-muted">Monograph Spec:</th><td><span class="badge bg-primary">Spec <?= htmlspecialchars($lot['spec_code'] ?? 'SPEC') ?> v<?= htmlspecialchars((string)($lot['spec_version_number'] ?? '1')) ?>.0</span></td></tr>
                    <tr><th class="text-muted">Lot Size:</th><td><?= $lot['lot_size'] !== null ? htmlspecialchars((string)$lot['lot_size']) . ' ' . htmlspecialchars($lot['lot_unit']) : '-' ?></td></tr>
                    <tr><th class="text-muted">Mfg / Expiry Date:</th><td><?= $lot['manufacturing_date'] ?? '-' ?> &nbsp;/&nbsp; <?= $lot['expiry_date'] ?? '-' ?></td></tr>
                    <tr><th class="text-muted">ERP Sync Status:</th><td><?= htmlspecialchars($lot['erp_sync_status'] ?? 'Pending') ?></td></tr>
                </table>
            </div>
        </div>

        <!-- Testing Summary Across Samples -->
        <h5 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-clipboard-data me-2"></i>Quality Testing & Sample Compliance</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Sample Code</th>
                        <th>Stage</th>
                        <th>Received Date</th>
                        <th>Tests Assigned</th>
                        <th>OOS Records</th>
                        <th>Compliance Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($samples)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No samples recorded for this lot.</td></tr>
                    <?php else: ?>
                        <?php foreach ($samples as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['sample_code']) ?></strong></td>
                                <td><?= htmlspecialchars($s['sample_stage'] ?? '-') ?></td>
                                <td><?= $s['received_date'] ?></td>
                                <td><?= $s['completed_tests'] ?> / <?= $s['total_tests'] ?> Completed</td>
                                <td>
                                    <?php if ((int)$s['open_oos_count'] > 0): ?>
                                        <span class="badge bg-danger"><?= $s['open_oos_count'] ?> Open OOS</span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="bi bi-check"></i> 0 Open</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $s['status'] === 'Approved' || $s['status'] === 'COA Released' ? 'success' : 'secondary' ?>">
                                        <?= $s['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 21 CFR Part 11 Electronic Signature Box -->
        <div class="border rounded p-3 bg-light mb-2">
            <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-shield-lock me-1"></i>21 CFR Part 11 & ALCOA+ Electronic Signature Manifestation</h6>
            <div class="row">
                <div class="col-8">
                    <p class="small text-muted mb-1">
                        "I hereby certify that all manufacturing and analytical data for Lot <strong><?= htmlspecialchars($lot['lot_number']) ?></strong> have been reviewed. All required analyses meet approved specification limits and regulatory standards."
                    </p>
                    <div class="small"><strong>Justification / Disposition Reason:</strong> <?= htmlspecialchars($lot['disposition_reason'] ?? 'Pending disposition authorization') ?></div>
                    <div class="small text-muted mt-1">Signature Hash (SHA-256 HMAC): <code class="text-break"><?= htmlspecialchars($lot['disposition_signature_hash'] ?? 'UNSIGNED') ?></code></div>
                </div>
                <div class="col-4 text-end border-start">
                    <div class="small text-muted">Authorized Quality Approver:</div>
                    <div class="fw-bold fs-6 mt-1"><?= htmlspecialchars($lot['disposition_by_name'] ?? 'Pending Signature') ?></div>
                    <div class="small text-muted"><?= $lot['disposition_at'] ? date('Y-m-d H:i:s', strtotime($lot['disposition_at'])) . ' UTC' : '-' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
