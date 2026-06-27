<?php $title = 'Statistical Process Control'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Statistical Process Control</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-sliders stat-icon"></i>
            <div class="stat-value"><?= $stats['total_parameters'] ?></div>
            <div class="stat-label">Active Parameters</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-database stat-icon"></i>
            <div class="stat-value"><?= $stats['total_readings'] ?></div>
            <div class="stat-label">Total Readings</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-purple">
            <i class="bi bi-tags stat-icon"></i>
            <div class="stat-value"><?= count($stats['categories']) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-dark">
            <i class="bi bi-graph-up stat-icon"></i>
            <div class="stat-value">
                <?php
                $inControl = 0;
                foreach ($params as $p) {
                    $db = \App\Helpers\Database::connect();
                    $stmt = $db->prepare("SELECT COUNT(*) FROM spc_readings WHERE parameter_id = ?");
                    $stmt->execute([$p['id']]);
                    if ($stmt->fetchColumn() > 0) $inControl++;
                }
                echo $inControl;
                ?>
            </div>
            <div class="stat-label">Parameters with Data</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-1"></i>SPC Parameters</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Parameter</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Spec Min</th>
                    <th>Target</th>
                    <th>Spec Max</th>
                    <th>LCL</th>
                    <th>UCL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($params as $p): ?>
                <tr>
                    <td><span class="badge bg-dark"><?= e($p['parameter_code']) ?></span></td>
                    <td><strong><?= e($p['parameter_name']) ?></strong></td>
                    <td><?= e($p['category']) ?></td>
                    <td><?= e($p['unit']) ?></td>
                    <td><?= $p['spec_min'] !== null ? e($p['spec_min']) : '-' ?></td>
                    <td><?= $p['spec_target'] !== null ? e($p['spec_target']) : '-' ?></td>
                    <td><?= $p['spec_max'] !== null ? e($p['spec_max']) : '-' ?></td>
                    <td><?= $p['lcl'] !== null ? e($p['lcl']) : '-' ?></td>
                    <td><?= $p['ucl'] !== null ? e($p['ucl']) : '-' ?></td>
                    <td>
                        <a href="/spc/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i> Chart</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($params)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No SPC parameters configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
