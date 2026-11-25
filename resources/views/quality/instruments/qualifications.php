<?php
$title = 'Equipment Lifecycle: DQ/IQ/OQ/PQ & Out-of-Calibration Impact';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Equipment Lifecycle & Qualifications</h1>
            <p class="text-muted mb-0">Design, Installation, Operational, and Performance Qualifications (DQ/IQ/OQ/PQ) & Calibration Impact</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newQualModal">
                <i class="bi bi-patch-check"></i> Record Qualification
            </button>
        </div>
    </div>

    <!-- Qualifications Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Validation & Qualification Protocols</span>
            <span class="badge bg-secondary"><?= count($qualifications) ?> protocols</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Execution Date</th>
                            <th>Instrument</th>
                            <th>Phase</th>
                            <th>Protocol Code</th>
                            <th>Executed By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($qualifications)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No qualification protocols recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($qualifications as $q): ?>
                                <tr>
                                    <td><?= htmlspecialchars($q['execution_date']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($q['instrument_name']) ?></strong><br>
                                        <code><?= htmlspecialchars($q['instrument_code']) ?></code>
                                    </td>
                                    <td><span class="badge bg-info text-dark fs-6"><?= htmlspecialchars($q['phase']) ?></span></td>
                                    <td><code><?= htmlspecialchars($q['protocol_code']) ?></code></td>
                                    <td><?= htmlspecialchars($q['executed_by_name'] ?? 'Analyst') ?></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($q['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Calibration Impact Assessments -->
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white fw-bold">Out-of-Calibration Impact Assessments (Retrospective GxP Review)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Evaluation Date</th>
                            <th>Instrument</th>
                            <th>Failed Date</th>
                            <th>Tests Affected</th>
                            <th>Risk Level</th>
                            <th>Disposition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($impacts)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">No instruments currently flagged for out-of-calibration impact review.</td></tr>
                        <?php else: ?>
                            <?php foreach ($impacts as $imp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($imp['evaluated_at']) ?></td>
                                    <td><strong><?= htmlspecialchars($imp['instrument_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($imp['failed_date']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $imp['affected_tests_count'] ?> samples/tests</span></td>
                                    <td><span class="badge bg-danger"><?= htmlspecialchars($imp['risk_level']) ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($imp['disposition']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Qualification -->
<div class="modal fade" id="newQualModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/instruments/qualifications" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Instrument Qualification Protocol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Instrument</label>
                    <select name="instrument_id" class="form-select" required>
                        <option value="">-- Select Instrument --</option>
                        <?php foreach ($instruments as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['instrument_name']) ?> (<?= htmlspecialchars($inst['instrument_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Qualification Phase</label>
                        <select name="phase" class="form-select">
                            <option value="DQ">DQ (Design Qualification)</option>
                            <option value="IQ">IQ (Installation Qualification)</option>
                            <option value="OQ" selected>OQ (Operational Qualification)</option>
                            <option value="PQ">PQ (Performance Qualification)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Execution Date</label>
                        <input type="date" name="execution_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Protocol Document Code</label>
                    <input type="text" name="protocol_code" class="form-control font-monospace" required placeholder="e.g. VAL-OQ-HPLC-001">
                </div>
                <div class="mb-3">
                    <label class="form-label">Qualification Outcome</label>
                    <select name="status" class="form-select">
                        <option value="Passed">Passed (All Acceptance Criteria Met)</option>
                        <option value="Conditional">Conditional (Deviation Open)</option>
                        <option value="Failed">Failed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Protocol</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
