<?php
layout('app');
$title = 'Database Partitioning & Lifecycle Archival Plan';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">High-Performance Database Partitioning & Archival</h1>
            <p class="text-muted mb-0">PostgreSQL declarative range partitioning for <code>audit_logs</code> & <code>results</code>, tiered NVMe/Block storage, and ALCOA+ sealed archival</p>
        </div>
        <div>
            <span class="badge bg-primary p-2"><i class="bi bi-database"></i> High-Throughput Strategy</span>
        </div>
    </div>

    <!-- Tiering Policy Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-danger">
                <div class="card-body">
                    <h5 class="fw-bold text-danger"><i class="bi bi-fire"></i> Hot Storage Tier</h5>
                    <p class="small text-muted mb-0"><?= htmlspecialchars($plan['retention_policy']['hot_storage']) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-warning">
                <div class="card-body">
                    <h5 class="fw-bold text-warning"><i class="bi bi-sun"></i> Warm Storage Tier</h5>
                    <p class="small text-muted mb-0"><?= htmlspecialchars($plan['retention_policy']['warm_storage']) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <h5 class="fw-bold text-info"><i class="bi bi-snow"></i> Cold Archival Tier</h5>
                    <p class="small text-muted mb-0"><?= htmlspecialchars($plan['retention_policy']['cold_storage']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Range Partitions Tables -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light fw-bold">
                    Audit Logs Range Partitions (<code>audit_logs</code>)
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Partition Table</th>
                                <th>Date Range</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plan['tables']['audit_logs'] as $part): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($part['table_name']) ?></code></td>
                                    <td><?= htmlspecialchars($part['range']) ?></td>
                                    <td>
                                        <span class="badge <?= $part['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $part['active'] ? 'Active (Hot)' : 'Historical / Standby' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light fw-bold">
                    Test Results Range Partitions (<code>results</code>)
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Partition Table</th>
                                <th>Date Range</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plan['tables']['results'] as $part): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($part['table_name']) ?></code></td>
                                    <td><?= htmlspecialchars($part['range']) ?></td>
                                    <td>
                                        <span class="badge <?= $part['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $part['active'] ? 'Active (Hot)' : 'Historical / Standby' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
