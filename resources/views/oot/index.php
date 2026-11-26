<?php
$title = 'Statistical Out-of-Trend (OOT) Investigations';
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Statistical Out-of-Trend (OOT) Monitoring</h1>
            <p class="text-muted mb-0">Nelson Rules, 3-Sigma Historical Drift & Replicate Outlier Investigations</p>
        </div>
        <div>
            <a href="/tests/review" class="btn btn-outline-secondary"><i class="bi bi-card-checklist"></i> Review by Exception</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Active & Historical OOT Alerts</span>
            <span class="badge bg-secondary"><?= count($incidents) ?> incidents</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Flagged At</th>
                            <th>Sample / Product</th>
                            <th>Test Parameter</th>
                            <th>Rule Triggered</th>
                            <th>Measured vs Expected</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incidents)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No out-of-trend incidents detected. All processes running within control limits.</td></tr>
                        <?php else: ?>
                            <?php foreach ($incidents as $inc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($inc['flagged_at']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($inc['sample_code']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($inc['product_name'] ?? 'N/A') ?> (Batch: <?= htmlspecialchars($inc['batch_number'] ?? 'N/A') ?>)</small>
                                    </td>
                                    <td><strong><?= htmlspecialchars($inc['test_name']) ?></strong></td>
                                    <td>
                                        <span class="badge bg-danger text-uppercase"><?= htmlspecialchars($inc['rule_type']) ?></span><br>
                                        <small class="text-muted"><?= htmlspecialchars($inc['investigation_notes'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        Measured: <strong><?= htmlspecialchars($inc['measured_value']) ?></strong><br>
                                        <small class="text-muted">Mean: <?= round((float)$inc['expected_mean'], 3) ?> &bull; &sigma;: <?= round((float)$inc['sigma'], 3) ?></small>
                                    </td>
                                    <td>
                                        <?php $badge = match($inc['status']) {
                                            'Open' => 'bg-danger',
                                            'Under Review' => 'bg-warning text-dark',
                                            'Closed' => 'bg-success',
                                            'Escalated_To_CAPA' => 'bg-dark',
                                            default => 'bg-secondary'
                                        }; ?>
                                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($inc['status']) ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $inc['id'] ?>">
                                            Investigate
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal: Review OOT -->
                                <div class="modal fade" id="reviewModal<?= $inc['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="/oot/<?= $inc['id'] ?>/review" method="POST" class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Investigate OOT: <?= htmlspecialchars($inc['sample_code']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Investigation Disposition</label>
                                                    <select name="status" class="form-select">
                                                        <option value="Under Review" <?= $inc['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                                                        <option value="Closed" <?= $inc['status'] === 'Closed' ? 'selected' : '' ?>>Closed (Assign Cause / False Alarm)</option>
                                                        <option value="Escalated_To_CAPA" <?= $inc['status'] === 'Escalated_To_CAPA' ? 'selected' : '' ?>>Escalate to CAPA</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Investigation & Root Cause Notes</label>
                                                    <textarea name="investigation_notes" class="form-control" rows="4" required><?= htmlspecialchars($inc['investigation_notes'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Disposition</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
