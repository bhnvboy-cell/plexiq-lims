<?php $title = 'Enter Result'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-pencil-square"></i> Enter Test Result</h2>
    <a href="/tests/pending" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">Test Information</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Sample Code</th><td><?= htmlspecialchars($test['sample_code']) ?></td></tr>
                    <tr><th>Batch Number</th><td><?= htmlspecialchars($test['batch_number'] ?? '-') ?></td></tr>
                    <tr><th>Test</th><td><?= htmlspecialchars($test['test_code']) ?> - <?= htmlspecialchars($test['test_name']) ?></td></tr>
                    <tr><th>Method</th><td><?= htmlspecialchars($test['method_name'] ?? '-') ?></td></tr>
                    <tr><th>Specification</th><td><?= htmlspecialchars($test['spec_limit_text'] ?? ($test['min_spec_limit'] . ' - ' . $test['max_spec_limit'] . ' ' . $test['unit_code'])) ?></td></tr>
                    <tr><th>Unit</th><td><?= htmlspecialchars($test['unit_name'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header">Enter Result</div>
            <div class="card-body">
                <form method="POST" action="/tests/<?= $test['id'] ?>/result">
                    <?= csrf_field() ?>

                    <!-- Instrument Calibration Gating (ISO 17025) -->
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-cpu"></i> Test Instrument</label>
                        <select name="instrument_id" id="instrumentSelect" class="form-select">
                            <option value="">— Select Laboratory Instrument (Optional) —</option>
                            <?php foreach (($instruments ?? []) as $inst): ?>
                                <?php
                                    $isCalibrated = true;
                                    $calLabel = 'Calibrated';
                                    if (!empty($inst['next_calibration_date'])) {
                                        if (strtotime($inst['next_calibration_date']) < strtotime(date('Y-m-d'))) {
                                            $isCalibrated = false;
                                            $calLabel = 'EXPIRED CAL: ' . $inst['next_calibration_date'];
                                        } else {
                                            $calLabel = 'Cal valid until ' . $inst['next_calibration_date'];
                                        }
                                    }
                                ?>
                                <option value="<?= $inst['id'] ?>" <?= ((int)($result['instrument_id'] ?? 0) === (int)$inst['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['instrument_name']) ?> (<?= htmlspecialchars($inst['instrument_code']) ?>) [<?= $calLabel ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Instruments with overdue calibrations will be gated according to ISO 17025 compliance.</small>
                    </div>

                    <!-- Multi-Replicate Statistical Calculator -->
                    <div class="card mb-3 bg-light border-0">
                        <div class="card-body p-3">
                            <label class="form-label fw-bold"><i class="bi bi-calculator"></i> Replicate Measurements &amp; %RSD Calculator</label>
                            <div class="input-group mb-2">
                                <input type="text" id="replicatesRaw" name="replicates_raw" class="form-control" placeholder="e.g. 10.12, 10.15, 10.14">
                                <button type="button" class="btn btn-outline-secondary" onclick="calcReplicates()">Calculate Stats</button>
                            </div>
                            <small class="text-muted d-block mb-1">Enter comma- or space-separated values to auto-compute Mean, SD, and %RSD.</small>
                            <div id="replicateStats" class="small text-primary fw-bold" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Result Value (<?= htmlspecialchars($test['unit_code'] ?? '') ?>)</label>
                        <input type="number" step="any" name="result_value" id="resultValue" class="form-control form-control-lg"
                               value="<?= htmlspecialchars((string)($result['result_value'] ?? '')) ?>"
                               <?= $test['unit_code'] === '%' || $test['min_spec_limit'] !== null ? '' : 'disabled' ?>>
                        <small class="text-muted">Enter numeric value for quantitative tests</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Result Text</label>
                        <textarea name="result_text" class="form-control" rows="2"
                        <?= $test['unit_code'] === '%' || $test['min_spec_limit'] !== null ? 'disabled' : '' ?>
                        ><?= htmlspecialchars($result['result_text'] ?? '') ?></textarea>
                        <small class="text-muted">Enter text result for qualitative tests (e.g., Conforms)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($result['remarks'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Measurement Uncertainty</label>
                            <input type="number" step="any" name="uncertainty" class="form-control"
                                   value="<?= htmlspecialchars((string)($result['uncertainty'] ?? '')) ?>"
                                   placeholder="± value">
                            <small class="text-muted">Expanded uncertainty (same unit as result)</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Coverage Factor (k)</label>
                            <input type="number" step="any" name="k_factor" class="form-control"
                                   value="<?= htmlspecialchars((string)($result['k_factor'] ?? '')) ?>"
                                   placeholder="e.g. 2.0">
                            <small class="text-muted">Default 2.0 for 95% confidence</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confidence Interval</label>
                            <select name="confidence_interval" class="form-select">
                                <option value="">— Select —</option>
                                <option value="95%" <?= ($result['confidence_interval'] ?? '') === '95%' ? 'selected' : '' ?>>95%</option>
                                <option value="99%" <?= ($result['confidence_interval'] ?? '') === '99%' ? 'selected' : '' ?>>99%</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Replicate Count</label>
                        <input type="number" min="1" name="replicate_count" class="form-control"
                               value="<?= htmlspecialchars((string)($result['replicate_count'] ?? 1)) ?>">
                        <small class="text-muted">Number of replicate measurements</small>
                    </div>
                    <?php if (!empty($result['uncertainty'])): ?>
                    <div class="alert alert-info">
                        <strong>Reported result:</strong> <?= htmlspecialchars((string)($result['result_value'] ?? '')) ?> ± <?= htmlspecialchars((string)$result['uncertainty']) ?>
                        <?= htmlspecialchars($test['unit_code'] ?? '') ?> (k = <?= htmlspecialchars((string)($result['k_factor'] ?? 2)) ?>, <?= htmlspecialchars((string)($result['confidence_interval'] ?? '95%')) ?>)
                    </div>
                    <?php endif; ?>
                    <?php if ($test['min_spec_limit'] !== null && $test['max_spec_limit'] !== null): ?>
                    <div class="alert alert-info">
                        <strong>Auto-validation:</strong> Value must be between <?= $test['min_spec_limit'] ?> and <?= $test['max_spec_limit'] ?> <?= $test['unit_code'] ?? '' ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <?php if (!empty($nextTestId)): ?>
                        <input type="hidden" name="_next_test_id" value="<?= $nextTestId ?>">
                        <button type="submit" class="btn btn-success btn-lg flex-fill"><i class="bi bi-save"></i> Save &amp; Next</button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-lg flex-fill"><i class="bi bi-save"></i> Save Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($revisions)): ?>
<div class="card">
    <div class="card-header">Revision History</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Revision</th><th>Value</th><th>Changed By</th><th>Date</th><th>Reason</th></tr></thead>
            <tbody>
                <?php foreach ($revisions as $rev): ?>
                <tr>
                    <td>#<?= $rev['revision'] ?></td>
                    <td><?= htmlspecialchars((string)($rev['result_value'] ?? $rev['result_text'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars($rev['changed_by_name'] ?? '-') ?></td>
                    <td><?= $rev['changed_at'] ?></td>
                    <td><?= htmlspecialchars($rev['change_reason'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function calcReplicates() {
    const raw = document.getElementById('replicatesRaw').value.trim();
    if (!raw) return;
    const nums = raw.split(/[,;\s]+/).map(parseFloat).filter(n => !isNaN(n));
    if (nums.length < 2) {
        alert('Please enter at least 2 numeric values.');
        return;
    }
    const n = nums.length;
    const mean = nums.reduce((a, b) => a + b, 0) / n;
    const variance = nums.reduce((acc, val) => acc + Math.pow(val - mean, 2), 0) / (n - 1);
    const sd = Math.sqrt(variance);
    const rsd = mean !== 0 ? Math.abs((sd / mean) * 100) : 0;
    
    document.getElementById('resultValue').value = mean.toFixed(4);
    const repCountInput = document.querySelector('input[name="replicate_count"]');
    if (repCountInput) repCountInput.value = n;
    const uncInput = document.querySelector('input[name="uncertainty"]');
    if (uncInput) uncInput.value = (sd * 2.0).toFixed(4);

    const statsDiv = document.getElementById('replicateStats');
    statsDiv.style.display = 'block';
    statsDiv.innerHTML = `<i class="bi bi-check-circle"></i> Calculated (n=${n}): Mean = ${mean.toFixed(4)}, SD = ${sd.toFixed(4)}, %RSD = ${rsd.toFixed(2)}% (Auto-filled)`;
}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
