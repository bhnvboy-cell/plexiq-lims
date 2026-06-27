<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-clock-history me-2"></i>Login History</h4>
        <span class="text-muted small">User login session records</span>
    </div>
    <a href="/audit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Audit</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Login Time</th>
                    <th>Logout Time</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($h['full_name'] ?? $h['username']) ?></span></td>
                    <td><small class="text-muted"><?= e($h['login_at']) ?></small></td>
                    <td><?= $h['logout_at'] ? e($h['logout_at']) : '<span class="badge bg-success bg-opacity-10 text-success">Active</span>' ?></td>
                    <td><small class="text-muted"><?= e($h['ip_address'] ?? '-') ?></small></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><small class="text-muted"><?= e($h['user_agent'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
