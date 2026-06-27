<?php $title = 'Environmental Readings: ' . e($point['point_name'] ?? 'All Points'); layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-clock-history me-2"></i>Reading History
        <?php if (!empty($point)): ?>
        <small class="text-muted fs-6 ms-2"><?= e($point['point_name']) ?> — <?= e($point['location'] ?? '') ?></small>
        <?php endif; ?>
    </h4>
    <a href="/environmental" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="row g-3">
    <!-- Monitoring Point Selector -->
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Monitoring Points</h6></div>
            <div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto;">
                <?php foreach ($points as $p): ?>
                <a href="?point_id=<?= $p['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($selectedPointId ?? '') == $p['id'] ? 'active' : '' ?>">
                    <div>
                        <div class="fw-bold small"><?= e($p['point_name']) ?></div>
                        <small class="text-muted"><?= e($p['location'] ?? '') ?></small>
                    </div>
                    <small><?= $p['reading_count'] ?? 0 ?> readings</small>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Readings Content -->
    <div class="col-md-9">
        <!-- Chart Area -->
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>Trend Chart</h6>
                <div class="d-flex gap-2">
                    <select id="rangeSelector" class="form-select form-select-sm" style="width:auto;" onchange="updateChartRange()">
                        <option value="24h" <?= ($range ?? '7d') === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                        <option value="7d" <?= ($range ?? '7d') === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30d" <?= ($range ?? '') === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90d" <?= ($range ?? '') === '90d' ? 'selected' : '' ?>>Last 90 Days</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <canvas id="readingsChart" height="250"></canvas>
                <?php if (empty($readings)): ?>
                <div class="text-center text-muted py-4">No readings data available for chart.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Readings Table -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-table me-1"></i>Readings</h6>
                <span class="badge bg-secondary"><?= count($readings ?? []) ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Monitoring Point</th>
                            <th>Parameter</th>
                            <th>Value</th>
                            <th>Unit</th>
                            <th>Min Threshold</th>
                            <th>Max Threshold</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($readings)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No readings found for the selected criteria.</td></tr>
                        <?php else: foreach ($readings as $r): ?>
                        <?php $outOfRange = (!empty($r['min_threshold']) && $r['value'] < $r['min_threshold']) || (!empty($r['max_threshold']) && $r['value'] > $r['max_threshold']); ?>
                        <tr class="<?= $outOfRange ? 'table-danger' : '' ?>">
                            <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($r['recorded_at'])) ?></small></td>
                            <td><?= e($r['point_name'] ?? '—') ?></td>
                            <td><?= e($r['parameter'] ?? '—') ?></td>
                            <td class="fw-bold <?= $outOfRange ? 'text-danger' : 'text-success' ?>"><?= e($r['value']) ?></td>
                            <td><?= e($r['unit'] ?? '—') ?></td>
                            <td><?= e($r['min_threshold'] ?? '—') ?></td>
                            <td><?= e($r['max_threshold'] ?? '—') ?></td>
                            <td>
                                <?php if ($outOfRange): ?>
                                <span class="badge bg-danger">Out of Range</span>
                                <?php else: ?>
                                <span class="badge bg-success">Within Range</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($pagination)): ?>
            <div class="card-footer d-flex justify-content-center">
                <nav><?= $pagination ?></nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($chartLabels) && !empty($chartValues)): ?>
new Chart(document.getElementById('readingsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: '<?= e($point['point_name'] ?? 'Readings') ?>',
            data: <?= json_encode($chartValues) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: { beginAtZero: false },
            x: { ticks: { maxTicksLimit: 10 } }
        }
    }
});
<?php endif; ?>

function updateChartRange() {
    const range = document.getElementById('rangeSelector').value;
    const params = new URLSearchParams(window.location.search);
    params.set('range', range);
    window.location.search = params.toString();
}
</script>
