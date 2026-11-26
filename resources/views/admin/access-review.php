<?php
$title = 'User Access Review (Periodic GxP Audit)';
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Periodic User Access Review</h1>
            <p class="text-muted mb-0">21 CFR Part 11 / EU Annex 11 User Account Governance & Segregation of Duties</p>
        </div>
        <div>
            <a href="/users" class="btn btn-outline-secondary">User Management</a>
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print / Export Audit Record</button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Active & Inactive System Personnel</span>
            <span class="badge bg-secondary"><?= count($users) ?> total users</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Password Changed</th>
                            <th>Account Lock</th>
                            <th>Compliance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($u['full_name']) ?></strong><br>
                                        <small class="text-muted"><?= e($u['username']) ?> &bull; <?= e($u['email']) ?></small>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= e($u['role_name']) ?></span></td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Deactivated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $u['last_login'] ? date('Y-m-d H:i', strtotime($u['last_login'])) : '<span class="text-muted">Never</span>' ?></td>
                                    <td><?= $u['password_changed_at'] ? date('Y-m-d', strtotime($u['password_changed_at'])) : '<span class="text-muted">Initial</span>' ?></td>
                                    <td>
                                        <?php if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                                            <span class="badge bg-danger">Locked until <?= date('H:i', strtotime($u['locked_until'])) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Normal (<?= (int)$u['failed_login_attempts'] ?> fails)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><i class="bi bi-shield-check"></i> Verified</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
