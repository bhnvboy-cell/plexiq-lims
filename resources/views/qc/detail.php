<?php $title = e($lot['lot_number']) . ' - Quality Control'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-shield-check me-2"></i><?= e($lot['lot_number']) ?>
        <small class="text-muted fs-6 ms-2"><?= e($lot['manufacturer'] ?? 'Control Material') ?></small>
    </h4>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResultModal">
            <i class="bi bi-plus-lg"></i> Record Result
        </button>
        <a href="/qc" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<?php if ($westgard['status'] === 'violation'): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Westgard rule violation detected.</strong>
    The latest run should be rejected and the source investigated before reporting patient/sample results.
    Refer to a CAPA or deviation record if the condition persists.
</div>
<?php elseif ($westgard['status'] === 'ok' && $westgard['n'] >= 3): ?>
<div class="alert alert-success">
    <i class="bi bi-check-circle me-1"></i>
    <strong>Process in control.</strong> No Westgard rule violations across <?= $westgard['n'] ?> results.
</div>
<?php elseif ($westgard['status'] === 'insufficient'): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    At least 3 results are required before Westgard multi-rule assessment can run.
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-collection stat-icon"></i>
            <div class="stat-value"><?= $stats['n'] ?? 0 ?></div>
            <div class="stat-label">Results</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-bullseye stat-icon"></i>
            <div class="stat-value"><?= $stats['mean'] ?? '-' ?></div>
            <div class="stat-label">Measured Mean (<?= e($lot['unit']) ?>)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-arrow-left-right stat-icon"></i>
            <div class="stat-value"><?= $stats['stddev'] ?? '-' ?></div>
            <div class="stat-label">Measured SD</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-purple">
            <i class="bi bi-target stat-icon"></i>
            <div class="stat-value">
                <?= $lot['target_mean'] !== null ? e($lot['target_mean']) . ' ± ' . e($lot['target_sd'] ?? '-') : '-' ?>
            </div>
            <div class="stat-label">Target Mean ± SD</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-graph-up me-1"></i>Levey-Jennings Chart</span>
        <span class="badge bg-secondary">Mean <?= $lot['target_mean'] !== null ? e($lot['target_mean']) : 'n/a' ?> · SD <?= $lot['target_sd'] !== null ? e($lot['target_sd']) : 'n/a' ?></span>
    </div>
    <div class="card-body">
        <canvas id="ljChart" height="300"></canvas>
    </div>
</div>

