<?php
layout('app');
$title = 'Job Queue & Worker Operations';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Job Queue & Worker Supervision</h1>
            <p class="text-muted mb-0">High-throughput asynchronous tasks, automated retries with exponential backoff & Dead-Letter Queue (DLQ)</p>
        </div>
        <div>
            <span class="badge bg-success p-2"><i class="bi bi-cpu"></i> Queue Engine Active</span>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Queued Jobs</div>
                    <h3 class="mb-0 fw-bold"><?= (int)$stats['total_jobs'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Pending in Queue</div>
                    <h3 class="mb-0 fw-bold text-warning"><?= (int)$stats['pending_count'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Completed Jobs</div>
                    <h3 class="mb-0 fw-bold text-success"><?= (int)$stats['completed_count'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Dead-Letter Queue (DLQ)</div>
                    <h3 class="mb-0 fw-bold text-danger"><?= (int)$stats['dead_letter_count'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Jobs Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Recent Asynchronous Jobs</span>
            <span class="badge bg-secondary"><?= count($recentJobs) ?> displayed</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Job ID</th>
                            <th>Queue</th>
                            <th>Handler / Class</th>
                            <th>Attempts</th>
                            <th>Status</th>
                            <th>Available At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentJobs)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No background jobs registered in queue.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentJobs as $job): ?>
                                <tr>
                                    <td>#<?= (int)$job['id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($job['queue_name']) ?></span></td>
                                    <td><code><?= htmlspecialchars($job['job_class']) ?></code></td>
                                    <td><?= (int)$job['attempts'] ?> / <?= (int)$job['max_attempts'] ?></td>
                                    <td>
                                        <span class="badge <?= $job['status'] === 'completed' ? 'bg-success' : ($job['status'] === 'pending' ? 'bg-warning text-dark' : ($job['status'] === 'dead_letter' ? 'bg-danger' : 'bg-info')) ?>">
                                            <?= htmlspecialchars($job['status']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($job['available_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
