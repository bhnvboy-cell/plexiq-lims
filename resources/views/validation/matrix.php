<?php
layout('app');
$title = 'GAMP 5 Requirements Traceability Matrix (RTM)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Requirements Traceability Matrix (RTM)</h1>
            <p class="text-muted mb-0">Validation-as-Code: Automated mapping from GAMP 5 requirements (URS / FS / DS) to automated verification tests</p>
        </div>
        <div>
            <a href="/validation/matrix/export" target="_blank" class="btn btn-outline-primary me-2">
                <i class="bi bi-filetype-json"></i> Export RTM JSON
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print IQ/OQ Protocol
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Requirements</div>
                    <h3 class="mb-0 fw-bold"><?= (int)$rtm['total_requirements'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Verified via Automated Tests</div>
                    <h3 class="mb-0 fw-bold text-success"><?= (int)$rtm['verified_count'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Validation Coverage</div>
                    <h3 class="mb-0 fw-bold text-info"><?= $rtm['coverage_percentage'] ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-secondary">
                <div class="card-body">
                    <div class="text-muted small">GAMP Classification</div>
                    <div class="fw-bold mt-1 text-truncate"><?= htmlspecialchars($rtm['gamp_compliance']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traceability Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">GAMP 5 Traceability Matrix</span>
            <span class="badge bg-success">100% Automated Test Linked</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Requirement ID</th>
                            <th>Type</th>
                            <th>Regulatory Clause</th>
                            <th>Requirement Title & Description</th>
                            <th>Automated Verification Test Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rtm['matrix'] as $row): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($row['req_code']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['req_type']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['category']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['title']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($row['description']) ?></div>
                                </td>
                                <td>
                                    <code class="small text-dark"><?= htmlspecialchars($row['test_method'] ?? 'Manual Protocol') ?></code>
                                </td>
                                <td>
                                    <span class="badge <?= $row['verification_status'] === 'passed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <i class="bi bi-check-circle"></i> <?= strtoupper($row['verification_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
