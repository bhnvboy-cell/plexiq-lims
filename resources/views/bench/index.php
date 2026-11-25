<?php
layout('app');
$title = 'Bench Mode - High-Throughput Tablet & Touch Result Entry';
?>

<div class="container-fluid py-3">
    <!-- Bench Mode Header -->
    <div class="card bg-dark text-white shadow-sm mb-3 border-0">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-0"><i class="bi bi-tablet"></i> Bench Mode & Quick Grid</h2>
                <small class="text-secondary">Keyboard-First (Tab / Enter) & Tablet Touchscreen Optimized</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-secondary text-white border-0"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="barcodeScannerInput" class="form-control" placeholder="Scan Sample Barcode..." autofocus>
                </div>
                <span class="badge bg-success p-2"><i class="bi bi-broadcast"></i> Autosave Active</span>
            </div>
        </div>
    </div>

    <!-- Quick Results Entry Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" id="benchGrid">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">Sample Code</th>
                            <th>Sample / Product</th>
                            <th>Test Method</th>
                            <th>Specification Limit</th>
                            <th style="width: 220px;">Result Entry</th>
                            <th style="width: 130px;">Action / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tests)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">No pending tests currently assigned to this bench station.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tests as $idx => $t): ?>
                                <tr id="row-<?= $t['id'] ?>" class="<?= $t['status'] === 'completed' ? 'table-success' : '' ?>">
                                    <td>
                                        <code class="fw-bold fs-6"><?= htmlspecialchars($t['sample_code']) ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($t['sample_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($t['test_code']) ?></span>
                                        <?= htmlspecialchars($t['test_name']) ?>
                                    </td>
                                    <td>
                                        <?php if ($t['min_spec_limit'] !== null || $t['max_spec_limit'] !== null): ?>
                                            <code><?= $t['min_spec_limit'] ?? 'N/A' ?> &ndash; <?= $t['max_spec_limit'] ?? 'N/A' ?> <?= htmlspecialchars($t['unit_code'] ?? '') ?></code>
                                        <?php else: ?>
                                            <span class="text-muted"><?= htmlspecialchars($t['spec_limit_text'] ?? 'Conforms') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <input type="text"
                                                   class="form-control form-control-lg font-monospace fw-bold result-input"
                                                   id="input-<?= $t['id'] ?>"
                                                   data-id="<?= $t['id'] ?>"
                                                   data-min="<?= $t['min_spec_limit'] ?? '' ?>"
                                                   data-max="<?= $t['max_spec_limit'] ?? '' ?>"
                                                   value="<?= htmlspecialchars($t['result_value'] ?? $t['result_text'] ?? '') ?>"
                                                   placeholder="Enter value..."
                                                   onkeydown="handleGridKey(event, <?= $idx ?>)">
                                            <?php if ($t['unit_code']): ?>
                                                <span class="input-group-text"><?= htmlspecialchars($t['unit_code']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm px-3" onclick="saveBenchResult(<?= $t['id'] ?>)">
                                            <i class="bi bi-check-lg"></i> Save
                                        </button>
                                        <span id="badge-<?= $t['id'] ?>" class="badge mt-1 d-block <?= $t['is_within_spec'] === true ? 'bg-success' : ($t['is_within_spec'] === false ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                            <?= htmlspecialchars($t['status']) ?>
                                        </span>
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

<script>
function saveBenchResult(testId) {
    const input = document.getElementById(`input-${testId}`);
    const badge = document.getElementById(`badge-${testId}`);
    const row = document.getElementById(`row-${testId}`);
    const val = input.value.trim();

    if (!val) return;

    badge.className = 'badge mt-1 d-block bg-info text-dark';
    badge.innerText = 'Saving...';

    fetch('/bench/save-result', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `sample_test_id=${testId}&value=${encodeURIComponent(val)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (d.is_within_spec) {
                badge.className = 'badge mt-1 d-block bg-success';
                badge.innerText = 'Pass';
                row.className = 'table-success';
            } else {
                badge.className = 'badge mt-1 d-block bg-danger';
                badge.innerText = 'OOS Flagged';
                row.className = 'table-danger';
                alert('WARNING: Entered value triggered OUT OF SPECIFICATION (OOS) event!');
            }
        } else {
            badge.className = 'badge mt-1 d-block bg-danger';
            badge.innerText = 'Error';
            alert(d.error);
        }
    })
    .catch(err => {
        badge.className = 'badge mt-1 d-block bg-danger';
        badge.innerText = 'Network Err';
    });
}

function handleGridKey(e, idx) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const testId = e.target.getAttribute('data-id');
        saveBenchResult(testId);
        // Move to next input
        const inputs = document.querySelectorAll('.result-input');
        if (inputs[idx + 1]) {
            inputs[idx + 1].focus();
            inputs[idx + 1].select();
        }
    }
}
</script>
