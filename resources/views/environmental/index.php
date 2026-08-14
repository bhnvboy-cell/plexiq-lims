<?php $title = 'Environmental Monitoring'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-cloud-sun me-2"></i>Environmental Monitoring</h4>
    <div class="d-flex gap-2">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2"><i class="bi bi-check-circle me-1"></i><?= count($points ?? []) ?> Points</span>
        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= count($alerts ?? []) ?> Alerts</span>
        <a href="/environmental/points" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history"></i> Points & Readings</a>
        <a href="/environmental/points/create" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> New Point</a>
    </div>
</div>

<!-- Current Readings Summary -->
<div class="row g-3 mb-4">
    <?php if (empty($points)): ?>
    <div class="col-12">
        <div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-cloud-sun display-4 d-block mb-3"></i>No monitoring points configured.</div></div>
    </div>
    <?php else: foreach ($points as $mp): ?>
    <?php
    $lastValue = $mp['last_reading_value'] ?? null;
    $lastTime = $mp['last_reading_time'] ?? null;
    $isAlert = $lastValue !== null && $lastValue !== '' && (
        ($mp['min_threshold'] !== null && (float)$lastValue < (float)$mp['min_threshold']) ||
        ($mp['max_threshold'] !== null && (float)$lastValue > (float)$mp['max_threshold'])
    );
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="card shadow-sm h-100 border-<?= $isAlert ? 'danger' : ($lastValue !== null ? 'success' : 'secondary') ?> border-opacity-25">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="card-title mb-0"><?= e($mp['point_name']) ?></h6>
                        <small class="text-muted"><?= e($mp['location_name'] ?? '—') ?></small>
                    </div>
                    <i class="bi bi-<?= $isAlert ? 'exclamation-triangle-fill text-danger' : 'check-circle-fill text-success' ?> fs-4"></i>
                </div>
                <div class="my-3">
                    <?php if ($lastValue !== null): ?>
                    <div class="display-6 fw-bold text-center <?= $isAlert ? 'text-danger' : 'text-success' ?>">
                        <?= e($lastValue) ?>
                        <small class="fs-6 text-muted"><?= e($mp['unit'] ?? '') ?></small>
                    </div>
                    <div class="text-center small text-muted">
                        <?= $lastTime ? date('d M Y H:i', strtotime($lastTime)) : '' ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-2">
                        <i class="bi bi-dash-lg display-6"></i>
                        <div class="small">No readings yet</div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                    <span>Min: <?= e($mp['min_threshold'] ?? '—') ?></span>
                    <span>Max: <?= e($mp['max_threshold'] ?? '—') ?></span>
                    <a href="/environmental/points/<?= $mp['id'] ?>/readings" class="text-decoration-none fw-bold">History</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- Alerts Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Active Alerts</h6>
        <span class="badge bg-danger"><?= count($alerts ?? []) ?> active</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>Monitoring Point</th>
                    <th>Type</th>
                    <th>Reading</th>
                    <th>Threshold</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alerts)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-check-circle text-success me-1"></i>No active alerts. All monitoring points are within range.</td></tr>
                <?php else: foreach ($alerts as $a): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i', strtotime($a['created_at'])) ?></small></td>
                    <td class="fw-bold"><?= e($a['point_name'] ?? '—') ?><br><small class="text-muted"><?= e($a['location_name'] ?? '') ?></small></td>
                    <td><span class="badge bg-<?= $a['alert_type'] === 'max_violation' ? 'danger' : 'warning' ?>"><?= e($a['alert_type'] ?? '—') ?></span></td>
                    <td class="fw-bold text-danger"><?= e($a['reading_value'] ?? '—') ?></td>
                    <td><code><?= e($a['threshold_value'] ?? '—') ?></code></td>
                    <td><?= e($a['message'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($a['is_resolved'])): ?>
                        <span class="badge bg-success">Acknowledged</span>
                        <?php else: ?>
                        <span class="badge bg-danger">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($a['is_resolved'])): ?>
                        <form method="POST" action="/environmental/alerts/<?= $a['id'] ?>/acknowledge" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i> Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
