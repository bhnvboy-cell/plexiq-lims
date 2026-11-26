<?php
$title = 'Calculation Formulas & Sandbox Engine';
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Formula Designer & Sandboxed Engine</h1>
            <p class="text-muted mb-0">AST tokenized math evaluator (zero eval), ASTM E29 Sig-Figs & Replicate Stats</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newFormulaModal">
                <i class="bi bi-plus-lg"></i> New Formula
            </button>
        </div>
    </div>

    <!-- Live Interactive Test Sandbox -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white fw-bold">Interactive Sandbox Evaluator</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Mathematical Expression</label>
                    <input type="text" id="sandboxExpr" class="form-control font-monospace" placeholder="(A - B) * 100 / SampleWeight" value="(Gross - Tare) * 100 / SampleWeight">
                    <small class="text-muted">Allowed operators: + - * / ^ ( ), functions: sqrt, abs, min, max, avg, stdev, round</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Variables (JSON Dictionary)</label>
                    <input type="text" id="sandboxVars" class="form-control font-monospace" value='{"Gross": 15.452, "Tare": 10.231, "SampleWeight": 5.221}'>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Sig-Figs</label>
                    <input type="number" id="sandboxSigFigs" class="form-control" value="4">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-success w-100" onclick="runSandboxTest()"><i class="bi bi-play-fill"></i> Test</button>
                </div>
            </div>
            <div id="sandboxOutput" class="mt-3 alert alert-light border font-monospace d-none"></div>
        </div>
    </div>

    <!-- Formulas Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light fw-bold">Configured Calculation Formulas</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Test Method</th>
                            <th>Formula Name</th>
                            <th>Expression</th>
                            <th>Sig-Figs</th>
                            <th>Rounding Mode</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($formulas)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No custom formulas created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($formulas as $f): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($f['test_name']) ?></strong> (<?= htmlspecialchars($f['test_code']) ?>)</td>
                                    <td><?= htmlspecialchars($f['name']) ?></td>
                                    <td><code><?= htmlspecialchars($f['expression']) ?></code></td>
                                    <td><span class="badge bg-secondary"><?= $f['sig_figs'] ?> digits</span></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($f['rounding_mode']) ?></span></td>
                                    <td><span class="badge bg-success">Active (v<?= $f['version'] ?>)</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Formula -->
<div class="modal fade" id="newFormulaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/formulas" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Calculation Formula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Associated Test</label>
                    <select name="test_id" class="form-select" required>
                        <option value="">-- Select Test --</option>
                        <?php foreach ($tests as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['test_name']) ?> (<?= htmlspecialchars($t['test_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Formula Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Moisture Content Assay">
                </div>
                <div class="mb-3">
                    <label class="form-label">Expression</label>
                    <input type="text" name="expression" class="form-control font-monospace" required placeholder="e.g. (Gross - Tare) * 100 / SampleWeight">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Significant Figures (ASTM E29)</label>
                        <input type="number" name="sig_figs" class="form-control" value="3" min="1" max="10">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rounding Rule</label>
                        <select name="rounding_mode" class="form-select">
                            <option value="half_even">Round Half to Even (ASTM E29 Banker's)</option>
                            <option value="half_up">Round Half Up</option>
                            <option value="truncate">Truncate</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Formula</button>
            </div>
        </form>
    </div>
</div>

<script>
function runSandboxTest() {
    const expr = document.getElementById('sandboxExpr').value;
    const vars = document.getElementById('sandboxVars').value;
    const sigFigs = document.getElementById('sandboxSigFigs').value;
    const output = document.getElementById('sandboxOutput');

    fetch('/formulas/test-eval', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
        body: new URLSearchParams({expression: expr, variables: vars, sig_figs: sigFigs})
    })
    .then(r => r.json())
    .then(data => {
        output.classList.remove('d-none');
        if (data.success) {
            output.className = 'mt-3 alert alert-success font-monospace';
            output.innerHTML = `<strong>Result:</strong> ${data.result}`;
        } else {
            output.className = 'mt-3 alert alert-danger font-monospace';
            output.innerHTML = `<strong>Error:</strong> ${data.error}`;
        }
    })
    .catch(err => {
        output.classList.remove('d-none');
        output.className = 'mt-3 alert alert-danger font-monospace';
        output.innerHTML = `<strong>Network Error:</strong> ${err.message}`;
    });
}
</script>
