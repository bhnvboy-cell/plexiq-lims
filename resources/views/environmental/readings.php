<?php $title = 'Environmental Readings: ' . e($point['point_name'] ?? 'All Points'); layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-clock-history me-2"></i>Reading History
        <?php if (!empty($point)): ?>
        <small class="text-muted fs-6 ms-2"><?= e($point['point_name']) ?> — <?= e($point['location_name'] ?? '') ?></small>
        <?php endif; ?>
    </h4>
    <a href="/environmental" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <!-- Monitoring Point Selector -->
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Monitoring Points</h6></div>
            <div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto;">
                <?php foreach ($points as $p): ?>
                <a href="/environmental/points/<?= $p['id'] ?>/readings" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($selectedPointId ?? '') == $p['id'] ? 'active' : '' ?>">
                    <div>
                        <div class="fw-bold small"><?= e($p['point_name']) ?></div>
                        <small class="text-muted"><?= e($p['location_name'] ?? '') ?></small>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <!-- Add Reading -->
        <div class="card shadow-sm mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-plus-circle me-1"></i>Record Reading</h6></div>
            <div class="card-body">
                <form method="POST" action="/environmental/points/<?= $point['id'] ?>/readings" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-auto">
                        <label class="form-label small mb-1">Value <span class="text-danger">*</span></label>
                        <input type="number" name="reading_value" step="any" class="form-control" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-1">Unit</label>
                        <input type="text" name="unit" class="form-control" value="<?= e($point['unit'] ?? '°C') ?>" style="width:90px">
                    </div>
                    <div class="col">
                        <label class="form-label small mb-1">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Record</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Chart Area -->
        <div class="card shadow-sm mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>Trend Chart</h6></div>
            <div class="card-body">
                <canvas id="readingsChart" height="200"></canvas>
                <?php if (empty($readings)): ?>
                <div class="text-center text-muted py-4">No readings data available for chart.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Readings Table -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-table me-1"></i>Readings</h6>
                <span class="badge bg-secondary"><?= number_format($paginator['total'] ?? count($readings ?? [])) ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Value</th>
                            <th>Unit</th>
                            <th>Recorded By</th>
                            <th>Notes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($readings)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No readings recorded for this point.</td></tr>
                        <?php else: foreach ($readings as $r): ?>
                        <?php
                        $outOfRange = ($point['min_threshold'] !== null && (float)$r['reading_value'] < (float)$point['min_threshold']) || ($point['max_threshold'] !== null && (float)$r['reading_value'] > (float)$point['max_threshold']);
                        ?>
                        <tr class="<?= $outOfRange ? 'table-danger' : '' ?>">
                            <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($r['created_at'])) ?></small></td>
                            <td class="fw-bold <?= $outOfRange ? 'text-danger' : 'text-success' ?>"><?= e($r['reading_value']) ?></td>
                            <td><?= e($r['unit'] ?? '—') ?></td>
                            <td><?= e($r['recorded_by_name'] ?? '—') ?></td>
                            <td><?= e($r['notes'] ?? '—') ?></td>
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
            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
$chartLabels = [];
$chartValues = [];
foreach (array_reverse($readings) as $r) {
    $chartLabels[] = date('d M H:i', strtotime($r['created_at']));
    $chartValues[] = (float)$r['reading_value'];
}
?>
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
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: false },
            x: { ticks: { maxTicksLimit: 10 } }
        }
    }
});
</script>
