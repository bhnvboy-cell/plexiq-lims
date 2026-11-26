<?php
layout('app');
$title = 'ERP Reconciliation & Usage Decisions';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-arrow-repeat me-2"></i>ERP Quality Reconciliation</h1>
            <p class="text-muted mb-0">Bi-directional Verification of LIMS Dispositions vs ERP Usage Decisions (SAP S/4HANA, Oracle Cloud, Dynamics 365)</p>
        </div>
        <div>
            <a href="/erp" class="btn btn-outline-secondary me-2"><i class="bi bi-sliders me-1"></i> Connectors</a>
            <a href="/production-lots" class="btn btn-primary"><i class="bi bi-boxes me-1"></i> Production Lots</a>
        </div>
    </div>

    <!-- Active Connectors Summary -->
    <div class="row mb-4">
        <?php foreach ($connectors as $conn): ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($conn['name']) ?></h6>
                            <span class="badge bg-<?= $conn['status'] === 'Active' ? 'success' : 'secondary' ?>"><?= $conn['status'] ?></span>
                        </div>
                        <div class="small text-muted mb-1">Type: <code><?= htmlspecialchars($conn['type']) ?></code></div>
                        <div class="small text-muted">Endpoint: <code><?= htmlspecialchars($conn['endpoint_url']) ?></code></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Reconciliation Registry Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Lot Dispositions & ERP Usage Decisions</span>
            <span class="badge bg-secondary"><?= count($lots) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Lot Number</th>
                            <th>Product</th>
                            <th>ERP Batch Ref</th>
                            <th>LIMS Disposition</th>
                            <th>Disposition By</th>
                            <th>ERP Sync Status</th>
                            <th>Idempotency Key</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lots)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No production lots found for ERP reconciliation.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lots as $lot): ?>
                                <tr>
                                    <td>
                                        <a href="/production-lots/<?= $lot['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($lot['lot_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($lot['product_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($lot['product_code']) ?></small>
                                    </td>
                                    <td><code><?= htmlspecialchars($lot['erp_batch_number'] ?? '-') ?></code></td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($lot['disposition']) {
                                            'Released' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                            'On Hold' => 'bg-warning text-dark',
                                            'Conditionally Released' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $lot['disposition'] ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($lot['disposition_by_name'] ?? 'Pending') ?>
                                        <?php if (!empty($lot['disposition_at'])): ?>
                                            <div class="small text-muted"><?= date('M d, H:i', strtotime($lot['disposition_at'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lot['erp_sync_status'] === 'Synced'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Synced</span>
                                        <?php elseif ($lot['erp_sync_status'] === 'Failed'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted text-break"><code><?= htmlspecialchars($lot['erp_sync_idempotency_key'] ?? 'N/A') ?></code></small>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="/production-lots/<?= $lot['id'] ?>/sync-erp" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Post or retry Usage Decision to ERP">
                                                <i class="bi bi-arrow-repeat me-1"></i> Sync / Retry
                                            </button>
                                        </form>
                                        <a href="/production-lots/<?= $lot['id'] ?>/release-report" class="btn btn-sm btn-outline-secondary ms-1">
                                            <i class="bi bi-file-earmark-text"></i> Report
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
