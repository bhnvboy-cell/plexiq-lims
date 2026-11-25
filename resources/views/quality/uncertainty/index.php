<?php
$title = 'ISO/IEC 17025 Measurement Uncertainty Budgets';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Measurement Uncertainty Budgets (ISO/IEC 17025)</h1>
            <p class="text-muted mb-0">GUM / Eurachem Guide Quantifying Uncertainty in Analytical Measurement</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBudgetModal">
                <i class="bi bi-calculator"></i> Build Uncertainty Budget
            </button>
        </div>
    </div>

    <!-- Master List -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Validated Test Uncertainty Budgets</span>
            <span class="badge bg-secondary"><?= count($budgets) ?> budgets</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Test Method</th>
                            <th>Budget Name</th>
                            <th>Combined $u_c$</th>
                            <th>Coverage Factor ($k$)</th>
                            <th>Expanded Uncertainty ($U$)</th>
                            <th>Components</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($budgets)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No uncertainty budgets configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($budgets as $b): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($b['test_name']) ?></strong> (<?= htmlspecialchars($b['test_code']) ?>)</td>
                                    <td><?= htmlspecialchars($b['name']) ?></td>
                                    <td><code>&plusmn; <?= round((float)$b['combined_uncertainty'], 4) ?> <?= htmlspecialchars($b['unit'] ?? '') ?></code></td>
                                    <td>k = <?= htmlspecialchars($b['coverage_factor_k']) ?> (95.45%)</td>
                                    <td><strong class="text-primary">&plusmn; <?= round((float)$b['expanded_uncertainty'], 4) ?> <?= htmlspecialchars($b['unit'] ?? '') ?></strong></td>
                                    <td><span class="badge bg-info text-dark"><?= $b['component_count'] ?> sources</span></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Build Uncertainty Budget -->
<div class="modal fade" id="newBudgetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/uncertainty" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Measurement Uncertainty Budget (GUM Model)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Analytical Test</label>
                        <select name="test_id" class="form-select" required>
                            <option value="">-- Select Test --</option>
                            <?php foreach ($tests as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['test_name']) ?> (<?= htmlspecialchars($t['test_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Budget Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Assay Potency Uncertainty Budget">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Measurement Unit</label>
                        <input type="text" name="unit" class="form-control" value="%" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Coverage Factor k</label>
                        <input type="number" name="k_factor" class="form-control" step="0.01" value="2.00">
                        <small class="text-muted">k=2.00 corresponds to 95.45% confidence</small>
                    </div>
                </div>

                <h6 class="fw-bold mt-3">Uncertainty Components (Type A & Type B Sources)</h6>
                <p class="text-muted small">Enter component sources below. Standard divisors will be applied automatically (Rectangular: &radic;3, Triangular: &radic;6, Normal: 2).</p>

                <div id="componentsContainer">
                    <div class="p-3 bg-light border rounded mb-2">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="small fw-bold">Source Name</label>
                                <input type="text" name="components[0][name]" class="form-control form-control-sm" value="Analytical Balance Weighing Repeatability" required>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">Type</label>
                                <select name="components[0][type]" class="form-select form-select-sm">
                                    <option value="Type_A">Type A</option>
                                    <option value="Type_B" selected>Type B</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Distribution</label>
                                <select name="components[0][distribution]" class="form-select form-select-sm">
                                    <option value="Normal">Normal (k=2)</option>
                                    <option value="Rectangular" selected>Rectangular (&radic;3)</option>
                                    <option value="Triangular">Triangular (&radic;6)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Value (&plusmn;)</label>
                                <input type="number" step="0.0001" name="components[0][value]" class="form-control form-control-sm" value="0.05" required>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 bg-light border rounded mb-2">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="small fw-bold">Source Name</label>
                                <input type="text" name="components[1][name]" class="form-control form-control-sm" value="Volumetric Flask Calibration Tolerance" required>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">Type</label>
                                <select name="components[1][type]" class="form-select form-select-sm">
                                    <option value="Type_B" selected>Type B</option>
                                    <option value="Type_A">Type A</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Distribution</label>
                                <select name="components[1][distribution]" class="form-select form-select-sm">
                                    <option value="Triangular" selected>Triangular (&radic;6)</option>
                                    <option value="Rectangular">Rectangular (&radic;3)</option>
                                    <option value="Normal">Normal (k=2)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold">Value (&plusmn;)</label>
                                <input type="number" step="0.0001" name="components[1][value]" class="form-control form-control-sm" value="0.08" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Calculate & Save Budget</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