<?php if ($westgard['violations']): ?>
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle me-1"></i>Westgard Violations
    </div>
    <div class="card-body">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Description</th>
                    <th>Result #</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($westgard['violations'] as $v): ?>
                <tr>
                    <td><span class="badge bg-danger"><?= e($v['label']) ?></span></td>
                    <td><?= e($v['text']) ?></td>
                    <td>#<?= $v['index'] + 1 ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <small class="text-muted mt-2 d-block">
            1₃ₛ reject · 2₂ₛ reject · R₄ₛ reject · 4₁ₛ reject · 10ₓ reject.
            A single rejected rule requires corrective action before the next run.
        </small>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-1"></i>QC Results</span>
        <span class="text-muted small">Target: <?= $lot['target_mean'] !== null ? e($lot['target_mean']) : '-' ?> ± <?= $lot['target_sd'] !== null ? e($lot['target_sd']) : '-' ?> <?= e($lot['unit']) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Entered At</th>
                    <th>Value (<?= e($lot['unit']) ?>)</th>
                    <th>Instrument</th>
                    <th>Entered By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($r['entered_at'])) ?></td>
                    <td>
                        <span class="fw-bold <?= $lot['target_mean'] !== null && $lot['target_sd'] !== null && (abs((float)$r['result_value'] - (float)$lot['target_mean']) > 3 * (float)$lot['target_sd']) ? 'text-danger' : 'text-success' ?>">
                            <?= e($r['result_value']) ?>
                        </span>
                    </td>
                    <td><?= e($r['instrument_name'] ?? '-') ?></td>
                    <td><?= e($r['entered_by_name'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= e($r['notes'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($results)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No QC results recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/qc/<?= $lot['id'] ?>/results">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Record QC Result — <?= e($lot['lot_number']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Result Value * (<?= e($lot['unit']) ?>)</label>
                            <input type="number" step="any" name="result_value" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Entered At</label>
                            <input type="datetime-local" name="entered_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instrument</label>
                        <select name="instrument_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($instruments as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= e($inst['instrument_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Test</label>
                        <select name="test_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($tests as $test): ?>
                            <option value="<?= $test['id'] ?>"><?= e($test['test_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Analysis Parameter</label>
                        <select name="parameter_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($parameters as $ap): ?>
                            <option value="<?= $ap['id'] ?>"><?= e($ap['parameter_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const results = <?= json_encode($results) ?>;
const lot = <?= json_encode($lot) ?>;
const westgard = <?= json_encode($westgard) ?>;

const labels = results.map((r, i) => '#' + (i + 1) + ' ' + new Date(r.entered_at).toLocaleDateString('en-IN', {day:'2-digit', month:'2-digit'}));
const values = results.map(r => parseFloat(r.result_value));
const zValues = westgard.mean !== undefined ? values.map(v => (v - westgard.mean) / westgard.sd) : [];

const targetMean = lot.target_mean !== null ? parseFloat(lot.target_mean) : (westgard.mean || 0);
const targetSd = lot.target_sd !== null ? parseFloat(lot.target_sd) : (westgard.sd || 1);

const violations = (westgard.violations || []).map(v => v.index);
const violationIdx = new Set(violations);

function lineData(y) {
    return values.map((_, i) => y);
}

const datasets = [
    {
        label: lot.unit ? ('QC Value (' + lot.unit + ')') : 'QC Value',
        data: values,
        borderColor: '#2b7be4',
        backgroundColor: 'rgba(43,123,228,0.1)',
        fill: true,
        tension: 0.2,
        pointRadius: values.map((_, i) => violationIdx.has(i) ? 7 : 4),
        pointBackgroundColor: values.map((_, i) => violationIdx.has(i) ? '#e74c3c' : '#2b7be4'),
        pointBorderColor: values.map((_, i) => violationIdx.has(i) ? '#c0392b' : '#2b7be4'),
    },
    { label: 'Mean', data: values.map(() => targetMean), borderColor: '#11998e', borderDash: [6, 3], pointRadius: 0, borderWidth: 2 },
    { label: '+1 SD', data: values.map(() => targetMean + targetSd), borderColor: '#f5a623', borderDash: [2, 4], pointRadius: 0, borderWidth: 1 },
    { label: '-1 SD', data: values.map(() => targetMean - targetSd), borderColor: '#f5a623', borderDash: [2, 4], pointRadius: 0, borderWidth: 1 },
    { label: '+2 SD', data: values.map(() => targetMean + 2 * targetSd), borderColor: '#e67e22', borderDash: [4, 4], pointRadius: 0, borderWidth: 1 },
    { label: '-2 SD', data: values.map(() => targetMean - 2 * targetSd), borderColor: '#e67e22', borderDash: [4, 4], pointRadius: 0, borderWidth: 1 },
    { label: '+3 SD', data: values.map(() => targetMean + 3 * targetSd), borderColor: '#e74c3c', borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
    { label: '-3 SD', data: values.map(() => targetMean - 3 * targetSd), borderColor: '#e74c3c', borderDash: [6, 4], pointRadius: 0, borderWidth: 1.5 },
];

new Chart(document.getElementById('ljChart').getContext('2d'), {
    type: 'line',
    data: { labels, datasets },
    options: {
        responsive: true,
        plugins: {
            title: { display: true, text: 'Levey-Jennings Control Chart', font: { size: 14 } },
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
        },
        scales: {
            y: { title: { display: true, text: 'Result Value (' + (lot.unit || '') + ')' } },
            x: { title: { display: true, text: 'Run Sequence' } }
        }
    }
});
</script>
