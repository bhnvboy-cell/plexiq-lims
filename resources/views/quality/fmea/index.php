<?php
$title = 'FMEA Risk Engine (Failure Mode & Effects Analysis)';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">FMEA Risk Engine (Failure Mode & Effects Analysis)</h1>
            <p class="text-muted mb-0">ICH Q9 Quality Risk Management & RPN Prioritization (RPN = Severity &times; Occurrence &times; Detection)</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newFmeaModal">
                <i class="bi bi-plus-lg"></i> New Risk Assessment
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Risk Assessment Register (Ranked by Initial RPN)</span>
            <span class="badge bg-secondary"><?= count($assessments) ?> assessments</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Process Step / Entity</th>
                            <th>Failure Mode & Effect</th>
                            <th>Potential Cause</th>
                            <th class="text-center">S</th>
                            <th class="text-center">O</th>
                            <th class="text-center">D</th>
                            <th class="text-center">Initial RPN</th>
                            <th>Mitigation Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assessments)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No FMEA risk assessments logged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assessments as $a): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($a['process_step']) ?></strong><br>
                                        <small class="badge bg-secondary text-uppercase"><?= htmlspecialchars($a['entity_type']) ?> #<?= $a['entity_id'] ?></small>
                                    </td>
                                    <td>
                                        <strong>Mode:</strong> <?= htmlspecialchars($a['failure_mode']) ?><br>
                                        <small class="text-muted"><strong>Effect:</strong> <?= htmlspecialchars($a['failure_effect']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($a['failure_cause']) ?></td>
                                    <td class="text-center fw-bold"><?= $a['severity'] ?></td>
                                    <td class="text-center fw-bold"><?= $a['occurrence'] ?></td>
                                    <td class="text-center fw-bold"><?= $a['detection'] ?></td>
                                    <td class="text-center">
                                        <?php $rpn = (int)$a['rpn'];
                                        $rpnClass = $rpn >= 200 ? 'bg-danger' : ($rpn >= 100 ? 'bg-warning text-dark' : 'bg-success'); ?>
                                        <span class="badge <?= $rpnClass ?> fs-6"><?= $rpn ?></span>
                                    </td>
                                    <td style="max-width: 250px;"><?= htmlspecialchars($a['mitigation_plan'] ?? 'None defined') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New FMEA -->
<div class="modal fade" id="newFmeaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/fmea" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Perform FMEA Risk Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Scope / Entity Type</label>
                        <select name="entity_type" class="form-select">
                            <option value="method">Test Method</option>
                            <option value="instrument">Instrument / Equipment</option>
                            <option value="process">Sampling / Prep Process</option>
                            <option value="change_control">Change Control</option>
                            <option value="deviation">Deviation</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Process Step</label>
                        <input type="text" name="process_step" class="form-control" required placeholder="e.g. Sample Pipetting / Dilution">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Potential Failure Mode</label>
                    <input type="text" name="failure_mode" class="form-control" required placeholder="e.g. Inaccurate micropipette dispensing volume">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Potential Failure Effect</label>
                        <textarea name="failure_effect" class="form-control" rows="2" required placeholder="e.g. False OOS potency result causing batch rejection"></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Potential Root Cause</label>
                        <textarea name="failure_cause" class="form-control" rows="2" required placeholder="e.g. Calibration drift or tip seating misalignment"></textarea>
                    </div>
                </div>
                <div class="row p-3 bg-light border rounded mb-3">
                    <div class="col-md-4 mb-2">
                        <label class="form-label fw-bold">Severity (1-10)</label>
                        <input type="number" name="severity" class="form-control" min="1" max="10" value="8" required>
                        <small class="text-muted">1 = None, 10 = Hazardous</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label fw-bold">Occurrence (1-10)</label>
                        <input type="number" name="occurrence" class="form-control" min="1" max="10" value="4" required>
                        <small class="text-muted">1 = Remote, 10 = Very High</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label fw-bold">Detection (1-10)</label>
                        <input type="number" name="detection" class="form-control" min="1" max="10" value="5" required>
                        <small class="text-muted">1 = Certain, 10 = Undetectable</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mitigation / Preventive Action Plan</label>
                    <textarea name="mitigation_plan" class="form-control" rows="2" placeholder="e.g. Implement daily gravimetric pipette verification and annual accredited calibration..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Calculate RPN & Save</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
