<?php
$title = 'Change Control Management (21 CFR Part 211 / EU Annex 11)';
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Change Control Management</h1>
            <p class="text-muted mb-0">Regulated Change Governance: Methods, Specifications, Instruments, and Facilities</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newChangeModal">
                <i class="bi bi-plus-lg"></i> Propose Change
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Active Change Controls</span>
            <span class="badge bg-secondary"><?= count($changes) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Change #</th>
                            <th>Title & Type</th>
                            <th>Proposed By</th>
                            <th>Pre-Approval</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($changes)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No change control records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($changes as $c): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($c['change_number']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($c['title']) ?></strong><br>
                                        <span class="badge bg-info text-dark text-capitalize"><?= htmlspecialchars($c['change_type']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($c['proposed_by_name'] ?? 'System') ?></td>
                                    <td>
                                        <?php if (!empty($c['pre_approved_at'])): ?>
                                            <span class="badge bg-success">Pre-Approved</span><br>
                                            <small class="text-muted"><?= htmlspecialchars($c['pre_approved_by_name'] ?? '') ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($c['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'Submitted'): ?>
                                            <form action="/change-controls/<?= $c['id'] ?>/pre-approve" method="POST" class="d-inline">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Pre-Approve</button>
                                            </form>
                                        <?php endif; ?>
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

<!-- Modal: Propose Change -->
<div class="modal fade" id="newChangeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/change-controls" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Propose Regulated Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Change Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. HPLC Column Replacement for Assay Method">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Change Type</label>
                        <select name="change_type" class="form-select">
                            <option value="Method">Method</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Specification">Specification</option>
                            <option value="Process">Process</option>
                            <option value="Software">Software</option>
                            <option value="Facility">Facility</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Change (21 CFR Part 11 / ALCOA+ requirement)</label>
                    <textarea name="reason_for_change" class="form-control" rows="3" required placeholder="Explain why this change is necessary..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Regulatory & Quality Impact Assessment</label>
                    <textarea name="impact_assessment" class="form-control" rows="3" required placeholder="Assess impact on validated state, data integrity, and compliance..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Change Control</button>
            </div>
        </form>
    </div>
</div>
