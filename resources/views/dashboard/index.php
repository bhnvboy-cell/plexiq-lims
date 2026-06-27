<?php $title = 'Dashboard'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
    <span class="badge bg-primary bg-opacity-10 text-primary fs-6 px-3 py-2">Welcome, <?= htmlspecialchars($auth['user']['full_name'] ?? $auth['user']['username']) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-collection stat-icon"></i>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Samples</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-value"><?= ($stats['registered'] ?? 0) + ($stats['in_progress'] ?? 0) ?></div>
            <div class="stat-label">Pending / In Progress</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-file-check stat-icon"></i>
            <div class="stat-value"><?= $stats['coa_released'] ?></div>
            <div class="stat-label">COA Released</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-red">
            <i class="bi bi-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $stats['urgent'] ?></div>
            <div class="stat-label">Urgent / Overdue</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i>Recent Samples</span>
                <a href="/samples" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Sample Code</th><th>Customer</th><th>Product</th><th>Status</th><th>Priority</th><th>Created</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentSamples as $s): ?>
                        <tr>
                            <td><a href="/samples/<?= $s['id'] ?>" class="fw-medium text-decoration-none"><?= e($s['sample_code']) ?></a></td>
                            <td><?= e($s['customer_name'] ?? 'N/A') ?></td>
                            <td><?= e($s['product_name'] ?? 'N/A') ?></td>
                            <td><?php $map = ['Registered'=>'secondary','In Progress'=>'info','Reviewed'=>'primary','Approved'=>'success','COA Released'=>'success','Rejected'=>'danger']; ?>
                                <span class="badge bg-<?= $map[$s['status']] ?? 'secondary' ?>"><?= e($s['status']) ?></span>
                            </td>
                            <td><?php $pmap = ['Urgent'=>'danger','High'=>'warning','Normal'=>'primary','Low'=>'secondary']; ?>
                                <span class="badge bg-<?= $pmap[$s['priority']] ?? 'secondary' ?>"><?= e($s['priority']) ?></span>
                            </td>
                            <td><small class="text-muted"><?= date('Y-m-d', strtotime($s['created_at'])) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentSamples)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No samples registered yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="bi bi-bar-chart me-1"></i>Status Overview</div>
                    <div class="card-body">
                        <canvas id="statusChart" height="180"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clipboard-check me-1"></i>Test Status</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Pending Tests</span>
                            <span class="fw-bold"><?= $pendingTests ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">In Progress</span>
                            <span class="fw-bold"><?= $inProgressTests ?></span>
                        </div>
                        <a href="/tests/pending" class="btn btn-primary btn-sm w-100"><i class="bi bi-arrow-right me-1"></i>Go to Tests</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Registered', 'In Progress', 'Reviewed', 'Approved', 'COA Released', 'Rejected'],
        datasets: [{
            data: [<?= $stats['registered'] ?>, <?= $stats['in_progress'] ?>, <?= $stats['reviewed'] ?>, <?= $stats['approved'] ?>, <?= $stats['coa_released'] ?>, 0],
            backgroundColor: ['#6c757d', '#0dcaf0', '#0d6efd', '#198754', '#20c997', '#dc3545']
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
});
</script>
