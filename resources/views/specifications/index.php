<?php
layout('app');
$title = 'Analytical Specifications (Versioned Master Data)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Product Specifications</h1>
            <p class="text-muted mb-0">Commercial 4-Layer Master Data: Versioned Specifications, Grades, and GxP Effective Dates</p>
        </div>
        <div>
            <a href="/specifications/create" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> New Specification
            </a>
            <a href="/product-test-plans" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-diagram-3"></i> Product Test Plans
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-journal-check me-2"></i>Active Specifications Master Index</span>
            <span class="badge bg-secondary"><?= count($specifications) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Spec Code</th>
                            <th>Product & Type</th>
                            <th>Grade / Compendial</th>
                            <th>Customer (Target)</th>
                            <th>Active Version</th>
                            <th>Total Revisions</th>
                            <th>Created Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($specifications)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No specifications found. Click "New Specification" to create one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($specifications as $s): ?>
                                <tr>
                                    <td>
                                        <a href="/specifications/<?= $s['id'] ?>" class="fw-bold text-decoration-none">
                                            <code><?= htmlspecialchars($s['spec_code']) ?></code>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s['product_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($s['product_code']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($s['grade'] ?? 'Standard') ?></span>
                                    </td>
                                    <td>
                                        <?= !empty($s['customer_name']) ? htmlspecialchars($s['customer_name']) : '<span class="text-muted">Global / All Customers</span>' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['active_version'])): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>v<?= $s['active_version'] ?>.0 Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Draft Only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= $s['version_count'] ?> version(s)</span>
                                    </td>
                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($s['created_at'])) ?></small></td>
                                    <td class="text-end">
                                        <a href="/specifications/<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Manage Specs
                                        </a>
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
