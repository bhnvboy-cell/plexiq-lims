<?php
$title = 'ICH Q1E Stability Regression & Shelf-Life Forecasting';
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">ICH Q1E Stability Regression & Shelf-Life Forecasting</h1>
            <p class="text-muted mb-0">Statistical Evaluation of Stability Data (95% Confidence Interval Extrapolation)</p>
        </div>
        <div>
            <a href="/stability" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Stability Studies</a>
        </div>
    </div>

    <!-- Live Interactive Stability Forecaster -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white fw-bold">ICH Q1E Shelf-Life Calculator</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Timepoint Data (JSON Array of Months & Measured Values)</label>
                    <textarea id="stabData" class="form-control font-monospace" rows="4">[
  {"month": 0, "value": 100.4},
  {"month": 3, "value": 99.8},
  {"month": 6, "value": 99.1},
  {"month": 9, "value": 98.4},
  {"month": 12, "value": 97.9},
  {"month": 18, "value": 96.8},
  {"month": 24, "value": 95.9}
]</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Lower Spec Limit (API Assay %)</label>
                    <input type="number" step="0.1" id="stabLower" class="form-control" value="90.0">
                    <label class="form-label fw-bold mt-2">Upper Spec Limit (Impurity %)</label>
                    <input type="number" step="0.1" id="stabUpper" class="form-control" placeholder="e.g. 0.5 (optional)">
                </div>
                <div class="col-md-4 d-flex flex-column justify-content-center">
                    <button class="btn btn-success btn-lg mb-2" onclick="runRegression()"><i class="bi bi-graph-up"></i> Compute Shelf-Life</button>
                    <small class="text-muted">Calculates intersection of 95% one-sided confidence boundary with specification limits according to ICH Q1E Section 2.2.</small>
                </div>
            </div>

            <!-- Output Box -->
            <div id="regressionResults" class="mt-4 alert alert-light border d-none">
                <div class="row">
                    <div class="col-md-4 text-center border-end">
                        <small class="text-muted d-block">Predicted Shelf-Life (95% CI)</small>
                        <span id="resMonths" class="display-6 fw-bold text-success">--</span>
                        <small id="resGoverning" class="text-muted d-block"></small>
                    </div>
                    <div class="col-md-4 text-center border-end">
                        <small class="text-muted d-block">Degradation Rate (Slope)</small>
                        <span id="resSlope" class="fs-4 fw-bold text-primary">--</span>
                        <small class="text-muted d-block">% per month</small>
                    </div>
                    <div class="col-md-4 text-center">
                        <small class="text-muted d-block">Goodness of Fit ($R^2$)</small>
                        <span id="resR2" class="fs-4 fw-bold text-dark">--</span>
                        <small id="resSe" class="text-muted d-block"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stability Studies Reference Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light fw-bold">Active ICH Stability Studies</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Study Protocol</th>
                            <th>Product</th>
                            <th>Storage Condition</th>
                            <th>Orientation</th>
                            <th>Timepoints</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($studies)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No active stability protocols found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($studies as $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($s['study_code']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($s['product_name'] ?? 'Product') ?></strong></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['condition_name'] ?? '25°C / 60% RH') ?></span></td>
                                    <td><?= htmlspecialchars($s['orientation'] ?? 'Upright') ?></td>
                                    <td><?= htmlspecialchars($s['duration_months'] ?? 24) ?> Months</td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($s['status'] ?? 'Active') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function runRegression() {
    const rawData = document.getElementById('stabData').value;
    const lower = document.getElementById('stabLower').value;
    const upper = document.getElementById('stabUpper').value;
    const outBox = document.getElementById('regressionResults');

    fetch('/stability/regression/evaluate', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
        body: new URLSearchParams({data: rawData, lower_spec_limit: lower, upper_spec_limit: upper})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.regression) {
            outBox.classList.remove('d-none');
            const reg = res.regression;
            document.getElementById('resMonths').innerText = (reg.predicted_shelf_life_months ? reg.predicted_shelf_life_months + ' Months' : '> 120 Months');
            document.getElementById('resGoverning').innerText = 'Governing: ' + reg.governing_spec;
            document.getElementById('resSlope').innerText = reg.slope + '% / mo';
            document.getElementById('resR2').innerText = reg.r_squared;
            document.getElementById('resSe').innerText = 'Residual Std Error: ' + reg.residual_std_error;
        } else {
            alert('Regression error: ' + (res.error || 'Failed to compute'));
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}
</script>
